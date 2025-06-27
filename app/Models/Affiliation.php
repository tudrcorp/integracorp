<?php

namespace App\Models;

use App\Mail\CertificateEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\SendTarjetaAfiliado;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendNotificacionAfiliacionIndividual;

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
        'full_name_con',
        'nro_identificacion_con',
        'sex_con',
        'birth_date_con',
        'adress_con',
        'city_id_con',
        'state_id_con',
        'country_id_con',
        'region_con',
        'phone_con',
        'email_con',
        'full_name_ti',
        'nro_identificacion_ti',
        'sex_ti',
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
        'vaucher_ils',
        'date_payment_initial_ils',
        'date_payment_final_ils',
        'document_ils',
        'total_amount',
        'observations'
        
    ];

    protected $casts = [
        'upload_documents' => 'array',
    ];


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

    public function sendCertificate($titular, $afiliates)
    {
        // dd($titular);
        $name_pdf = 'CER-'.$titular['code'].'.pdf';
        
        $data_ti = $titular->toArray();
        
        if(isset($titular->agent)) {
            $name_agent = $titular->agent->name;
            
        }else{
            $name_agent = $titular->agency->name_corporative;
            
        }

        $plan = $titular->plan->description;
        if(isset($titular->coverage_id)) {
            $coverage   = $titular->coverage->price;
        }else{
            $coverage   = 0;
        }

        /**
         * Agregamos la informacion al array principal que viaja a la vista del certificado
         * ----------------------------------------------------------------------------------------------------
         */
        $data_ti['name_agent']  = $name_agent;
        $data_ti['plan']        = $plan;
        $data_ti['coverage']    = $coverage;

        if ($plan == 'PLAN INICIAL') {
            $colorTitle      = '#305B93';
            $titleBeneficios = 'beneficios del plan inicial';
            $imageBeneficios = 'beneficiosInicial.png';
        }
        if ($plan == 'PLAN IDEAL') {
            $colorTitle      = '#052F60';
            $titleBeneficios = 'beneficios del plan ideal';
            $imageBeneficios = 'beneficiosIdeal.png';
        }
        if ($plan == 'PLAN ESPECIAL') {
            $colorTitle      = '#529471';
            $titleBeneficios = 'beneficios del plan emergencias medicas';
            $imageBeneficios = 'beneficiosEspecial.png';
        }

        $data_ti['colorTitle']      = $colorTitle;
        $data_ti['titleBeneficios'] = $titleBeneficios;
        $data_ti['imageBeneficios'] = $imageBeneficios;
        
        SendNotificacionAfiliacionIndividual::dispatch($titular['full_name_ti'], $titular['email_ti'], $name_pdf, $data_ti, $afiliates);

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
}