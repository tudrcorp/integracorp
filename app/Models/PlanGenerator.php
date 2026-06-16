<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanGenerator extends Model
{
    protected $fillable = [
        'name',
        'control_number',
        'client_data',
        'issued_at',
        'agent_name',
        'population_summary',
        'quotation_page_count',
        'plan_page_number',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'quotation_page_count' => 'integer',
            'plan_page_number' => 'integer',
        ];
    }

    public function columns(): HasMany
    {
        return $this->hasMany(PlanGeneratorColumn::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PlanGeneratorRow::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function rateRows(): HasMany
    {
        return $this->hasMany(PlanGeneratorRateRow::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function quotationPages(): HasMany
    {
        return $this->hasMany(PlanGeneratorQuotationPage::class)
            ->orderBy('sort_order')
            ->orderBy('page_number');
    }
}
