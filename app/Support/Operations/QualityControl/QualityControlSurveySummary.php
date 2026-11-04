<?php

declare(strict_types=1);

namespace App\Support\Operations\QualityControl;

use App\Enums\QualityControlAffiliateType;
use App\Enums\QualityControlRating;
use App\Models\QualityControlSurvey;
use Illuminate\Support\Carbon;

/**
 * Cifras del encabezado de Quality Control, en una sola consulta agregada.
 */
final class QualityControlSurveySummary
{
    /**
     * @return array{total: int, satisfied: int, neutral: int, dissatisfied: int, last_30_days: int, satisfaction_rate: int|null}
     */
    public static function forType(QualityControlAffiliateType $type): array
    {
        $row = QualityControlSurvey::query()
            ->ofType($type)
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN rating_overall IN (?, ?) THEN 1 ELSE 0 END) AS satisfied', [
                QualityControlRating::TotallySatisfied->value,
                QualityControlRating::Satisfied->value,
            ])
            ->selectRaw('SUM(CASE WHEN rating_overall = ? THEN 1 ELSE 0 END) AS neutral', [QualityControlRating::Neutral->value])
            ->selectRaw('SUM(CASE WHEN rating_overall = ? THEN 1 ELSE 0 END) AS dissatisfied', [QualityControlRating::Dissatisfied->value])
            ->selectRaw('SUM(CASE WHEN responded_at >= ? THEN 1 ELSE 0 END) AS last_30_days', [Carbon::today()->subDays(30)->format('Y-m-d H:i:s')])
            ->selectRaw('SUM(CASE WHEN rating_overall IS NOT NULL THEN 1 ELSE 0 END) AS rated')
            ->first();

        $satisfied = (int) ($row->satisfied ?? 0);
        $rated = (int) ($row->rated ?? 0);

        return [
            'total' => (int) ($row->total ?? 0),
            'satisfied' => $satisfied,
            'neutral' => (int) ($row->neutral ?? 0),
            'dissatisfied' => (int) ($row->dissatisfied ?? 0),
            'last_30_days' => (int) ($row->last_30_days ?? 0),
            'satisfaction_rate' => $rated > 0 ? (int) round($satisfied * 100 / $rated) : null,
        ];
    }
}
