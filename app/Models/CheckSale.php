<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckSale extends Model
{
    protected $table = 'check_sales';

    protected $fillable = [
            'fecha',
            'agencia',
            'agente',
            'nro_factura',
            'codgio_afiliado',
            'cliente_afiliado',
            'contacto',
            'rif',
            'telefono',
            'email',
            'producto',
            'servicio',
            'cobertura',
            'poblacion',
            'enero',
            'febrero',
            'marzo',
            'abril',
            'mayo',
            'junio',
            'agosto',
            'septiembre',
            'octubre',
            'noviembre',
            'diciembre',
            'monto_pagado',
            'observaciones',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}