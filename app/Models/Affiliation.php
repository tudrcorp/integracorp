<?php

namespace App\Models;

use App\Mail\CertificateEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\SendTarjetaAfiliado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Jobs\SendNotificacionAfiliacionIndividual;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Affiliation extends Model
{
    protected $table = 'affiliations';

    protected $fillable = [
        'code',
        'agent_id',
        'code_agency',
        'owner_code',
        'owner_agent',
        'plan_id',
        
        /** Datos del pagador */
        'full_name_payer',
        'nro_identificacion_payer',
        'phone_payer',
        'email_payer',
        'relationship_payer',
        
        /** Datos del titular */
        'full_name_ti',
        'nro_identificacion_ti',
        'sex_ti',
        'age',
        'birth_date_ti',
        'adress_ti',
        'city_id_ti',
        'state_id_ti',
        'country_id_ti',
        'region_ti',
        'phone_ti',
        'email_ti',

        
        'cuestion_1',
        'cuestion_2',
        'cuestion_3',
        'cuestion_4',
        'cuestion_5',
        'cuestion_6',
        'cuestion_7',
        'cuestion_8',
        'cuestion_9',
        'cuestion_10',
        'cuestion_11',
        'cuestion_12',
        'cuestion_13',
        'cuestion_14',
        'cuestion_15',
        'cuestion_16',
        'observations_cuestions',
        
        'full_name_applicant',
        'signature_applicant',
        'nro_identificacion_applicant',
        'full_name_agent',
        'signature_agent',
        'signature_ti',
        'code_agent',
        'date_today',
        'created_by',
        'status',
        'individual_quote_id',
        'document',
        'observations_payment',
        'fee_anual',

        //despues de afiliar el poago
        'payment_frequency',
        'coverage_id',
        'activated_at',
        'family_members',
        'code_individual_quote',
        'total_amount',
        'observations',
        'feedback',
        'feedback_dos',

        //...Unidad de Negocio y linea de servicio
        'business_unit_id',
        'business_line_id',
        'ownerAccountManagers',

        //PROVEEDORRES DE SERVICIOS
        'service_providers',

        //...Fecha de Vigencia de la afiliacion
        'effective_date',

        //...Aliado de Servicio NIVEL 1
        'aliado_1_name',
        'date_init_aliado_1',
        'date_end_aliado_1',
        'vaucher_aliado_1',
        
    ];

    /**
     * Get the user that owns the Agent
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function accountManager()
    {
        return $this->hasOne(User::class, 'id', 'ownerAccountManagers');
    }

    protected $casts = [
        'upload_documents' => 'array',
        'service_providers' => 'array',
    ];

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id_ti', 'id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id_ti', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id_ti', 'id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }


    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'code_agency', 'code');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }

    public function individual_quote()
    {
        return $this->belongsTo(IndividualQuote::class);
    }

    public function paid_memberships()
    {
        return $this->hasMany(PaidMembership::class);
    }

    public function status_log_affiliations()
    {
        return $this->hasMany(StatusLogAffiliation::class);
    }

    public function documents()
    {
        return $this->hasMany(AffiliationIndividualDocument::class);
    }

    public function sendTarjetaAfiliacion($details)
    {
        /**
         * JOB
         */
        SendTarjetaAfiliado::dispatch($details);
    }

    public function affiliationIndividualPlans(): HasMany
    {
        return $this->hasMany(AfilliationIndividualPlan::class);
    }

    public function businessUnit(): HasOne
    {
        return $this->hasOne(BusinessUnit::class, 'id', 'business_unit_id');
    }

    public function businessLine(): HasOne
    {
        return $this->hasOne(BusinessLine::class, 'id', 'business_line_id');
    }

    /**
     * Funcion para enviar el certificado de afiliacion
     * cuando se registra mas de un afiliado
     * 
     * @param [type] $record
     * @return void 
     * @version 1.0
     */
    public function sendCertificate($record, $afiliates)
    {
        // dd($record, $record->plan->benefitPlans->toArray());
        try {

            $pagador = [
                'name'                  => $record->full_name_payer,
                'code'                  => $record->code,
                'tarifa_anual'          => $record->fee_anual,
                'plan'                  => $record->plan->description,
                'plan_id'               => $record->plan_id,
                'frecuencia_pago'       => $record->payment_frequency,
                'cobertura'            => isset($record->coverage_id) ? $record->coverage->price : 0,
                'fecha_afiliacion'      => $record->created_at->format('d/m/Y'),
                'tarifa_periodo'        => $record->total_amount,
            ];

            //Validamos si la afiliacionn la realizo un agente o una agencia
            if (isset($record->agent)) {
                $pagador['agente_agencia'] = $record->agent->name;
            }else{
                $pagador['agente_agencia'] = isset($record->agency->name_corporative) ? $record->agency->name_corporative : 'TuDrEnCasa';
            }

            
            //Nombre del PDF
            $name_pdf = 'CER-' . $record->code . '.pdf';
            
            //Beneficios asociados al plan
            $beneficios = $record->plan->benefitPlans->toArray();
            $beneficios_table = [];
            for ($i = 0; $i < count($beneficios); $i++) {
                $beneficios_table[$i] = $beneficios[$i]['description'];
            }
            // dd($beneficios_table, $pagador,$afiliates);

            SendNotificacionAfiliacionIndividual::dispatch($pagador, $beneficios_table, $name_pdf, $afiliates, Auth::user())->onQueue('certificates');
            //code...

        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            Notification::make()
                ->title('Falla al enviar el certificado')
                ->danger();
                
            //throw $th;
        }
    }

    /**
     * Funcion para enviar el certificado de afiliacion
     * cuando se registra un afiliado
     * 
     * @param [type] $record
     * @return void 
     * @version 1.0
     */
    public function sendCertificateOnlyHolder($record, $afiliates)
    {

        try {

            $pagador = [
                'name'                  => $record->full_name_payer,
                'code'                  => $record->code,
                'tarifa_anual'          => $record->fee_anual,
                'plan'                  => $record->plan->description,
                'plan_id'               => $record->plan_id,
                'frecuencia_pago'       => $record->payment_frequency,
                'cobertura'            => isset($record->coverage_id) ? $record->coverage->price : 0,
                'fecha_afiliacion'      => $record->created_at->format('d/m/Y'),
                'tarifa_periodo'        => $record->total_amount,
            ];

            //Validamos si la afiliacionn la realizo un agente o una agencia
            if (isset($record->agent)) {
                $pagador['agente_agencia'] = $record->agent->name;
            } else {
                $pagador['agente_agencia'] = isset($record->agency->name_corporative) ? $record->agency->name_corporative : 'TuDrEnCasa';
            }


            //Nombre del PDF
            $name_pdf = 'CER-' . $record->code . '.pdf';

            //Beneficios asociados al plan
            $beneficios = $record->plan->benefitPlans->toArray();
            $beneficios_table = [];
            
            for ($i = 0; $i < count($beneficios); $i++) {
                $beneficios_table[$i] = $beneficios[$i]['description'];
            }

            // dd($beneficios_table, $pagador,$afiliates);

            SendNotificacionAfiliacionIndividual::dispatch($pagador, $beneficios_table, $name_pdf, $afiliates, Auth::user())->onQueue('certificates');
            //code...
        } catch (\Throwable $th) {
            dd($th);
            //throw $th;
        }
    }

    
    
}