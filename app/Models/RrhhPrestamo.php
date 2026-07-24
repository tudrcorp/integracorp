<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RrhhPrestamo extends Model
{
    protected $table = 'rrhh_prestamos';

    protected $fillable = [
        'colaborador_id',
        'descripcion',
        'monto',
        'interes',
        'nro_cuotas',
        'nro_cuota_cancelada',
        'monto_cuota',
        'saldo',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'interes' => 'decimal:2',
            'monto_cuota' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RrhhColaborador::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RrhhDetallePrestamo::class, 'prestamo_id');
    }
}
