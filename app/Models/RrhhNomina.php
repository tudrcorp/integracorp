<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RrhhNomina extends Model
{
    protected $table = 'rrhh_nominas';

    protected $fillable = [
        'anio',
        'periodo',
        'fecha_desde',
        'fecha_hasta',
        'tasa_bcv',
        'total_salarios',
        'total_descuentos',
        'total_asignaciones',
        'total_prestamos',
        'total_neto',
        'total_salarios_ves',
        'total_descuentos_ves',
        'total_asignaciones_ves',
        'total_prestamos_ves',
        'total_neto_ves',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'periodo' => 'integer',
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date',
            'tasa_bcv' => 'decimal:4',
            'total_salarios' => 'decimal:2',
            'total_descuentos' => 'decimal:2',
            'total_asignaciones' => 'decimal:2',
            'total_prestamos' => 'decimal:2',
            'total_neto' => 'decimal:2',
            'total_salarios_ves' => 'decimal:2',
            'total_descuentos_ves' => 'decimal:2',
            'total_asignaciones_ves' => 'decimal:2',
            'total_prestamos_ves' => 'decimal:2',
            'total_neto_ves' => 'decimal:2',
        ];
    }

    public function detalleNomina(): HasMany
    {
        return $this->hasMany(RrhhDetalleNomina::class, 'nomina_id');
    }

    public function periodoLabel(): string
    {
        if ($this->fecha_desde === null || $this->fecha_hasta === null) {
            return '—';
        }

        $rango = $this->fecha_desde->format('d/m/Y').' — '.$this->fecha_hasta->format('d/m/Y');

        if ($this->periodo !== null) {
            return sprintf('P%02d · %s', (int) $this->periodo, $rango);
        }

        return $rango;
    }
}
