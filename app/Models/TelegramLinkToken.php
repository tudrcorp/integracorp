<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enlace de vinculación de un solo uso. Solo se guarda el hash del código.
 */
class TelegramLinkToken extends Model
{
    protected $fillable = [
        'telegram_recipient_id',
        'token_hash',
        'short_code_hash',
        'expires_at',
        'used_at',
        'used_chat_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TelegramRecipient::class, 'telegram_recipient_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
