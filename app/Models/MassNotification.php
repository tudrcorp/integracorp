<?php

namespace App\Models;

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
        'channels',
        'type',
    ];

    protected $casts = [
        'channels' => 'array',
        'date_programed' => 'datetime',
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
}
