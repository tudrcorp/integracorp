<?php

namespace App\Models;

use App\Support\MassNotificationRecipientDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MassNotification extends Model
{
    protected $table = 'mass_notifications';

    protected $fillable = [
        'mass_notification_folder_id',
        'title',
        'content',
        'file',
        'name',
        'email',
        'phone',
        'is_sent',
        'is_approved',
        'approved_by',
        'reason',
        'date_programed',
        'header_title',
        'email_subject',
        'test_email_success_count',
        'test_email_failed_count',
        'last_test_email_to',
        'last_test_email_at',
        'last_test_email_error',
        'test_whatsapp_success_count',
        'test_whatsapp_failed_count',
        'last_test_whatsapp_to',
        'last_test_whatsapp_at',
        'last_test_whatsapp_error',
        'channels',
        'type',
    ];

    protected $casts = [
        'channels' => 'array',
        'date_programed' => 'datetime',
        'is_sent' => 'boolean',
        'is_approved' => 'boolean',
        'last_test_email_at' => 'datetime',
        'last_test_whatsapp_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MassNotification $notification): void {
            if ($notification->mass_notification_folder_id !== null) {
                return;
            }

            $defaultFolderId = MassNotificationFolder::query()
                ->where('is_default', true)
                ->value('id');

            if ($defaultFolderId !== null) {
                $notification->mass_notification_folder_id = $defaultFolderId;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MassNotificationFolder::class, 'mass_notification_folder_id');
    }

    public function dataNotifications(): HasMany
    {
        return $this->hasMany(DataNotification::class, 'mass_notification_id', 'id');
    }

    /**
     * @return array{
     *     email: array{sent: int, failed: int, pending: int, skipped: int},
     *     whatsapp: array{sent: int, failed: int, pending: int, skipped: int}
     * }
     */
    public function deliveryStats(): array
    {
        return MassNotificationRecipientDelivery::summarizeForNotification($this->id);
    }

    public function isScheduledForFuture(): bool
    {
        return $this->date_programed !== null && $this->date_programed->isFuture();
    }
}
