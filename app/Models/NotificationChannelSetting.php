<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Interruptor general de un canal (whatsapp, mail, telegram).
 */
class NotificationChannelSetting extends Model
{
    protected $fillable = [
        'channel',
        'is_enabled',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
