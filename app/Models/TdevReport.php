<?php

namespace App\Models;

use App\Casts\TdevFormaPagoCast;
use App\Casts\TdevStatusComisionCast;
use App\Casts\TdevStatusPagoCast;
use App\Casts\TdevStatusVaucherCast;
use Illuminate\Database\Eloquent\Model;

class TdevReport extends Model
{
    protected $table = 'tdev_reports';

    protected $fillable = [
        'mes',
        'fecha',
        'vaucher',
        'agencia',
        'agente_emisor',
        'nivel',
        'salida',
        'regreso',
        'pasajero',
        'nro_documento',
        'categoria_del_plan',
        'descripcion_del_plan',
        'estatus_vaucher',
        'cupon_de_descuento',
        'cupon_comision',
        'cupon_promocion',
        'porcentaje_cupon',
        'precio_upgrade',
        'monto_pvp_precio_de_venta',
        'forma_pago',
        'comprobante_pago_path',
        'entidad_bancaria_receptora',
        'estatus_pago',
        'referencia_bancaria_pago_vaucher_credito',
        'tasa_bcv',
        'monto_abonado_en_cuenta_vaucher_credito',
        'fecha_pago_vaucher_credito',
        'dias_transcurridos',
        'porcentaje_comision',
        'monto_comision',
        'estatus_comision',
        'fecha_pago_comision',
        'formas_pago_comision',
        'referencia_bancaria_comision',
        'relacion_comision',
        'observaciones',
        'observaciones_proceso',
        'comision_agencia',
        'comision_agente',
        'comision_subagente',
        'neto_del_servicio',
        'utilidad_tdev',
        'status_report',
    ];

    protected function casts(): array
    {
        return [
            'estatus_vaucher' => TdevStatusVaucherCast::class,
            'estatus_comision' => TdevStatusComisionCast::class,
            'estatus_pago' => TdevStatusPagoCast::class,
            'forma_pago' => TdevFormaPagoCast::class,
        ];
    }
}
