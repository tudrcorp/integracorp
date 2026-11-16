<?php

declare(strict_types=1);

namespace App\Filament\Shared\Telegram\Pages;

use App\Enums\TelegramRecipientStatus;
use App\Enums\TelegramRecipientType;
use App\Models\TelegramRecipient;
use App\Models\User;
use App\Support\Telegram\TelegramLinkDelivery;
use App\Support\Telegram\TelegramLinkService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

/**
 * «Conectar Telegram»: el personal @tudrencasa.com vincula su Telegram para
 * recibir allí las notificaciones internas. Se registra en todos los paneles
 * internos y se abre desde el menú de usuario.
 */
class ConnectTelegram extends Page
{
    protected static ?string $slug = 'conectar-telegram';

    protected static ?string $title = 'Conectar Telegram';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.shared.telegram.connect-telegram';

    public ?string $linkUrl = null;

    public ?string $linkExpiresAt = null;

    /** Código de 6 dígitos del enlace vigente, para escribírselo al bot. */
    public ?string $linkCode = null;

    public ?string $linkExpiresAtIso = null;

    public static function canAccess(): bool
    {
        return self::userCanConnect(Auth::user());
    }

    public static function userCanConnect(mixed $user): bool
    {
        return $user instanceof User
            && strtoupper(trim((string) $user->status)) === 'ACTIVO'
            && TelegramRecipient::isInternalEmail((string) $user->email);
    }

    public function getSubheading(): ?string
    {
        return 'Reciba en Telegram los avisos internos de IntegraCorp: alertas, asignaciones y claves. Es gratis y no compromete su WhatsApp.';
    }

    #[Computed]
    public function recipient(): ?TelegramRecipient
    {
        return TelegramRecipient::query()
            ->where('type', TelegramRecipientType::User->value)
            ->where('user_id', Auth::id())
            ->first();
    }

    public function isLinked(): bool
    {
        return $this->recipient?->status === TelegramRecipientStatus::Active && filled($this->recipient?->chat_id);
    }

    public function qrSvg(): string
    {
        if ($this->linkUrl === null) {
            return '';
        }

        try {
            return (string) QrCode::format('svg')->size(260)->margin(1)->errorCorrection('M')->generate($this->linkUrl);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Mientras hay un enlace en pantalla, la vista consulta cada pocos segundos
     * si la persona ya pulsó «Iniciar», para confirmar sin recargar.
     */
    public function checkLinked(): void
    {
        unset($this->recipient);

        if ($this->linkUrl !== null && $this->isLinked()) {
            $this->linkUrl = null;
            $this->linkExpiresAt = null;
            $this->linkCode = null;
            $this->linkExpiresAtIso = null;

            Notification::make()
                ->title('¡Telegram vinculado!')
                ->body('Desde ahora recibirá allí sus notificaciones internas.')
                ->success()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateLink')
                ->label(fn (): string => $this->isLinked() ? 'Vincular otro Telegram' : 'Generar enlace')
                ->icon('heroicon-o-link')
                ->color(fn (): string => $this->isLinked() ? 'gray' : 'primary')
                ->requiresConfirmation(fn (): bool => $this->isLinked())
                ->modalHeading('Vincular otro Telegram')
                ->modalDescription('El Telegram actual seguirá recibiendo avisos hasta que el nuevo pulse «Iniciar». Después, los avisos irán solo al nuevo.')
                ->action(fn () => $this->generateLink()),
            Action::make('unlink')
                ->label('Desvincular')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => $this->isLinked())
                ->requiresConfirmation()
                ->modalHeading('Desvincular Telegram')
                ->modalDescription('Dejará de recibir avisos por Telegram y volverán a llegarle por WhatsApp o correo. Podrá vincularse de nuevo cuando quiera.')
                ->modalSubmitActionLabel('Sí, desvincular')
                ->action(function (): void {
                    $recipient = $this->recipient;

                    if ($recipient instanceof TelegramRecipient) {
                        TelegramLinkService::unlink($recipient, (string) (Auth::user()?->name ?? 'usuario'));
                    }

                    unset($this->recipient);

                    Notification::make()->title('Telegram desvinculado')->success()->send();
                }),
        ];
    }

    public function generateLink(): void
    {
        $user = Auth::user();

        if (! self::userCanConnect($user)) {
            abort(403);
        }

        if (! filled(config('telegram.bot_token')) || ! (bool) config('telegram.enabled', true)) {
            Notification::make()->title('Telegram no está disponible')->body('El administrador aún no activó el bot en este servidor.')->warning()->send();

            return;
        }

        try {
            $link = TelegramLinkService::issueForUser($user, (string) $user->name);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('No se pudo generar el enlace')->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->linkUrl = $link['url'];
        $this->linkCode = $link['code'];
        $this->linkExpiresAt = $link['expires_at']->timezone(config('app.timezone'))->format('h:i A');
        $this->linkExpiresAtIso = $link['expires_at']->toIso8601String();
        unset($this->recipient);
    }

    public function botUsername(): string
    {
        return (string) config('telegram.bot_username');
    }

    public function sendLinkByWhatsAppAction(): Action
    {
        return $this->sendLinkAction('sendLinkByWhatsApp', TelegramLinkDelivery::CHANNEL_WHATSAPP)
            ->label('Enviarme el enlace por WhatsApp')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('success')
            ->size('lg');
    }

    public function sendLinkByEmailAction(): Action
    {
        return $this->sendLinkAction('sendLinkByEmail', TelegramLinkDelivery::CHANNEL_MAIL)
            ->label('Enviármelo por correo')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->link();
    }

    private function sendLinkAction(string $name, string $channel): Action
    {
        return Action::make($name)
            ->action(function () use ($channel): void {
                $user = Auth::user();

                if (! self::userCanConnect($user)) {
                    abort(403);
                }

                if ($this->linkUrl === null || $this->linkCode === null || $this->linkExpiresAtIso === null
                    || Carbon::parse($this->linkExpiresAtIso)->isPast()) {
                    $this->generateLink();
                }

                if ($this->linkUrl === null || $this->linkCode === null || $this->linkExpiresAtIso === null) {
                    return;
                }

                try {
                    $masked = TelegramLinkDelivery::send($user, $channel, $this->linkUrl, $this->linkCode, Carbon::parse($this->linkExpiresAtIso));
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se envió')->body($exception->getMessage())->warning()->send();

                    return;
                }

                Notification::make()
                    ->title($channel === TelegramLinkDelivery::CHANNEL_WHATSAPP ? 'Revise su WhatsApp' : 'Revise su correo')
                    ->body('Le enviamos el enlace a '.$masked.'. Ábralo desde su teléfono y pulse «Iniciar» en Telegram. Llega en menos de un minuto.')
                    ->success()
                    ->send();
            });
    }
}
