<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckAffiliation extends Model
{
    protected $table = 'check_affiliations';

    protected $fillable = [
        'nro_afiliado','fecha_emision','codigo_tdec','tipo_plan','proveedor',
        'nro_vaucher',
        'cobertura',
        'tomador',
        'tipo_doc',
        'nro_doc',
        'afiliado',
        'tipo_doc_dos',
        'nro_doc_tres',
        'sexo',
        'fecha_nacimiento',
        'edad',
        'parentesco',
        'telefono',
        'correo',
        'estado',
        'ciudad',
        'direccion',
        'vigencia_desde',
        'vigencia_hasta',
        'agencia',
        'agente',
        'plan',
        'frecuencia_pago',
        'forma_pago',
        'monto_plan',
        'monto_recibido',
        'diferencia',
        'estatus_pago',
        'moneda',
        'referencia','fecha_pago','pagado_desde','pagado_hasta','estatus_renovacion','estatus_afiliado','dias_para_vencer','estado_del_plan',
        'pagado_ils_desde',
        'pagado_ils_hasta',
        'dia_vencimiento_ils',
        'estado_plan_ils',
        'fecha_egreso',
        'observaciones',
        'plan_id',
        'coverage_id',
        'age_range_id',
        'fee',
        'agent_id',
        'agency_id',
        'total_persons',
        'owner_code',
        'status_migration',
    ];
}