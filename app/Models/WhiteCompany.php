<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class WhiteCompany extends Model
{
    protected $table = 'white_companies';

    protected $fillable = [
        'name',
        'logo',
        'carnet_template_image',
        'brand_primary_color',
        'certificate_signature',
        'rif',
        'email',
        'phone',
        'address',
        'city_id',
        'state_id',
        'country_id',
        'updated_by',
        'created_by',
        'assigned_credit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_credit' => 'decimal:2',
        ];
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    /**
     * @return HasMany<WhiteCompanyPlanDocument, $this>
     */
    public function planDocuments(): HasMany
    {
        return $this->hasMany(WhiteCompanyPlanDocument::class);
    }

    /**
     * @return HasMany<WhiteCompanyPlanLabel, $this>
     */
    public function planLabels(): HasMany
    {
        return $this->hasMany(WhiteCompanyPlanLabel::class);
    }

    /**
     * @return HasMany<WhiteCompanyFee, $this>
     */
    public function negotiatedFees(): HasMany
    {
        return $this->hasMany(WhiteCompanyFee::class);
    }

    /**
     * Planes habilitados para esta aliada. Es el paso previo a pactar netas:
     * la matriz de negociación solo ofrece tarifas de estos planes.
     */
    public function assignedPlans(): HasMany
    {
        return $this->hasMany(WhiteCompanyPlan::class);
    }

    public function creditReconciliations(): HasMany
    {
        return $this->hasMany(CreditReconciliation::class);
    }

    public function logoAbsolutePath(): ?string
    {
        return self::publicDiskAbsolutePath($this->logo);
    }

    public function carnetTemplateImageAbsolutePath(): ?string
    {
        return self::publicDiskAbsolutePath($this->carnet_template_image);
    }

    public function certificateSignatureAbsolutePath(): ?string
    {
        return self::publicDiskAbsolutePath($this->certificate_signature);
    }

    public function condicionadoPathForPlan(int $planId): ?string
    {
        $document = $this->relationLoaded('planDocuments')
            ? $this->planDocuments->first(
                fn (WhiteCompanyPlanDocument $document): bool => (int) $document->plan_id === $planId
                    && $document->kind === WhiteCompanyPlanDocument::KIND_CONDICIONADO
            )
            : $this->planDocuments()
                ->where('plan_id', $planId)
                ->where('kind', WhiteCompanyPlanDocument::KIND_CONDICIONADO)
                ->first();

        return $document?->path;
    }

    public function planLabelForPlan(int $planId): ?WhiteCompanyPlanLabel
    {
        if ($this->relationLoaded('planLabels')) {
            $label = $this->planLabels->first(
                fn (WhiteCompanyPlanLabel $label): bool => (int) $label->plan_id === $planId
            );

            return $label instanceof WhiteCompanyPlanLabel ? $label : null;
        }

        return $this->planLabels()->where('plan_id', $planId)->first();
    }

    private static function publicDiskAbsolutePath(mixed $relativePath): ?string
    {
        if (! is_string($relativePath) || $relativePath === '') {
            return null;
        }

        $path = Storage::disk('public')->path($relativePath);

        return is_file($path) ? $path : null;
    }

    public function consumedAssignedCredit(?int $exceptId = null): float
    {
        if ($this->relationLoaded('creditReconciliations')) {
            return (float) $this->creditReconciliations
                ->when($exceptId !== null, fn ($records) => $records->reject(
                    fn (CreditReconciliation $record): bool => (int) $record->id === $exceptId
                ))
                ->sum('total_to_pay');
        }

        return (float) $this->creditReconciliations()
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->sum('total_to_pay');
    }

    public function remainingAssignedCredit(?int $exceptId = null): float
    {
        return (float) ($this->assigned_credit ?? 0) - $this->consumedAssignedCredit($exceptId);
    }
}
