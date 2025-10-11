<?php

namespace App\Models;

use App\Models\Agent;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\LogController;
use Illuminate\Database\Eloquent\Model;
use App\Models\DetailsCorporateQuoteRequest;
use App\Jobs\SendNotificacionSolicitudCotizacion;

class CorporateQuoteRequest extends Model
{
    protected $table = 'corporate_quote_requests';

    protected $fillable = [
        'code',
        'owner_code',
        'code_agency',
        'agent_id',
        'full_name',
        'rif',
        'email',
        'phone',
        'state_id',
        'region',
        'status',
        'created_by',
        'observations',
        'poblation',
        'ownerAccountManagers'
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

    public function details()
    {
        return $this->hasMany(DetailsCorporateQuoteRequest::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agent::class);
    }


    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function detailsData()
    {
        return $this->hasMany(CorporateQuoteRequestData::class);
    }

    /**
     * JOB
     * Este Job se ejecuta para enviar una notificacion al correo de administrador
     * despues de crear la cotizacion
     * 
     * @author Gustavo Camacho
     * @version 1.0
     * 
     * @see SendNotificacionSolicitudCotizacion
     * 
     */
    public function sendNotification($record)
    {
        try {

            SendNotificacionSolicitudCotizacion::dispatch($record);
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::send_link_preAffiliation()', $th->getMessage());
        }
    }
}