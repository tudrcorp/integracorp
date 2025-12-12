<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $fillable = [
        'name',
            'status_convenio',
            'tipo_clinica',
            'tipo_servicio',
            'city_id',
            'state_id',
            'clasificacion',
            'horario',
            'status_sistema',
            'rif',
            'razon_social',
            'local_phone',
            'personal_phone',
            'correo_principal',
            'afiliacion_proveedor',
            'observaciones',
            'ubicacion_principal',
            'convenio_pago',
            'tiempo_credito',
            'auditado',
            'fecha_auditoria',
            'auditor',
            'urgen_care',
            'consulta_aps',
            'amd',
            'laboratorio_centro',
            'laboratorio_domicilio',
            'rx_centro',
            'rx_domicilio',
            'eco_abdominal_centro',
            'eco_abdominal_domicilio',
            'electrocardiograma_centro',
            'electrocardiograma_domicilio',
            'mamografia',
            'tomografo',
            'resonancia',
            'encologogia',
            'equipos_especiales_oftalmologia',
            'radioterapia_intraoperatoria',
            'quirofanos',
            'uci_uten',
            'neonatal',
            'ambulancias',
            'odontologia',
            'oftalmologia',
            'densitometria_osea',
            'dialisis',
            'otras_unidades_especiales',
            'otros_servicios',
            'created_by',
            'updated_by'
        
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function supplierRedGlobals()
    {
        return $this->hasMany(SupplierRedGlobal::class);
    }

    public function supplierObservacions()
    {
        return $this->hasMany(SupplierObservacion::class);
    }

    public function supplierContactPrincipals()
    {
        return $this->hasMany(SupplierContactPrincipal::class);
    }
}