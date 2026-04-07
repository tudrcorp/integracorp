<?php

namespace App\Support;

use App\Models\HelpDesk;
use Illuminate\Support\Carbon;

final class HelpdeskObservationAppender
{
    public static function append(HelpDesk $record, string $newNote, string $userName): void
    {
        $merged = self::mergeObservation((string) $record->observation, $newNote, $userName);
        if ($merged === (string) $record->observation) {
            return;
        }

        $record->observation = $merged;
        $record->updated_by = $userName;
        $record->save();
    }

    /**
     * @param  non-empty-string  $userName
     */
    public static function mergeObservation(string $existingRaw, string $newNote, string $userName, ?Carbon $at = null): string
    {
        $newNote = trim($newNote);
        if ($newNote === '') {
            return $existingRaw;
        }

        $tz = (string) config('app.timezone');
        $moment = $at ?? Carbon::now($tz);
        $header = '['.$moment->timezone($tz)->format('d/m/Y H:i').' · '.$userName.']'."\n";
        $block = $header.$newNote;
        $existing = trim($existingRaw);

        return $existing === '' ? $block : $existing."\n\n".$block;
    }
}
