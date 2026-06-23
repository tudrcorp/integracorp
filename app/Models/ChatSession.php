<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatSession extends Model
{
    public const PUBLIC_CHAT_COOKIE = 'integracorp_public_chat_token';

    protected $fillable = [
        'public_token',
        'status',
        'current_state',
        'detected_intent',
        'handoff_requested',
        'handoff_reason',
        'context_summary',
        'ip_address',
        'user_agent',
        'metadata',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'handoff_requested' => 'boolean',
            'metadata' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id')->orderBy('id');
    }

    public static function startPublic(?string $ipAddress, ?string $userAgent): self
    {
        return self::query()->create([
            'public_token' => (string) Str::ulid(),
            'status' => 'active',
            'current_state' => 'saludo',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'last_message_at' => now(),
        ]);
    }

    public static function findActiveByPublicToken(string $publicToken): ?self
    {
        if ($publicToken === '') {
            return null;
        }

        return self::query()
            ->where('public_token', $publicToken)
            ->whereIn('status', ['active', 'handoff'])
            ->first();
    }
}
