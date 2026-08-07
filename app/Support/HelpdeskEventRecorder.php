<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;
use App\Models\HelpDeskEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

final class HelpdeskEventRecorder
{
    public const TYPE_NOTE = 'note';

    public const TYPE_STATUS_CHANGE = 'status_change';

    public const TYPE_PRIORITY_CHANGE = 'priority_change';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_CSAT = 'csat';

    public const TYPE_REOPEN = 'reopen';

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function record(
        HelpDesk $ticket,
        string $type,
        string $bodyHtml,
        ?User $user = null,
        ?array $meta = null,
        ?Carbon $occurredAt = null,
    ): HelpDeskEvent {
        $occurredAt ??= Carbon::now((string) config('app.timezone'));
        $user ??= auth()->user() instanceof User ? auth()->user() : null;

        return HelpDeskEvent::query()->create([
            'help_desk_id' => $ticket->getKey(),
            'user_id' => $user?->getAuthIdentifier(),
            'actor_name' => $user?->name,
            'type' => $type,
            'body_html' => HelpdeskNoteHtmlSanitizer::sanitize(trim($bodyHtml)),
            'meta' => $meta,
            'occurred_at' => $occurredAt,
        ]);
    }
}
