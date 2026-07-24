<?php

namespace App\Models;

use App\Support\Rrhh\RrhhValorCalculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RrhhAsignacion extends Model
{
    protected $table = 'rrhh_asignacions';

    protected $fillable = [
        'name',
        'description',
        'tipo_valor',
        'monto',
        'porcentaje',
        'aplicacion',
        'departamento_id',
        'colaborador_id',
        'cargo_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'porcentaje' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RrhhAsignacion $model): void {
            if ($model->aplicacion === 'departamento') {
                $model->colaborador_id = null;
                $model->cargo_id = null;
            }

            if ($model->aplicacion === 'colaborador') {
                $model->departamento_id = null;
                $model->cargo_id = null;
            }

            if ($model->tipo_valor === RrhhValorCalculo::TIPO_PORCENTAJE) {
                $model->monto = null;
            }

            if ($model->tipo_valor === RrhhValorCalculo::TIPO_MONTO) {
                $model->porcentaje = null;
            }
        });
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(RrhhDepartamento::class, 'departamento_id');
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RrhhColaborador::class, 'colaborador_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(RrhhCargo::class, 'cargo_id');
    }

    public function destinoLabel(): string
    {
        return match ($this->aplicacion) {
            'departamento' => (string) ($this->departamento?->description ?? '—'),
            'colaborador' => (string) ($this->colaborador?->fullName ?? '—'),
            default => (string) ($this->cargo?->description ?? '—'),
        };
    }

    public function valorLabel(): string
    {
        return RrhhValorCalculo::valorLabel($this->tipo_valor, $this->monto, $this->porcentaje);
    }

    public function calcularSobreSueldoBase(float $sueldoBase): float
    {
        return RrhhValorCalculo::calcular($this->tipo_valor, $this->monto, $this->porcentaje, $sueldoBase);
    }
}
