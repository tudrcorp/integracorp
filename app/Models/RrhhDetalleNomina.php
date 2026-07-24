<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RrhhDetalleNomina extends Model
{
    protected $table = 'rrhh_detalle_nominas';

    protected $fillable = [
        'colaborador_id',
        'colaborador_nombre',
        'colaborador_cedula',
        'nomina_id',
        'cargo_id',
        'cargo_nombre',
        'departamento_id',
        'departamento_nombre',
        'salario',
        'salario_ves',
        'monto_descuento',
        'monto_descuento_ves',
        'monto_bono',
        'monto_bono_ves',
        'monto_prestamo',
        'monto_prestamo_ves',
        'nro_cuota_cancelada',
        'monto_otros',
        'monto_total',
        'monto_total_ves',
        'detalle_asignaciones',
        'detalle_descuentos',
        'detalle_prestamos',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'salario' => 'decimal:2',
            'salario_ves' => 'decimal:2',
            'monto_descuento' => 'decimal:2',
            'monto_descuento_ves' => 'decimal:2',
            'monto_bono' => 'decimal:2',
            'monto_bono_ves' => 'decimal:2',
            'monto_prestamo' => 'decimal:2',
            'monto_prestamo_ves' => 'decimal:2',
            'monto_otros' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'monto_total_ves' => 'decimal:2',
            'detalle_asignaciones' => 'array',
            'detalle_descuentos' => 'array',
            'detalle_prestamos' => 'array',
        ];
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(RrhhNomina::class, 'nomina_id');
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RrhhColaborador::class, 'colaborador_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(RrhhCargo::class, 'cargo_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(RrhhDepartamento::class, 'departamento_id');
    }

    public function nombreColaborador(): string
    {
        if (filled($this->colaborador_nombre)) {
            return (string) $this->colaborador_nombre;
        }

        return (string) ($this->colaborador?->fullName ?: '—');
    }

    public function cedulaColaborador(): string
    {
        if (filled($this->colaborador_cedula)) {
            return (string) $this->colaborador_cedula;
        }

        return (string) ($this->colaborador?->cedula ?: 'Sin cédula');
    }

    public function nombreDepartamento(): string
    {
        if (filled($this->departamento_nombre)) {
            return (string) $this->departamento_nombre;
        }

        return (string) (
            $this->colaborador?->departamento?->description
            ?: $this->departamento?->description
            ?: '—'
        );
    }

    public function nombreCargo(): string
    {
        if (filled($this->cargo_nombre)) {
            return (string) $this->cargo_nombre;
        }

        return (string) (
            $this->colaborador?->cargo?->description
            ?: $this->cargo?->description
            ?: '—'
        );
    }

    public function montoVes(string $usdAttribute, string $vesAttribute, ?float $tasaBcv = null): float
    {
        $ves = (float) ($this->{$vesAttribute} ?? 0);

        if ($ves > 0) {
            return $ves;
        }

        $tasa = $tasaBcv ?? (float) ($this->nomina?->tasa_bcv ?? 0);

        if ($tasa <= 0) {
            return 0.0;
        }

        return round((float) ($this->{$usdAttribute} ?? 0) * $tasa, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     */
    public function conceptosLabel(?array $items, string $separator = ' · '): string
    {
        if ($items === null || $items === []) {
            return 'Sin conceptos';
        }

        return collect($items)
            ->map(function (array $item): string {
                $nombre = (string) ($item['name'] ?? $item['descripcion'] ?? 'Concepto');
                $monto = number_format((float) ($item['monto_calculado'] ?? $item['monto_cuota'] ?? 0), 2, '.', ',');
                $extra = '';

                if (($item['tipo_valor'] ?? null) === 'porcentaje') {
                    $extra = ' ('.number_format((float) ($item['valor_referencia'] ?? 0), 2, '.', '').'%)';
                }

                if (isset($item['aplicacion'])) {
                    $extra .= ' ['.$item['aplicacion'].']';
                }

                return $nombre.$extra.': USD$ '.$monto;
            })
            ->implode($separator);
    }
}
