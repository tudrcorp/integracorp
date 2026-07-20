<?php

namespace App\Models;

use App\Support\Operations\OperationServiceOrderValidity;
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
        'descripcion_urgen_care',
        'consulta_aps',
        'descripcion_consulta_aps',
        'amd',
        'descripcion_amd',
        'laboratorio_centro',
        'descripcion_laboratorio_centro',
        'laboratorio_domicilio',
        'descripcion_laboratorio_domicilio',
        'rx_centro',
        'descripcion_rx_centro',
        'rx_domicilio',
        'descripcion_rx_domicilio',
        'eco_abdominal_centro',
        'descripcion_eco_abdominal_centro',
        'eco_abdominal_domicilio',
        'descripcion_eco_abdominal_domicilio',
        'densitometria_osea',
        'descripcion_densitometria_osea',
        'dialisis',
        'descripcion_dialisis',
        'electrocardiograma_centro',
        'descripcion_electrocardiograma_centro',
        'electrocardiograma_domicilio',
        'descripcion_electrocardiograma_domicilio',
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
        'oncologia',
        'descripcion_encologogia',
        'uci_uten',
        'descripcion_uci_uten',
        'neonatal',
        'descripcion_neonatal',
        'ambulancias',
        'descripcion_ambulancias',
        'odontologia',
        'descripcion_odontologia',
        'oftalmologia',
        'descripcion_oftalmologia',
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
        'descripcion_otras_unidades_especiales',
        'cirugia_general',
        'descripcion_cirugia_general',
        'medicina_interna',
        'descripcion_medicina_interna',
        'obstetricia_ginecologia',
        'descripcion_obstetricia_ginecologia',
        'pediatria',
        'descripcion_pediatria',
        'otorrinolaringologia',
        'descripcion_otorrinolaringologia',
        'traumatologia_ortopedia',
        'descripcion_traumatologia_ortopedia',
        'neumonologia',
        'descripcion_neumonologia',
        'gastroenterologia',
        'descripcion_gastroenterologia',
        'cardiocirugia',
        'descripcion_cardiocirugia',
        'cardiologia',
        'descripcion_cardiologia',
        'psiquiatria',
        'descripcion_psiquiatria',
        'anestesia_reanimacion',
        'descripcion_anestesia_reanimacion',
        'imagenologia_avanzada',
        'descripcion_imagenologia_avanzada',
        'unidad_uci',
        'descripcion_unidad_uci',
        'banco_sangre',
        'descripcion_banco_sangre',
        'nefrologia',
        'descripcion_nefrologia',
        'radioterapia',
        'descripcion_radioterapia',
        'quimioterapia',
        'descripcion_quimioterapia',
        'otros_servicios',
        'created_by',
        'updated_by',
        'state_services',
        'supplier_clasificacion_id',
        'type_service',
        'carta_acceptance',
        'documents',
        'local_beneficiary_name',
        'local_beneficiary_rif',
        'local_beneficiary_account_number',
        'local_beneficiary_account_bank',
        'local_beneficiary_account_type',
        'local_beneficiary_phone_pm',
        'local_beneficiary_account_number_mon_inter',
        'local_beneficiary_account_bank_mon_inter',
        'local_beneficiary_account_type_mon_inter',
        'extra_beneficiary_name',
        'extra_beneficiary_ci_rif',
        'extra_beneficiary_account_number',
        'extra_beneficiary_account_bank',
        'extra_beneficiary_account_type',
        'extra_beneficiary_route',
        'extra_beneficiary_swift',
        'extra_beneficiary_zelle',
        'extra_beneficiary_address',
        'gestion_integracorp',

    ];

    protected $casts = [
        'state_services' => 'array',
        'type_service' => 'array',
        'documents' => 'array',
        'urgen_care' => 'boolean',
        'consulta_aps' => 'boolean',
        'amd' => 'boolean',
        'laboratorio_centro' => 'boolean',
        'laboratorio_domicilio' => 'boolean',
        'rx_centro' => 'boolean',
        'rx_domicilio' => 'boolean',
        'eco_abdominal_centro' => 'boolean',
        'eco_abdominal_domicilio' => 'boolean',
        'densitometria_osea' => 'boolean',
        'dialisis' => 'boolean',
        'electrocardiograma_centro' => 'boolean',
        'electrocardiograma_domicilio' => 'boolean',
        'equipos_especiales_oftalmologia' => 'boolean',
        'mamografia' => 'boolean',
        'quirofanos' => 'boolean',
        'radioterapia_intraoperatoria' => 'boolean',
        'resonancia' => 'boolean',
        'tomografo' => 'boolean',
        'oncologia' => 'boolean',
        'uci_uten' => 'boolean',
        'neonatal' => 'boolean',
        'ambulancias' => 'boolean',
        'odontologia' => 'boolean',
        'oftalmologia' => 'boolean',
        'uci_pediatrica' => 'boolean',
        'uci_adulto' => 'boolean',
        'estacionamiento_propio' => 'boolean',
        'ascensor' => 'boolean',
        'robotica' => 'boolean',
        'otras_unidades_especiales' => 'boolean',
        'cirugia_general' => 'boolean',
        'medicina_interna' => 'boolean',
        'obstetricia_ginecologia' => 'boolean',
        'pediatria' => 'boolean',
        'otorrinolaringologia' => 'boolean',
        'traumatologia_ortopedia' => 'boolean',
        'neumonologia' => 'boolean',
        'gastroenterologia' => 'boolean',
        'cardiocirugia' => 'boolean',
        'cardiologia' => 'boolean',
        'psiquiatria' => 'boolean',
        'anestesia_reanimacion' => 'boolean',
        'imagenologia_avanzada' => 'boolean',
        'unidad_uci' => 'boolean',
        'banco_sangre' => 'boolean',
        'nefrologia' => 'boolean',
        'radioterapia' => 'boolean',
        'quimioterapia' => 'boolean',
        'gestion_integracorp' => 'boolean',
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
        return $this->hasMany(SupplierObservacion::class)->orderBy('created_at', 'desc');
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

    public function operationServiceOrders()
    {
        return $this->hasMany(OperationServiceOrder::class, 'supplier_id')
            ->orderByDesc('created_at');
    }

    public function finalizedOperationServiceOrders()
    {
        return $this->hasMany(OperationServiceOrder::class, 'supplier_id')
            ->whereIn('status', OperationServiceOrderValidity::closedStatuses())
            ->orderByDesc('created_at');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<User, $this>
     */
    public function integracorpUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'supplier_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<TelemedicineDoctor, $this>
     */
    public function telemedicineDoctors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TelemedicineDoctor::class, 'supplier_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<TelemedicinePatient, $this>
     */
    public function telemedicinePatients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TelemedicinePatient::class, 'supplier_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<TelemedicineCase, $this>
     */
    public function telemedicineCases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TelemedicineCase::class, 'supplier_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<TelemedicineHistoryPatient, $this>
     */
    public function telemedicineHistoryPatients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TelemedicineHistoryPatient::class, 'supplier_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<OperationCoordinationService, $this>
     */
    public function operationCoordinationServices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OperationCoordinationService::class, 'supplier_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<OperationServiceOrder, $this>
     */
    public function telemedicineOperationServiceOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OperationServiceOrder::class, 'telemedicine_supplier_id');
    }
}
