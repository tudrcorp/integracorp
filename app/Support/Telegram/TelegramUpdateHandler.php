<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use App\Enums\TelegramRecipientStatus;
use App\Enums\TelegramRecipientType;
use App\Models\TelegramRecipient;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa lo que Telegram nos manda (por webhook en producción o por consulta
 * periódica en local): vinculaciones con «/start <código>», comandos /estado y
 * /ayuda, el bot agregado o retirado de un grupo, personas que bloquean el bot
 * y grupos que Telegram convierte en supergrupo (cambia su chat_id).
 *
 * Nunca lanza: Telegram reintenta el webhook si no recibe 200, y un error aquí
 * no debe provocar una tormenta de reintentos.
 */
final class TelegramUpdateHandler
{
    /** Tipos de actualización que pedimos a Telegram. */
    public const ALLOWED_UPDATES = ['message', 'my_chat_member'];

    public function __construct(private readonly TelegramBotClient $client) {}

    public static function make(): self
    {
        return new self(TelegramBotClient::make());
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        try {
            $updateId = (int) ($update['update_id'] ?? 0);

            if ($updateId > 0 && ! Cache::add('telegram:update:'.$updateId, 1, now()->addDay())) {
                return;
            }

            if (is_array($update['my_chat_member'] ?? null)) {
                $this->handleMembership($update['my_chat_member']);

                return;
            }

            if (is_array($update['message'] ?? null)) {
                $this->handleMessage($update['message']);
            }
        } catch (Throwable $exception) {
            Log::error('Telegram: no se pudo procesar una actualización.', [
                'update_id' => $update['update_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(array $message): void
    {
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $chatId = (string) ($chat['id'] ?? '');
        $chatType = (string) ($chat['type'] ?? '');

        if ($chatId === '') {
            return;
        }

        if (isset($message['migrate_to_chat_id'])) {
            $this->migrateGroup($chatId, (string) $message['migrate_to_chat_id']);

            return;
        }

        if ($chatType !== 'private') {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $username = filled($from['username'] ?? null) ? (string) $from['username'] : null;

        if (preg_match('/^(?:\/vincular(?:@\w+)?\s+)?(\d{3})[\s-]?(\d{3})$/i', $text, $matches) === 1) {
            $this->reply($chatId, TelegramLinkService::consumeShortCode($matches[1].$matches[2], $chatId, $username)->message);

            return;
        }

        if ($text === '' || ! str_starts_with($text, '/')) {
            $this->reply($chatId, 'Este bot solo envía notificaciones internas de IntegraCorp. Para vincularse, escríbame el código de 6 números de IntegraCorp → «Conectar Telegram». Escriba /ayuda para más opciones.');

            return;
        }

        [$command, $argument] = array_pad(preg_split('/\s+/', $text, 2) ?: [], 2, '');
        $command = mb_strtolower((string) preg_replace('/@.*$/', '', (string) $command));

        match ($command) {
            '/start' => $this->handleStart($chatId, trim((string) $argument), $username),
            '/estado' => $this->reply($chatId, $this->statusMessage($chatId)),
            '/ayuda', '/help' => $this->reply($chatId, $this->helpMessage()),
            '/vincular' => $this->reply($chatId, 'Escriba el código de 6 números que ve en IntegraCorp → menú de usuario → «Conectar Telegram». Por ejemplo: 482913'),
            default => $this->reply($chatId, 'No conozco ese comando. Escriba /ayuda.'),
        };
    }

    private function handleStart(string $chatId, string $token, ?string $username): void
    {
        if ($token === '') {
            $this->reply($chatId, 'Hola. Para recibir aquí las notificaciones internas, entre a IntegraCorp → menú de usuario → «Conectar Telegram» → «Generar enlace» y escríbame aquí el código de 6 números que aparece. Si ya está vinculado, escriba /estado.');

            return;
        }

        $result = TelegramLinkService::consume($token, $chatId, $username);

        $this->reply($chatId, $result->message);
    }

    /**
     * El bot entra o sale de un chat: grupos (agregado / retirado) y chats
     * privados (la persona bloquea o desbloquea el bot).
     *
     * @param  array<string, mixed>  $membership
     */
    private function handleMembership(array $membership): void
    {
        $chat = is_array($membership['chat'] ?? null) ? $membership['chat'] : [];
        $chatId = (string) ($chat['id'] ?? '');
        $chatType = (string) ($chat['type'] ?? '');
        $newStatus = (string) (($membership['new_chat_member']['status'] ?? null) ?? '');
        $from = is_array($membership['from'] ?? null) ? $membership['from'] : [];
        $addedBy = trim(((string) ($from['first_name'] ?? '')).' '.((string) ($from['last_name'] ?? '')));

        if ($chatId === '') {
            return;
        }

        $isIn = in_array($newStatus, ['member', 'administrator', 'creator', 'restricted'], true);

        if (in_array($chatType, ['group', 'supergroup'], true)) {
            $isIn ? $this->groupJoined($chatId, (string) ($chat['title'] ?? 'Grupo sin nombre'), $addedBy) : $this->groupLeft($chatId);

            return;
        }

        if ($chatType === 'private') {
            $this->privateChatChanged($chatId, $isIn);
        }
    }

    private function groupJoined(string $chatId, string $title, string $addedBy): void
    {
        $group = TelegramRecipient::query()->groups()->where('chat_id', $chatId)->first();

        if ($group instanceof TelegramRecipient) {
            $group->forceFill([
                'name' => $title,
                'status' => $group->status === TelegramRecipientStatus::Active ? TelegramRecipientStatus::Active : TelegramRecipientStatus::PendingApproval,
                'updated_by' => 'telegram:'.($addedBy !== '' ? $addedBy : 'grupo'),
            ])->save();
        } else {
            $group = TelegramRecipient::query()->create([
                'type' => TelegramRecipientType::Group,
                'status' => TelegramRecipientStatus::PendingApproval,
                'name' => $title,
                'chat_id' => $chatId,
                'reason' => $addedBy !== '' ? 'Agregado por '.$addedBy.' en Telegram' : null,
                'created_by' => 'telegram:'.($addedBy !== '' ? $addedBy : 'grupo'),
            ]);
        }

        $this->audit('AUDIT_TELEGRAM_GROUP_JOINED', $group, ['added_by' => $addedBy]);

        if ($group->status === TelegramRecipientStatus::PendingApproval) {
            $this->reply($chatId, 'Hola. Este grupo quedó registrado en IntegraCorp y está pendiente de aprobación. Hasta que un administrador lo apruebe, no recibirá notificaciones.');
        }
    }

    private function groupLeft(string $chatId): void
    {
        $group = TelegramRecipient::query()->groups()->where('chat_id', $chatId)->first();

        if (! $group instanceof TelegramRecipient) {
            return;
        }

        $group->forceFill([
            'status' => TelegramRecipientStatus::Blocked,
            'last_error' => 'El bot fue retirado del grupo.',
            'last_error_at' => now(),
            'updated_by' => 'telegram',
        ])->save();

        $this->audit('AUDIT_TELEGRAM_GROUP_LEFT', $group);
    }

    private function privateChatChanged(string $chatId, bool $isIn): void
    {
        $recipients = TelegramRecipient::query()
            ->where('chat_id', $chatId)
            ->whereIn('type', [TelegramRecipientType::User->value, TelegramRecipientType::External->value])
            ->get();

        foreach ($recipients as $recipient) {
            if (! $isIn && $recipient->status === TelegramRecipientStatus::Active) {
                $recipient->forceFill([
                    'status' => TelegramRecipientStatus::Blocked,
                    'last_error' => 'La persona bloqueó el bot en Telegram.',
                    'last_error_at' => now(),
                    'updated_by' => 'telegram',
                ])->save();

                $this->audit('AUDIT_TELEGRAM_RECIPIENT_BLOCKED_BOT', $recipient);
            }

            if ($isIn && $recipient->status === TelegramRecipientStatus::Blocked) {
                $recipient->forceFill([
                    'status' => TelegramRecipientStatus::Active,
                    'last_error' => null,
                    'last_error_at' => null,
                    'updated_by' => 'telegram',
                ])->save();

                $this->audit('AUDIT_TELEGRAM_RECIPIENT_UNBLOCKED_BOT', $recipient);
            }
        }
    }

    private function migrateGroup(string $oldChatId, string $newChatId): void
    {
        TelegramRecipient::query()->groups()->where('chat_id', $oldChatId)->update([
            'chat_id' => $newChatId,
            'updated_by' => 'telegram:supergrupo',
            'updated_at' => now(),
        ]);
    }

    private function statusMessage(string $chatId): string
    {
        $recipient = TelegramRecipient::query()
            ->where('chat_id', $chatId)
            ->whereIn('type', [TelegramRecipientType::User->value, TelegramRecipientType::External->value])
            ->latest('linked_at')
            ->first();

        if (! $recipient instanceof TelegramRecipient) {
            return 'Este Telegram no está vinculado a IntegraCorp. Entre a su panel → menú de usuario → «Conectar Telegram».';
        }

        if ($recipient->canReceive()) {
            return '✅ Vinculado como '.$recipient->name.' desde el '.($recipient->linked_at?->format('d/m/Y H:i') ?? '—').'. Recibirá aquí las notificaciones internas.';
        }

        return 'Este Telegram está registrado como '.$recipient->name.', pero hoy no recibe notificaciones ('.$recipient->status->label().'). Pida al administrador que lo revise.';
    }

    private function helpMessage(): string
    {
        return "IntegraCorp · notificaciones internas\n\n"
            ."• Código de 6 números — vincular este Telegram (lo ve en IntegraCorp → «Conectar Telegram»)\n"
            ."• /estado — ver si este Telegram está vinculado\n"
            ."• /ayuda — esta ayuda\n\n"
            .'Para vincularse: IntegraCorp → menú de usuario → «Conectar Telegram». El bot no lee sus mensajes ni los de los grupos.';
    }

    private function reply(string $chatId, string $text): void
    {
        try {
            $this->client->sendMessage($chatId, TelegramText::escape($text));
        } catch (Throwable $exception) {
            Log::warning('Telegram: no se pudo responder en el chat.', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(string $action, TelegramRecipient $recipient, array $details = []): void
    {
        try {
            SecurityAudit::log($action, 'telegram.webhook', [
                'telegram_recipient_id' => $recipient->getKey(),
                'type' => $recipient->type->value,
                'name' => $recipient->name,
                ...$details,
            ]);
        } catch (Throwable) {
        }
    }
}
