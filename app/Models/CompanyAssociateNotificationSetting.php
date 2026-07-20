<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemNotificationKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Use SystemNotificationRecipientSetting / SystemNotificationRecipients.
 */
class CompanyAssociateNotificationSetting extends Model
{
    protected $fillable = [
        'notification_emails',
        'notification_phones',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_emails' => 'array',
            'notification_phones' => 'array',
        ];
    }

    public static function instance(): self
    {
        $modern = SystemNotificationRecipientSetting::for(SystemNotificationKey::CompanyAssociateRegistration);

        return new self([
            'notification_emails' => $modern->emails(),
            'notification_phones' => $modern->phones(),
            'updated_by' => $modern->updated_by,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function emails(): array
    {
        return $this->normalizeList($this->notification_emails);
    }

    /**
     * @return array<int, string>
     */
    public function phones(): array
    {
        return $this->normalizeList($this->notification_phones);
    }

    /**
     * @param  array<int, string>|null  $values
     * @return array<int, string>
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
