<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Affiliate;
use App\Models\Affiliation;
use Illuminate\Http\Request;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\CheckAffiliation;
use App\Models\AffiliateCorporate;
use Illuminate\Support\Facades\Log;
use App\Models\AffiliationCorporate;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use App\Models\AfilliationCorporatePlan;
use Filament\Notifications\Notification;

class MigrationHistoricalController extends Controller
{
    static function migrate_history_affiliations($records)
    {
        try {

            $data_array = $records->toArray();

            for ($i = 0; $i < count($data_array); $i++) {

                if ($data_array[$i]['plan'] == 'INDIVIDUAL') {

                    //Cotizacion individual
                    $individual_quote = new IndividualQuote();
                    $individual_quote->code         = self::ind_quote_code_generate();
                    $individual_quote->code_agency  = $data_array[$i]['agency_id'];
                    $individual_quote->owner_code   = $data_array[$i]['owner_code'];
                    $individual_quote->agent_id     = $data_array[$i]['agent_id'];
                    $individual_quote->full_name    = $data_array[$i]['tomador'];
                    $individual_quote->email        = $data_array[$i]['correo'];
                    $individual_quote->phone        = $data_array[$i]['telefono'];
                    $individual_quote->created_by   = Auth::user()->name;
                    $individual_quote->status       = 'EJECUTADA';
                    $individual_quote->save();

                    //Detalle de la cotizacion
                    $details = new DetailIndividualQuote();
                    $details->individual_quote_id   = $individual_quote->id;
                    $details->plan_id               = $data_array[$i]['plan_id'];
                    $details->age_range_id          = $data_array[$i]['age_range_id'];
                    $details->coverage_id           = $data_array[$i]['coverage_id'];
                    $details->total_persons         = $data_array[$i]['total_persons'];
                    $details->fee                   = $data_array[$i]['fee'];
                    $details->subtotal_anual        = $data_array[$i]['fee'] * $data_array[$i]['total_persons'];
                    $details->subtotal_biannual     = ($data_array[$i]['fee'] * $data_array[$i]['total_persons']) / 2;
                    $details->subtotal_quarterly    = ($data_array[$i]['fee'] * $data_array[$i]['total_persons']) / 4;
                    $details->subtotal_monthly      = ($data_array[$i]['fee'] * $data_array[$i]['total_persons']) / 12;
                    $details->status                = 'EJECUTADA';
                    $details->created_by            = Auth::user()->name;
                    $details->save();

                    //Creo la afiliacion
                    $affiliation = new Affiliation();
                    $affiliation->code                    = self::affiliation_individual_code_generate();
                    $affiliation->individual_quote_id     = $individual_quote->id;
                    $affiliation->code_agency             = $data_array[$i]['agency_id'];
                    $affiliation->owner_code              = $data_array[$i]['owner_code'];
                    $affiliation->agent_id                = $data_array[$i]['agent_id'];
                    $affiliation->plan_id                 = $data_array[$i]['plan_id'];
                    $affiliation->coverage_id             = $data_array[$i]['coverage_id'];
                    $affiliation->payment_frequency       = strtoupper($data_array[$i]['frecuencia_pago']);
                    $affiliation->fee_anual               = $data_array[$i]['fee'];
                    $affiliation->family_members          = 1;

                    if ($affiliation->payment_frequency == 'ANUAL') {
                        $affiliation->total_amount = $data_array[$i]['fee'] * $affiliation->family_members;
                    }
                    if ($affiliation->payment_frequency == 'SEMESTRAL') {
                        $affiliation->total_amount = ($data_array[$i]['fee'] * $affiliation->family_members) / 2;
                    }
                    if ($affiliation->payment_frequency == 'TRIMESTRAL') {
                        $affiliation->total_amount = ($data_array[$i]['fee'] * $affiliation->family_members) / 4;
                    }

                    $affiliation->full_name_ti            = $data_array[$i]['afiliado'];
                    $affiliation->nro_identificacion_ti   = $data_array[$i]['nro_doc_tres'];
                    $affiliation->sex_ti                  = $data_array[$i]['sexo'];
                    $affiliation->birth_date_ti           = $data_array[$i]['fecha_nacimiento'];
                    $affiliation->age                     = Carbon::createFromFormat('d/m/Y', $data_array[$i]['fecha_nacimiento'])->age;
                    $affiliation->adress_ti               = $data_array[$i]['direccion'];
                    $affiliation->city_id_ti              = UtilsController::getCity($data_array[$i]['ciudad']);
                    $affiliation->state_id_ti             = UtilsController::getState($data_array[$i]['estado']);
                    $affiliation->country_id_ti           = 189;
                    $affiliation->region_ti               = UtilsController::getRegion($data_array[$i]['estado']);
                    $affiliation->phone_ti                = $data_array[$i]['telefono'];
                    $affiliation->email_ti                = $data_array[$i]['correo'];

                    $affiliation->full_name_payer          = $data_array[$i]['tomador'];
                    $affiliation->nro_identificacion_payer = $data_array[$i]['nro_doc_tres'];

                    $affiliation->status                  = 'ACTIVA';
                    $affiliation->activated_at            = $data_array[$i]['fecha_emision'];
                    $affiliation->vaucher_ils              = $data_array[$i]['nro_vaucher'];
                    $affiliation->date_payment_initial_ils = $data_array[$i]['pagado_ils_desde'];
                    $affiliation->date_payment_final_ils   = $data_array[$i]['pagado_ils_hasta'];
                    $affiliation->observations             = $data_array[$i]['observaciones'];
                    $affiliation->created_by               = Auth::user()->name;
                    $affiliation->save();

                    //Creamos el afiliado en la tabla de afiliados
                    $affiliate = new Affiliate();
                    $affiliate->affiliation_id = $affiliation->id;
                    $affiliate->full_name = $affiliation->full_name_ti;
                    $affiliate->birth_date = $affiliation->birth_date_ti;
                    $affiliate->nro_identificacion = $affiliation->nro_identificacion_ti;
                    $affiliate->sex = $affiliation->sex_ti;
                    $affiliate->age = $affiliation->age;
                    $affiliate->relationship = 'TITULAR';
                    $affiliate->phone = $affiliation->phone_ti;
                    $affiliate->status = 'ACTIVO';
                    $affiliate->country_id = $affiliation->country_id_ti;
                    $affiliate->city_id = $affiliation->city_id_ti;
                    $affiliate->state_id = $affiliation->state_id_ti;
                    $affiliate->region = $affiliation->region_ti;
                    $affiliate->address = $affiliation->adress_ti;
                    $affiliate->plan_id = $affiliation->plan_id;
                    $affiliate->coverage_id = $affiliation->coverage_id;
                    $affiliate->save();

                    //actualizo el status de la data
                    $update_status = CheckAffiliation::where('id', $data_array[$i]['id'])->first();
                    $update_status->status_migration  = 'PROCESADO';
                    $update_status->save();
                }

            }

            return true;
            //code...
        } catch (\Throwable $th) {
            Log::error($th);
            Notification::make()
                ->title('Error al migrar')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }

    static function migrate_history_affiliations_corporate($records, $data, $poblation)
    {
        try {
            
            $data_array = $records->toArray();
            // dd($data_array);

            $affiliation = new AffiliationCorporate();
            $affiliation->code                    = self::affiliation_corporate_code_generate();
            $affiliation->corporate_quote_id      = $data['corporate_quote_id'];
            $affiliation->code_agency             = $data_array[0]['agency_id'];
            $affiliation->owner_code              = $data_array[0]['owner_code'];
            $affiliation->agent_id                = $data_array[0]['agent_id'];
            $affiliation->payment_frequency       = strtoupper($data_array[0]['frecuencia_pago']);
            $affiliation->fee_anual               = $data_array[0]['fee'];

            if ($affiliation->payment_frequency == 'ANUAL') {
                $affiliation->total_amount = $affiliation->fee_anual * $poblation;
            }
            if ($affiliation->payment_frequency == 'SEMESTRAL') {
                $affiliation->total_amount = ($affiliation->fee_anual * $poblation) / 2;
            }
            if ($affiliation->payment_frequency == 'TRIMESTRAL') {
                $affiliation->total_amount = ($affiliation->fee_anual * $poblation) / 4;
            }


            $affiliation->name_corporate        = $data_array[0]['tomador'];
            $affiliation->rif                     = $data_array[0]['nro_doc'];
            $affiliation->address                 = '...';
            $affiliation->city_id                 = 32;
            $affiliation->state_id                = 10;
            $affiliation->country_id              = 189;
            $affiliation->region_id               = 'CAPITAL';
            $affiliation->phone                   = '+584242220056';
            $affiliation->email                   = 'afiliaciones@tudrencasa.com';

            $affiliation->full_name_contact          = '...';
            $affiliation->nro_identificacion_contact = '...';
            $affiliation->phone_contact              = '...';
            $affiliation->email_contact              = '...';
            
            $affiliation->status                   = 'ACTIVA';
            $affiliation->activated_at             = $data_array[0]['fecha_emision'];
            $affiliation->vaucher_ils              = $data_array[0]['nro_vaucher'];
            $affiliation->date_payment_initial_ils = $data_array[0]['pagado_ils_desde'];
            $affiliation->date_payment_final_ils   = $data_array[0]['pagado_ils_hasta'];
            $affiliation->observations             = $data_array[0]['observaciones'];
            $affiliation->created_by               = Auth::user()->name;
            $affiliation->save();

            //Afiliamos el o los planes asociados
            $affiliation_corporate_plan = new AfilliationCorporatePlan();
            $affiliation_corporate_plan->affiliation_corporate_id   = $affiliation->id;
            $affiliation_corporate_plan->code_affiliation           = $affiliation->code;
            $affiliation_corporate_plan->age_range_id               = $data_array[0]['age_range_id'];
            $affiliation_corporate_plan->coverage_id                = $data_array[0]['coverage_id'];
            $affiliation_corporate_plan->plan_id                    = $data_array[0]['plan_id'];
            $affiliation_corporate_plan->fee                        = $data_array[0]['fee'];
            $affiliation_corporate_plan->total_persons              = $poblation;
            $affiliation_corporate_plan->payment_frequency          = strtoupper($data_array[0]['frecuencia_pago']);
            $affiliation_corporate_plan->subtotal_anual             = $affiliation_corporate_plan->fee * $poblation;
            $affiliation_corporate_plan->subtotal_quarterly         = ($affiliation_corporate_plan->fee * $poblation) / 4;
            $affiliation_corporate_plan->subtotal_biannual          = ($affiliation_corporate_plan->fee * $poblation) / 2;
            $affiliation_corporate_plan->status                     = 'ACTIVA';
            $affiliation_corporate_plan->save();
            

            //Cargamos los afiliados (la poblcion)
            for ($i = 0; $i < count($data_array); $i++) {

                if ($data_array[$i]['plan'] == 'CORPORATIVO') {
                    //Creo la afiliacion

                    //Creamos el afiliado en la tabla de afiliados
                    $affiliate_corporate = new AffiliateCorporate();
                    $affiliate_corporate->affiliation_corporate_id          = $affiliation->id;
                    $affiliate_corporate->first_name                        = $data_array[$i]['afiliado'];
                    $affiliate_corporate->last_name                         = '...';
                    $affiliate_corporate->birth_date                        = $data_array[$i]['fecha_nacimiento'];
                    $affiliate_corporate->nro_identificacion                = $data_array[$i]['nro_doc_tres'];
                    $affiliate_corporate->sex                               = $data_array[$i]['sexo'];
                    $affiliate_corporate->age                               = Carbon::createFromFormat('d/m/Y', $data_array[$i]['fecha_nacimiento'])->age;
                    $affiliate_corporate->phone                             = $data_array[$i]['telefono'];
                    $affiliate_corporate->email                             = $data_array[$i]['correo'];
                    $affiliate_corporate->condition_medical                 = '...';
                    $affiliate_corporate->initial_date                      = '...';
                    $affiliate_corporate->position_company                  = '...';
                    $affiliate_corporate->address                           = $data_array[$i]['direccion'];

                    $affiliate_corporate->full_name_emergency               = '...';
                    $affiliate_corporate->phone_emergency                   = '...';
                    $affiliate_corporate->plan_id                           = $affiliation_corporate_plan->plan_id;
                    $affiliate_corporate->coverage_id                       = $affiliation_corporate_plan->coverage_id;
                    $affiliate_corporate->payment_frequency                 = strtoupper($data_array[$i]['frecuencia_pago']);
                    $affiliate_corporate->fee                               = $affiliation_corporate_plan->fee;
                    $affiliate_corporate->subtotal_anual                    = $affiliation_corporate_plan->fee;

                    if ($affiliate_corporate->payment_frequency == 'ANUAL') {
                        $affiliate_corporate->subtotal_payment_frequency    = $affiliation->fee_anual;
                    }
                    if ($affiliate_corporate->payment_frequency == 'SEMESTRAL') {
                        $affiliate_corporate->subtotal_payment_frequency    = $affiliation->fee_anual / 2;
                    }
                    if ($affiliate_corporate->payment_frequency == 'TRIMESTRAL') {
                        $affiliate_corporate->subtotal_payment_frequency    = $affiliation->fee_anual / 4;
                    }
                    
                    $affiliate_corporate->subtotal_daily                    = $affiliation->fee_anual / 365;
                    $affiliate_corporate->status                            = 'ACTIVO';
                    $affiliate_corporate->save();

                    //actualizo el status de la data
                    $update_status = CheckAffiliation::where('id', $data_array[$i]['id'])->first();
                    $update_status->status_migration  = 'PROCESADO';
                    $update_status->save();
                }
            }

            return true;
            //code...
        } catch (\Throwable $th) {
            Log::error($th);
            Notification::make()
                ->title('Error al migrar')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function ind_quote_code_generate()
    {
        if (IndividualQuote::max('id') == null) {
            $parte_entera = 0;
            
        } else {
            $parte_entera = IndividualQuote::max('id');
            
        }
        
        return 'COT-IND-000' . $parte_entera + 1;
    }

    public static function cor_quote_code_generate()
    {
        if (CorporateQuote::max('id') == null) {
            $parte_entera = 0;
            
        } else {
            $parte_entera = CorporateQuote::max('id');
            
        }
        
        return 'COT-COR-000' . $parte_entera + 1;
    }

    public static function affiliation_individual_code_generate()
    {
        if (Affiliation::max('id') == null) {
            $parte_entera = 0;
        } else {
            $parte_entera = Affiliation::max('id');
        }

        return 'TDEC-IND-000' . $parte_entera + 1;
    }

    public static function affiliation_corporate_code_generate()
    {
        if (AffiliationCorporate::max('id') == null) {
            $parte_entera = 0;
        } else {
            $parte_entera = AffiliationCorporate::max('id');
        }

        return 'TDEC-COR-000' . $parte_entera + 1;
    }
}