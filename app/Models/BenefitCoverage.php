<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BenefitCoverage extends Pivot
{
    protected $table = 'benefit_coverages';

    /**
     * Pivot desactiva el autoincremento por defecto, pero esta tabla sí tiene
     * un `id` propio. Sin esto, el id no vuelve poblado tras insertar y
     * cualquier sincronización que compare ids termina borrando lo que acaba
     * de escribir.
     */
    public $incrementing = true;

    protected $fillable = [
        'plan_id',
        'benefit_id',
        'coverage_id',
        'limit',
        'benefit_description',
        'coverage_price',
        'price',
    ];

    /**
     * `limit` es NULL-able a propósito: NULL significa que el beneficio no
     * tiene límite en esa cobertura, que no es lo mismo que un límite de 0.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
            'limit' => 'decimal:2',
        ];
    }

    /**
     * Summary of benefit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Benefit, BenefitCoverage>
     */
    public function benefit()
    {
        return $this->belongsTo(Benefit::class);
    }

    /**
     * Summary of coverage
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Coverage, BenefitCoverage>
     */
    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }
}
