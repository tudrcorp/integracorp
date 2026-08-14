<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhiteCompanyPlanDocument extends Model
{
    /** @use HasFactory<\Database\Factories\WhiteCompanyPlanDocumentFactory> */
    use HasFactory;

    public const KIND_CONDICIONADO = 'condicionado';

    protected $fillable = [
        'white_company_id',
        'plan_id',
        'kind',
        'path',
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

    public static function syncCondicionado(WhiteCompany $company, int $planId, mixed $path): void
    {
        $normalized = is_array($path) ? ($path[0] ?? null) : $path;

        if (! is_string($normalized) || $normalized === '') {
            $company->planDocuments()
                ->where('plan_id', $planId)
                ->where('kind', self::KIND_CONDICIONADO)
                ->delete();

            return;
        }

        $company->planDocuments()->updateOrCreate(
            [
                'plan_id' => $planId,
                'kind' => self::KIND_CONDICIONADO,
            ],
            [
                'path' => $normalized,
            ],
        );
    }
}
