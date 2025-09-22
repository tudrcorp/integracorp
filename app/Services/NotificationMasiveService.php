<?php

namespace App\Services;

use App\Models\AgentDocument;
use App\Models\DataNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\NotificationMasiveMail;
use App\Models\BirthdayNotification;
use Illuminate\Support\Facades\Mail;
use Barryvdh\Debugbar\Facades\Debugbar;
use App\Mail\NotificationMasiveMailBirthday;
use App\Http\Controllers\NotificationController;

class NotificationMasiveService
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function send($record)
    {

        try {

            set_time_limit(0);

            $array = DataNotification::where('mass_notification_id', $record->id)->get()->toArray();

            for ($i = 0; $i < count($array); $i++) {

                if ($record->header_title == null) {
                    $header = '';
                }

                if ($record->header_title != null) {
                    $header = $record->header_title . ' ' . $array[$i]['fullName'];
                }

                $body = <<<HTML
    
                *{$header}* 

                {$record->content}

                HTML;

                $curl = curl_init();

                if($record->type == 'image') {
                    $params = array(
                        'token'     => config('parameters.TOKEN'),
                        'to'        => $array[$i]['phone'],
                        'image'     => config('parameters.PUBLIC_URL') . '/' . $record->file,
                        'caption'   => $body
                    );

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => config('parameters.CURLOPT_URL_IMAGE'),
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
                    $err = curl_error($curl);

                    Log::info($response);
                    Log::error($err);

                }
                
                if ($record->type == 'video') {
                    $params = array(
                        'token'     => config('parameters.TOKEN'),
                        'to'        => $array[$i]['phone'],
                        'video'     => config('parameters.PUBLIC_URL') . '/' . $record->file,
                        'caption'   => $body
                    );

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => config('parameters.CURLOPT_URL_VIDEO'),
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
                    $err = curl_error($curl);

                    Log::info($response);
                    Log::error($err);

                }
                
                if ($record->type == 'url') {
                    $params = array(
                        'token'     => config('parameters.TOKEN'),
                        'to'        => $array[$i]['phone'],
                        'body'      => $body
                    );

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => config('parameters.CURLOPT_URL'),
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
                    $err = curl_error($curl);

                    Log::info($response);
                    Log::error($err);

                }

                curl_close($curl);

                sleep(1);
                
            }

            return true;
            
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function sendEmail($record)
    {

        try {

            set_time_limit(0);

            $infoArray = $record->toArray();

            $array = DataNotification::where('mass_notification_id', $record->id)->get()->toArray();

            for ($i = 0; $i < count($array); $i++) {
                //envio del email
                Debugbar::info('Destinatario:' . $array[$i]['email']);
                Mail::to($array[$i]['email'])->send(new NotificationMasiveMail($infoArray));

                sleep(5);
                
            }

            return true;
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function sendEmailBirthday($email, $name, $content, $file)
    {

        try {

            set_time_limit(0);

            Mail::to($email)->send(new NotificationMasiveMailBirthday($name, $content, $file));

            return true;
            
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function sendVideo($record)
    {

        try {

            set_time_limit(0);

            $infoArray = $record->toArray();

            $array = DataNotification::where('mass_notification_id', $record->id)->get()->toArray();

            for ($i = 0; $i < count($array); $i++) {

                if ($infoArray['header_title'] != null) {

                    $record->heading = $infoArray['header_title'] . ' ' . $array[$i]['fullName'];
                    $body = <<<HTML
    
                    *{$record->heading}* 
    
                    {$record->content}
    
                    HTML;

                    $params = array(
                        'token' => config('parameters.TOKEN'),
                        'to' => $array[$i]['phone'],
                        'video' => 'https://tudrgroup.com/images/videoDia3.mp4',
                        'caption' => $body
                    );
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => "https://api.ultramsg.com/instance117518/messages/video",
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
                    $err = curl_error($curl);

                    Log::info($response);
                    Log::error($err);

                    curl_close($curl);
                } else {

                    $body = <<<HTML
    
                    {$record->content}
    
                    HTML;

                    $params = array(
                        'token' => config('parameters.TOKEN'),
                        'to' => $array[$i]['phone'],
                        'video' => 'https://tudrgroup.com/images/videoDia3.mp4',
                        'caption' => $body
                    );
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => "https://api.ultramsg.com/instance117518/messages/video",
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
                    $err = curl_error($curl);

                    Log::info($response);
                    Log::error($err);

                    curl_close($curl);
                }
            }

            return true;
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function reminderUploadDoc()
    {
        try {

            $array_doc = [
                'DOCUMENTO DE IDENTIDAD',
                'FIRMA DIGITAL AGENTE',
                'W8/W9',
                'CUENTA USD',
                'CUENTA VES'
            ];

            $agents = DB::table('agents')
                ->select('id', 'email', 'phone', 'status', 'name')
                ->where('status', 'ACTIVO')
                ->get()
                ->toArray();

            for ($i = 0; $i < count($agents); $i++) {
                
                $array_doc_agent = [];
                
                $doc = AgentDocument::where('agent_id', $agents[$i]->id)->get();
                if(count($doc) == 5){
                    continue;
                }
                foreach ($doc as $key => $value) {
                    $array_doc_agent[$key] = $value->title;
                }
                $result = array_diff($array_doc, $array_doc_agent);
                $string = implode(', ', $result);

                if ($agents[$i]->phone == null) {
                    continue;
                }
                
                //Send Notificacion via Whatsapp
                NotificationController::documentUploadReminder($agents[$i]->phone, $agents[$i]->name, $string);
            }
            
            return true;
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function notificationBerthday()
    {
        try {

            set_time_limit(0);

            $tables = BirthdayNotification::where('status', 'APROBADA')->get()->toArray();
            if (count($tables) == 0) {
                return;
            }
            $now = now()->format('d/m/Y');

            // dd($tables);
            for ($i = 0; $i < count($tables); $i++) {
                /**
                 * Preparamos la data para el envio de la notificacion
                 * 
                 * @param $tables
                 * @param $now
                 * 
                 */
                if($tables[$i]['data_type'] == 'agents' || $tables[$i]['data_type'] == 'users' || $tables[$i]['data_type'] == 'suppliers' ) {
                    $data_type = 1;
                    $data = DB::table($tables[$i]['data_type'])
                        ->select('name', 'email', 'phone', 'birth_date')
                        ->where('birth_date', $now)
                        ->get()
                        ->toArray();
                    //Data para el envio
                    $name = $tables[$i]['name'];
                    $email = $tables[$i]['email'];
                    $phone = $tables[$i]['phone'];

                }
                if ($tables[$i]['data_type'] == 'capemiacs') {
                    $data_type = 2;
                    $data = DB::table($tables[$i]['data_type'])
                        ->select('cliente', 'email', 'telefonoUno', 'fecha_registro')
                        ->where('fecha_registro', $now)
                        ->get()
                        ->toArray();
                    //Data para el envio
                    $email = $tables[$i]['email'];
                    $name = $tables[$i]['cliente'];
                    $phone = $tables[$i]['telefonoUno'];
                   
                }
                if ($tables[$i]['data_type'] == 'affiliations') {
                    $data_type = 3;
                    $data = DB::table($tables[$i]['data_type'])
                        ->select('full_name_ti', 'email_ti', 'phone_ti', 'birth_date_ti')
                        ->where('birth_date_ti', $now)
                        ->get()
                        ->toArray();
                    //Data para el envio
                    $email = $tables[$i]['email_ti'];
                    $name = $tables[$i]['full_name_ti'];
                    $phone = $tables[$i]['phone_ti'];
                    
                }
                // dd($data[0]->name);
                /**
                 * Envio de notificacion de cumpleaños
                 * 
                 * @param $data
                 * 
                 */
                if($tables[$i]['type']  == 'whatsapp') {
                    for ($j = 0; $j < count($data); $j++) {
                        NotificationController::notificationBirthday($name, $phone, $tables[$i]['content'], $tables[$i]['file'], $tables[$i]['type']);
                        sleep(1);
                    }
                }

                if($tables[$i]['type']  == 'email') {
                    for ($j = 0; $j < count($data); $j++) {
                        self::sendEmailBirthday($email, $name, $tables[$i]['content'], $tables[$i]['file']);
                        sleep(1);
                    }
                }
            }

            return true;
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function sendUrl($record)
    {

        try {

            set_time_limit(0);

            $infoArray = $record->toArray();

            $array = DataNotification::where('mass_notification_id', $record->id)->get()->toArray();

            for ($i = 0; $i < count($array); $i++) {

                if ($infoArray['header_title'] != null) {

                    $record->heading = $infoArray['header_title'] . ' ' . $array[$i]['fullName'];
                    $body = <<<HTML
    
                    *{$record->heading}* 
    
                    {$record->content}
    
                    HTML;

                    $params = array(
                        'token' => config('parameters.TOKEN'),
                        'to' => $array[$i]['phone'],
                        'body' => $body
                    );
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => config('parameters.CURLOPT_URL'),
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
                    $err = curl_error($curl);

                    Log::info($response);
                    Log::error($err);

                    curl_close($curl);
                    
                } else {

                    $body = <<<HTML
    
                    {$record->content}
    
                    HTML;

                    $params = array(
                        'token' => config('parameters.TOKEN'),
                        'to' => $array[$i]['phone'],
                        'body' => $body
                    );
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => config('parameters.CURLOPT_URL'),
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
                    $err = curl_error($curl);

                    Log::info($response);
                    Log::error($err);

                    curl_close($curl);
                }
            }

            return true;
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }
}