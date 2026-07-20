<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemNotificationKey;
use Illuminate\Database\Eloquent\Model;

class SystemNotificationRecipientSetting extends Model
{
    protected $fillable = [
        'notification_key',
        'notification_emails',
        'notification_phones',
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
            'notification_phones' => 'array',
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

    /**
     * @return list<string>
     */
    public function phones(): array
    {
        return $this->normalizeList($this->notification_phones);
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
