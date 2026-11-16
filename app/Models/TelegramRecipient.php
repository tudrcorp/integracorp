<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelegramRecipientStatus;
use App\Enums\TelegramRecipientType;
use Database\Factories\TelegramRecipientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Destino de Telegram de las notificaciones internas: una persona del personal,
 * un contacto externo autorizado o un grupo aprobado.
 *
 * @property TelegramRecipientType $type
 * @property TelegramRecipientStatus $status
 */
class TelegramRecipient extends Model
{
    /** @use HasFactory<TelegramRecipientFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'status',
        'user_id',
        'name',
        'chat_id',
        'telegram_username',
        'email',
        'phone',
        'reason',
        'expires_at',
        'linked_at',
        'approved_by',
        'approved_at',
        'last_sent_at',
        'last_error',
        'last_error_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TelegramRecipientType::class,
            'status' => TelegramRecipientStatus::class,
            'expires_at' => 'datetime',
            'linked_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function linkTokens(): HasMany
    {
        return $this->hasMany(TelegramLinkToken::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /**
     * @param  Builder<TelegramRecipient>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', TelegramRecipientStatus::Active->value)->whereNotNull('chat_id');
    }

    /**
     * @param  Builder<TelegramRecipient>  $query
     */
    public function scopeGroups(Builder $query): void
    {
        $query->where('type', TelegramRecipientType::Group->value);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * ¿Se le puede enviar ahora mismo? Vinculado, activo, sin vencer y, si es
     * personal, con su usuario todavía activo y del dominio interno.
     */
    public function canReceive(): bool
    {
        if ($this->status !== TelegramRecipientStatus::Active || blank($this->chat_id) || $this->isExpired()) {
            return false;
        }

        if ($this->type !== TelegramRecipientType::User) {
            return true;
        }

        $user = $this->user;

        return $user instanceof User
            && strtoupper(trim((string) $user->status)) === 'ACTIVO'
            && self::isInternalEmail((string) $user->email);
    }

    public static function isInternalEmail(string $email): bool
    {
        $domain = (string) config('telegram.internal_email_domain', 'tudrencasa.com');

        return $domain !== '' && str_ends_with(mb_strtolower(trim($email)), '@'.$domain);
    }
}
