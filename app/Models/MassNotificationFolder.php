<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MassNotificationFolder extends Model
{
    protected $table = 'mass_notification_folders';

    protected $fillable = [
        'name',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function massNotifications(): HasMany
    {
        return $this->hasMany(MassNotification::class, 'mass_notification_folder_id');
    }

    public static function defaultFolder(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }
}
