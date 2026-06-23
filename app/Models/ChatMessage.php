<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
        'tool_name',
        'tool_call_id',
        'tool_arguments',
        'tool_result',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tool_arguments' => 'array',
            'tool_result' => 'array',
            'metadata' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
