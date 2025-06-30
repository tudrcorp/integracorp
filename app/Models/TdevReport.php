<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TdevReport extends Model
{
    protected $table = 'tdev_reports';

    protected $fillable = [
        'fecha',
        'vaucher',
        'agencia',
        'agente',
        'subagente',
        'salida',
        'regreso',
        'fecha_anulacion',
        'pasajero',
        'nacionalidad',
        'tipo_documento',
        'nro_documento',
        'categoria_del_plan',
        'descripcion_del_plan',
        'origen_del_viaje',
        'destino',
        'nro_dias_de_servicio',
        'edad',
        'estatus_del_vaucher',
        'referencia',
        'plan_familiar',
        'descuento',
        'impuesto',
        'precio_upgrade',
        'precio_de_venta',
        
        //Campos agregados al reporte
        //-----------------------------------------------------------
        'total_precio_venta',
        'fecha_pago_vaucher',
        'forma_de_pago',
        'entidad_bancaria_receptora',
        'referencia_bancaria',
        'tasa_pago',
        'monto_abonado_en_cuenta',
        'estatus_pago',
        'dias_emision',
        'porcen_comision',
        'comision_agencia',
        'comision_agente',
        'comision_subagente',
        'monto_comision',
        'estatus_comision',
        'fecha_pago_comision',
        'referencia_bancaria_comision',
        'relacion_comision',
        'observaciones',
        'neto_del_servicio',
        'utilidad_tdev',
        'status_report'
        //------------------------------------------------------------
    ];

    
}