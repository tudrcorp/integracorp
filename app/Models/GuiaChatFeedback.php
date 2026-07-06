<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\GuiaChat\GuiaChatFeedbackType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuiaChatFeedback extends Model
{
    protected $table = 'guia_chat_feedbacks';

    protected $fillable = [
        'type',
        'message',
        'reporter_first_name',
        'reporter_last_name',
        'chat_session_id',
        'public_token',
        'ip_address',
        'user_agent',
    ];

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function typeEnum(): ?GuiaChatFeedbackType
    {
        return GuiaChatFeedbackType::tryFromString($this->type);
    }

    public function reporterFullName(): ?string
    {
        $firstName = trim((string) $this->reporter_first_name);
        $lastName = trim((string) $this->reporter_last_name);

        if ($firstName === '' && $lastName === '') {
            return null;
        }

        return trim($firstName.' '.$lastName);
    }

    public function requiresReporterName(): bool
    {
        return $this->typeEnum()?->requiresReporterName() ?? false;
    }
}
