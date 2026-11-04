<?php

declare(strict_types=1);

namespace App\Support\Operations\QualityControl;

use App\Models\QualityControlSurvey;
use App\Models\QualityControlSurveyNote;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Notas de seguimiento de una encuesta: se agregan, nunca se editan ni se borran.
 */
final class QualityControlSurveyNotes
{
    public static function add(QualityControlSurvey $survey, string $note, ?User $user): QualityControlSurveyNote
    {
        $note = trim(preg_replace("/\r\n?/", "\n", $note) ?? '');
        $length = mb_strlen($note);

        if ($length < QualityControlSurveyNote::MIN_LENGTH || $length > QualityControlSurveyNote::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'note' => 'La nota debe tener entre '.QualityControlSurveyNote::MIN_LENGTH.' y '.QualityControlSurveyNote::MAX_LENGTH.' caracteres.',
            ]);
        }

        $created = DB::transaction(function () use ($survey, $note, $user): QualityControlSurveyNote {
            /** La encuesta pudo eliminarse entre abrir la ventana y guardar. */
            $locked = QualityControlSurvey::query()->lockForUpdate()->find($survey->getKey());

            if (! $locked instanceof QualityControlSurvey) {
                throw ValidationException::withMessages(['note' => 'La encuesta ya no existe: no se guardó la nota.']);
            }

            return QualityControlSurveyNote::query()->create([
                'quality_control_survey_id' => $locked->getKey(),
                'note' => $note,
                'created_by_user_id' => $user?->getKey(),
                'created_by_name' => filled($user?->name) ? (string) $user->name : 'SISTEMA',
            ]);
        });

        SecurityAudit::log('AUDIT_QUALITY_CONTROL_SURVEY_NOTE_ADDED', 'operations.quality-control.note', [
            'survey_id' => $survey->getKey(),
            'affiliate_type' => $survey->affiliate_type?->value,
            'note_id' => $created->getKey(),
            'length' => mb_strlen($note),
        ], $user);

        return $created;
    }
}
