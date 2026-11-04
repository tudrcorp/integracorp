<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota de seguimiento de una encuesta de Quality Control. No se edita ni se borra.
 */
class QualityControlSurveyNote extends Model
{
    public const MIN_LENGTH = 10;

    public const MAX_LENGTH = 2000;

    protected $fillable = [
        'quality_control_survey_id',
        'note',
        'created_by_user_id',
        'created_by_name',
    ];

    /**
     * @return BelongsTo<QualityControlSurvey, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(QualityControlSurvey::class, 'quality_control_survey_id');
    }
}
