<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckAgentAgency extends Model
{
    protected $table = 'check_agent_agencies';

    protected $fillable = [
        'codificacion_agente',
        'codigo_agente',
        'nombre_agencia_agente',
        'nombre_representante',
        'nro_identificacion',
        'fecha_nacimiento',
        'fecha_ingreso',
        'estatus',
        'email',
        'telefono',
        'usuario_instagram',
        'pais',
        'estado',
        'ciudad',
        'tdec',
        'tdev',
        'tipo_agente',
        'agente_supervisor',
        'agencia_master',
        'status_migration',
        'agency_id'
    ];
}