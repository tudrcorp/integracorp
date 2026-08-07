<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class HelpdeskObservationAppender
{
    public static function append(
        HelpDesk $record,
        string $newNote,
        string $userName,
        ?Carbon $at = null,
        ?User $user = null,
        string $eventType = HelpdeskEventRecorder::TYPE_NOTE,
        ?array $meta = null,
    ): void {
        $user ??= auth()->user() instanceof User ? auth()->user() : null;
        $newNote = HelpdeskNoteHtmlSanitizer::sanitize(trim($newNote));
        $merged = self::mergeObservation((string) $record->observation, $newNote, $userName, $at);

        if ($merged === (string) $record->observation) {
            return;
        }

        $tz = (string) config('app.timezone');
        $moment = $at ?? Carbon::now($tz);

        $record->observation = $merged;
        $record->updated_by = $userName;

        if (Schema::hasColumn('help_desks', 'latest_note_at')
            && Schema::hasColumn('help_desks', 'latest_note_by')) {
            $record->latest_note_at = $moment;
            $record->latest_note_by = $userName;

            if (Schema::hasColumn('help_desks', 'latest_note_by_user_id')) {
                $record->latest_note_by_user_id = $user?->getAuthIdentifier();
            }
        }

        if (Schema::hasColumn('help_desks', 'first_responded_at')) {
            HelpdeskSla::markFirstResponseIfNeeded($record, $user);
        }

        $record->save();

        if (Schema::hasTable('help_desk_events')) {
            HelpdeskEventRecorder::record(
                ticket: $record,
                type: $eventType,
                bodyHtml: $newNote,
                user: $user,
                meta: $meta,
                occurredAt: $moment,
            );
        }
    }

    /**
     * @param  non-empty-string  $userName
     */
    public static function mergeObservation(string $existingRaw, string $newNote, string $userName, ?Carbon $at = null): string
    {
        $newNote = trim($newNote);
        if ($newNote === '' || self::isEffectivelyEmptyNote($newNote)) {
            return $existingRaw;
        }

        $tz = (string) config('app.timezone');
        $moment = $at ?? Carbon::now($tz);
        $header = '['.$moment->timezone($tz)->format('d/m/Y H:i').' · '.$userName.']'."\n";
        $block = $header.$newNote;
        $existing = trim($existingRaw);

        return $existing === '' ? $block : $existing."\n\n".$block;
    }

    private static function isEffectivelyEmptyNote(string $html): bool
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '';
    }
}
