<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TdevReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class TdevReportProcessObservationAppender
{
    public static function append(TdevReport $record, string $newNote, string $userName): void
    {
        $newNote = HelpdeskNoteHtmlSanitizer::sanitize(trim($newNote));
        $merged = HelpdeskObservationAppender::mergeObservation((string) $record->observaciones_proceso, $newNote, $userName);
        if ($merged === (string) $record->observaciones_proceso) {
            return;
        }

        $record->observaciones_proceso = $merged;
        $record->save();

        Log::info('TDEV: observación de proceso registrada', [
            'tdev_report_id' => $record->getKey(),
            'vaucher' => $record->vaucher,
            'user_id' => Auth::id(),
            'user_name' => $userName,
        ]);
    }
}
