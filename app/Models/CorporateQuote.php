<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendEmailPropuestaEconomica;
use App\Jobs\SendEmailPropuestaEconomicaMultiple;
use App\Jobs\SendEmailPropuestaEconomicaPlanIdeal;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Jobs\SendEmailPropuestaEconomicaPlanEspecial;

class CorporateQuote extends Model
{
    protected $table = 'corporate_quotes';

    protected $fillable = [
        'code',
        'code_agent',
        'state_id',
        'country_id',
        'region',
        'city_id',
        'code_agency',
        'count_days',
        'full_name',
        'rif',
        'email',
        'phone',
        'status',
        'created_by',
        'agent_id',
        'corporate_quote_request_id',
        'owner_code',
        'plan',
        'observations',
        'data_doc'

    ];

    /**
     * Get all of the comments for the IndividualQuote
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detailCoporateQuotes(): HasMany
    {
        return $this->hasMany(DetailCorporateQuote::class, 'corporate_quote_id', 'id');
    }

    /**
     * Get all of the comments for the IndividualQuote
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(StatusLogCorpQuote::class, 'corporate_quote_id', 'id');
    }

    public function corporateQuoteRequest()
    {
        return $this->belongsTo(CorporateQuoteRequest::class);
    }

    //hasOne
    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class, 'id', 'agent_id');
    }
    
    public function state(): HasOne
    {
        return $this->hasOne(State::class, 'id', 'state_id');
    }

    /**
     * Funciones para la ejecucion de jobs
     * para el envio de los correos de propuesta economica
     * 
     * @return void
     * @author TuDrEnCasa
     * 
     * @param array $details
     */
    public function sendPropuestaEconomicaPlanInicial($details)
    {
        $collect = collect($details['data'][0]);
        // dd($collect);

        /**
         * JOB
         */
        SendEmailPropuestaEconomica::dispatch($details, $collect);
    }

    public function sendPropuestaEconomicaPlanIdeal($details)
    {
        $collect = collect($details['data']);
        $group_collect = $collect->groupBy('age_range');

        /**
         * JOB
         */
        SendEmailPropuestaEconomicaPlanIdeal::dispatch($details, $group_collect);
    }

    public function sendPropuestaEconomicaPlanEspecial($details)
    {
        $collect = collect($details['data']);
        $group_collect = $collect->groupBy('age_range');

        /**
         * JOB
         */
        SendEmailPropuestaEconomicaPlanEspecial::dispatch($details, $group_collect);
    }

    public function sendPropuestaEconomicaMultiple($collect_final)
    {

        try {

            /**
             * JOB
             */
            Log::info($collect_final);

            $details_generals = [];
            for ($i = 0; $i < count($collect_final); $i++) {
                $details_generals = [
                    'code' => $collect_final[$i]['code'],
                    'name' => $collect_final[$i]['name'],
                    'email' => $collect_final[$i]['email'],
                    'phone' => $collect_final[$i]['phone'],
                    'date' => $collect_final[$i]['date'],
                ];
                break;
            }

            SendEmailPropuestaEconomicaMultiple::dispatch($collect_final, $details_generals);
            //code...
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    
}