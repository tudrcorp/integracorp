<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhiteCompanyPlanLabel extends Model
{
    /** @use HasFactory<\Database\Factories\WhiteCompanyPlanLabelFactory> */
    use HasFactory;

    protected $fillable = [
        'white_company_id',
        'plan_id',
        'display_name',
        'short_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WhiteCompany, $this>
     */
    public function whiteCompany(): BelongsTo
    {
        return $this->belongsTo(WhiteCompany::class);
    }

    public static function syncForPlan(WhiteCompany $company, int $planId, mixed $displayName, mixed $shortLabel): void
    {
        $name = self::normalizeText($displayName);
        $short = self::normalizeText($shortLabel);

        if ($name === null && $short === null) {
            $company->planLabels()
                ->where('plan_id', $planId)
                ->delete();

            return;
        }

        $company->planLabels()->updateOrCreate(
            ['plan_id' => $planId],
            [
                'display_name' => $name,
                'short_label' => $short,
            ],
        );
    }

    private static function normalizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
