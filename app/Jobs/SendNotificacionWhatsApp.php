<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\LogController;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNotificacionWhatsApp implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $user_id;
    public $body;
    public $phone;
    public $document = null;


    /**
     * Create a new job instance.
     */
    public function __construct($user_id, $body, $phone, $document = null)
    {
        $this->user_id = $user_id;
        $this->body = $body;
        $this->phone = $phone;
        $this->document = $document;
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => $this->phone,
                'body' => $this->body
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL =>  config('parameters.CURLOPT_URL'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);
            $res = json_decode($response, true);
            $err = curl_error($curl);

            if (isset($res) && $res['sent'] == true) {
                Log::info('Se envio el mensaje');
                LogController::log($this->user_id, 'NOTIFICACION: Mensaje enviado', 'NotififcacionController::send_link_preAffiliation()', $res);
                return true;
            } else {
                Log::info('No se envio el mensaje');
                LogController::log($this->user_id, 'NOTIFICACION: Mensaje no enviado', 'NotififcacionController::send_link_preAffiliation()', $err);
                return false;
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }

        //
    }
}
