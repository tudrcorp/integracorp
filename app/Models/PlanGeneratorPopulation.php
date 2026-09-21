<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila del padrón importado para la pre-afiliación corporativa de un plan
 * generado. Al crear la afiliación se copia a `affiliate_corporates`, igual que
 * `corporate_quote_data` en el flujo de cotizaciones corporativas.
 */
class PlanGeneratorPopulation extends Model
{
    protected $fillable = [
        'plan_generator_id',
        'import_id',
        'last_name',
        'first_name',
        'nro_identificacion',
        'birth_date',
        'age',
        'sex',
        'phone',
        'email',
        'condition_medical',
        'initial_date',
        'position_company',
        'address',
        'full_name_emergency',
        'phone_emergency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_generator_id' => 'integer',
            'import_id' => 'integer',
        ];
    }

    public function planGenerator(): BelongsTo
    {
        return $this->belongsTo(PlanGenerator::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_id');
    }
}
