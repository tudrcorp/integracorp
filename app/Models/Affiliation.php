<?php

namespace App\Models;

use App\Mail\CertificateEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\SendTarjetaAfiliado;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendNotificacionAfiliacionIndividual;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'observations',
        'feedback',
        'feedback_dos',
        // 'is_accepted',
        
    ];

    protected $casts = [
        'upload_documents' => 'array',
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

    public function sendCertificate($record, $titular, $afiliates)
    {
        // dd($record, $titular, $afiliates);
        try {

            $data = $record->toArray();

            $name_pdf = 'CER-' . $record->code . '.pdf';

            if (isset($record->agent)) {
                $name_agent = $record->agent->name;
            } else {
                $name_agent = $record->agency->name_corporative;
            }

            $plan = $record->plan->description;

            if (isset($record->coverage_id)) {
                $coverage   = $record->coverage->price;
            } else {
                $coverage   = 0;
            }

            /**
             * Agregamos la informacion al array principal que viaja a la vista del certificado
             * ----------------------------------------------------------------------------------------------------
             */
            $data['name_agent']  = $name_agent;
            $data['plan']        = $plan;
            $data['coverage']    = $coverage;

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

            $data['colorTitle']      = $colorTitle;
            $data['titleBeneficios'] = $titleBeneficios;
            $data['imageBeneficios'] = $imageBeneficios;

            SendNotificacionAfiliacionIndividual::dispatch($titular['full_name'], Auth::user()->email, $name_pdf, $data, $afiliates);
            //code...
            
        } catch (\Throwable $th) {
            dd($th);
            //throw $th;
        }
        

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

    public function sendCertificateOnlyHolder($record, $afiliates)
    {
        // dd($record->toArray(), $afiliates);
        try {

            $data = $record->toArray();

            $name_pdf = 'CER-' . $record->code . '.pdf';

            if (isset($record->agent)) {
                $name_agent = $record->agent->name;
            } else {
                $name_agent = $record->agency->name_corporative;
            }

            $plan = $record->plan->description;

            if (isset($record->coverage_id)) {
                $coverage   = $record->coverage->price;
            } else {
                $coverage   = 0;
            }

            /**
             * Agregamos la informacion al array principal que viaja a la vista del certificado
             * ----------------------------------------------------------------------------------------------------
             */
            $data['name_agent']  = $name_agent;
            $data['plan']        = $plan;
            $data['coverage']    = $coverage;

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

            $data['colorTitle']      = $colorTitle;
            $data['titleBeneficios'] = $titleBeneficios;
            $data['imageBeneficios'] = $imageBeneficios;
            // dd(count($afiliates), $afiliates);
            SendNotificacionAfiliacionIndividual::dispatch($afiliates[0]['full_name'], Auth::user()->email, $name_pdf, $data, $afiliates);
            //code...
        } catch (\Throwable $th) {
            dd($th);
            //throw $th;
        }
    }

    public function affiliationIndividualPlans(): HasMany
    {
        return $this->hasMany(AfilliationIndividualPlan::class);
    }
}