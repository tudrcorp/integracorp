<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\Cast;

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
            'densitometria_osea',
            'descripcion_densitometria_osea',
            'dialisis',
            'descripcion_dialisis',
            'electrocardiograma_centro',
            'descripcion_electrocardiograma_centro',
            'equipos_especiales_oftalmologia',
            'descripcion_equipos_especiales_oftalmologia',
            'mamografia',
            'descripcion_mamografia',
            'quirofanos',
            'descripcion_quirofanos',
            'radioterapia_intraoperatoria',
            'descripcion_radioterapia_intraoperatoria',
            'resonancia',
            'descripcion_resonancia',
            'tomografo',
            'descripcion_tomografo',
            'uci_pediatrica',
            'descripcion_uci_pediatrica',
            'uci_adulto',
            'descripcion_uci_adulto',
            'estacionamiento_propio',
            'descripcion_estacionamiento_propio',
            'ascensor',
            'descripcion_ascensor',
            'robotica',
            'descripcion_robotica',
            'otras_unidades_especiales',
            'otros_servicios',
            'created_by',
            'updated_by',
            'state_services',
            'supplier_clasificacion_id',
            'type_service',
        
    ];

    protected $casts = [
        'state_services' => 'array', 
        'type_service' => 'array'
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

    public function supplierEstatusSistemas()
    {
        return $this->hasMany(SupplierEstatusSistema::class);
    }

    public function supplierStatusConvenios()
    {
        return $this->hasMany(SupplierStatusConvenio::class);
    }

    public function supplierTipoClinicas()
    {
        return $this->hasMany(SupplierTipoClinica::class);
    }

    public function SupplierZonaCoberturas()
    {
        return $this->hasMany(SupplierZonaCobertura::class);
    }

    public function SupplierClasificacion()
    {
        return $this->belongsTo(SupplierClasificacion::class);
    }

}