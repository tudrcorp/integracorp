<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemNotificationKey;
use App\Enums\TelegramDeliveryMode;
use Illuminate\Database\Eloquent\Model;

class SystemNotificationRecipientSetting extends Model
{
    protected $fillable = [
        'notification_key',
        'notification_emails',
        'notification_email_modes',
        'notification_phones',
        'telegram_mode',
        'telegram_group_ids',
        'telegram_recipient_ids',
        'is_active',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_key' => SystemNotificationKey::class,
            'notification_emails' => 'array',
            'notification_email_modes' => 'array',
            'notification_phones' => 'array',
            'telegram_group_ids' => 'array',
            'telegram_recipient_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function for(SystemNotificationKey $key): self
    {
        return static::query()->firstOrCreate(
            ['notification_key' => $key->value],
            [
                'notification_emails' => $key->defaultEmails(),
                'notification_phones' => $key->defaultPhones(),
                'is_active' => true,
            ],
        );
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * @return list<string>
     */
    public function emails(): array
    {
        return $this->normalizeList($this->notification_emails);
    }

    public const EMAIL_MODE_CC = 'cc';

    public const EMAIL_MODE_BCC = 'bcc';

    /**
     * Modo de copia de un correo testigo. Sin dato es CC, como funcionaba antes de existir el modo.
     */
    public function emailMode(string $email): string
    {
        $modes = is_array($this->notification_email_modes) ? $this->notification_email_modes : [];

        return ($modes[strtolower(trim($email))] ?? null) === self::EMAIL_MODE_BCC
            ? self::EMAIL_MODE_BCC
            : self::EMAIL_MODE_CC;
    }

    /**
     * @return array{cc: list<string>, bcc: list<string>}
     */
    public function emailsByMode(): array
    {
        $split = ['cc' => [], 'bcc' => []];

        foreach ($this->emails() as $email) {
            $split[$this->emailMode($email)][] = $email;
        }

        return $split;
    }

    /**
     * @return list<string>
     */
    public function phones(): array
    {
        return $this->normalizeList($this->notification_phones);
    }

    /**
     * Destino por Telegram. Un valor desconocido o vacío se toma como apagado:
     * el aviso sigue saliendo por los canales de siempre.
     */
    public function telegramMode(): TelegramDeliveryMode
    {
        return TelegramDeliveryMode::tryFrom((string) ($this->telegram_mode ?? '')) ?? TelegramDeliveryMode::Off;
    }

    /**
     * @return list<int>
     */
    public function telegramGroupIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', is_array($this->telegram_group_ids) ? $this->telegram_group_ids : []),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /**
     * Personas de Telegram elegidas a mano (además de los contactos ya vinculados).
     *
     * @return list<int>
     */
    public function telegramRecipientIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', is_array($this->telegram_recipient_ids) ? $this->telegram_recipient_ids : []),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /**
     * @param  array<int, string>|null  $values
     * @return list<string>
     */
    private function normalizeList(?array $values): array
    {
        if ($values === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values,
        )));
    }
}
