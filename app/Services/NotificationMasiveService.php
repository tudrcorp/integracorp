<?php

namespace App\Services;

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Jobs\SendNotificationMasiveMailBirthday;
use App\Mail\NotificationMasiveMail;
use App\Mail\NotificationMasiveMailBirthday;
use App\Models\AgentDocument;
use App\Models\BirthdayNotification;
use App\Models\DataNotification;
use App\Models\TelemedicinePatientMedications;
use Barryvdh\Debugbar\Facades\Debugbar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PhpParser\Node\Stmt\TryCatch;
use Throwable;

class NotificationMasiveService
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function send($dataNotificationArray, $infoNotificationArray)
    {
        
        try {

            set_time_limit(0);

            if ($infoNotificationArray['header_title'] == null) {
                $header = '';
            }

            if ($infoNotificationArray['header_title'] != null) {
                $header = $infoNotificationArray['header_title'] . ' ' . $dataNotificationArray['fullName'];
            }

            $body = <<<HTML

            {$header} 

            {$infoNotificationArray['content']}

            HTML;

            $curl = curl_init();

            if($infoNotificationArray['type'] == 'image') {
                $params = array(
                    'token'     => config('parameters.TOKEN'),
                    'to'        => $dataNotificationArray['phone'],
                    'image'     => config('parameters.PUBLIC_URL') . '/' . $infoNotificationArray['file'],
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
            
            if ($infoNotificationArray['type'] == 'video') {
                $params = array(
                    'token'     => config('parameters.TOKEN'),
                    'to'        => $dataNotificationArray['phone'],
                    'video'     => config('parameters.PUBLIC_URL') . '/' . $infoNotificationArray['file'],
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
            
            if ($infoNotificationArray['type'] == 'url') {
                $params = array(
                    'token'     => config('parameters.TOKEN'),
                    'to'        => $dataNotificationArray['phone'],
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

            Log::info('Enviado a:' . $dataNotificationArray['phone']);

            sleep(20);

            return true;
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage(). 'Line: ' . $th->getLine(). ' File: ' . $th->getFile());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function sendEmail($email, $record)
    {

        try {

            set_time_limit(0);

            $infoArray = $record->toArray();

            Log::info('Destinatario:' . $email);
            Mail::to($email)->send(new NotificationMasiveMail($infoArray));

            sleep(5);

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
            Log::error($th->getMessage() . ' Linea: ' . $th->getLine() . ' Archivo: ' . $th->getFile());
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

            $rowsNotifications = BirthdayNotification::where('status', 'APROBADA')->get()->toArray();
            
            if (count($rowsNotifications) == 0) {
                return;
            }
            //Fecha actual con el formato para comparar dia y mes
            $now = now()->format('d/m');

            // dd($tables);
            for ($i = 0; $i < count($rowsNotifications); $i++) {

                //For para recorrer los canales de envio
                for ($j = 0; $j < count($rowsNotifications[$i]['channels']); $j++) {

                    //Canal Whatsapp
                    if($rowsNotifications[$i]['channels'][$j] == 'whatsapp') {
                        
                        //AGENTS, USERS, SUPPLIERS  
                        if($rowsNotifications[$i]['data_type'] == 'agents' || $rowsNotifications[$i]['data_type'] == 'users' || $rowsNotifications[$i]['data_type'] == 'suppliers' ) {
                            
                            //Selecciono la data que voy a utilizar segun la notificacion
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('name', 'email', 'phone', 'birth_date')
                                ->get()
                                ->toArray();
                            
                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {

                                /**
                                 * En caso de que la data venga NULL
                                 */
                                if ($data[$k]->phone != null && $data[$k]->birth_date != null) {
                                    //Tomamos la fecha de nacimiento de la data principal y la convertimos en el formato dd/mm
                                    $conversionDate = UtilsController::converterDate($data[$k]->birth_date);
    
                                    //comparamos la fecha de nacimiento con la fecha actual
                                    if($conversionDate == $now){
                                        //Ejecuto el envio de la notificacion
                                        NotificationController::notificationBirthday($data[$k]->name, $data[$k]->phone, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file'], $rowsNotifications[$i]['type']);
    
                                    }
                                }else{
                                    continue;
                                }
                                
                            }
                            
                        }

                        //AFFILIATIONS
                        if ($rowsNotifications[$i]['data_type'] == 'affiliations') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('full_name_ti', 'email_ti', 'phone_ti', 'birth_date_ti')
                                ->where('birth_date_ti', $now)
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {

                                /**
                                 * En caso de que la data venga NULL
                                 */
                                if ($data[$k]->phone_ti != null && $data[$k]->birth_date_ti != null) {
                                    //Tomamos la fecha de nacimiento de la data principal y la convertimos en el formato dd/mm
                                    $conversionDate = UtilsController::converterDate($data[$k]->birth_date_ti);

                                    //comparamos la fecha de nacimiento con la fecha actual
                                    if ($conversionDate == $now) {
                                        //Ejecuto el envio de la notificacion
                                        NotificationController::notificationBirthday($data[$k]->full_name_ti, $data[$k]->phone_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file'], $rowsNotifications[$i]['type']);
                                    }
                                    
                                } else {
                                    continue;
                                }
                            }
                        }
                        
                    }

                    //Canal Email
                    //sendEmailBirthday($email, $name, $content, $file)
                    if ($rowsNotifications[$i]['channels'][$j] == 'email') {
                        
                        //AGENTS, USERS, SUPPLIERS
                        if ($rowsNotifications[$i]['data_type'] == 'agents' || $rowsNotifications[$i]['data_type'] == 'users' || $rowsNotifications[$i]['data_type'] == 'suppliers') {

                            //Selecciono la data que voy a utilizar segun la notificacion
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('name', 'email', 'phone', 'birth_date')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {

                                /**
                                 * En caso de que la data venga NULL
                                 */
                                if ($data[$k]->email != null) {

                                    //Ejecuto el envio de la notificacion
                                    set_time_limit(0);
                                    Mail::to('solrodriguez@tudrencasa.com')->send(new NotificationMasiveMailBirthday($data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']));
                                    Mail::to('gcamacho@tudrencasa.com')->send(new NotificationMasiveMailBirthday($data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']));
                                    
                                } else {
                                    continue;
                                }
                            }
                            
                        }

                        //AFFILIATIONS -- Logica Actualizada v2.1
                        if ($rowsNotifications[$i]['data_type'] == 'affiliations') {
                            // dump($rowsNotifications[$i]['data_type'], $rowsNotifications[$i]['channels']);
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('full_name_ti', 'email_ti', 'phone_ti', 'birth_date_ti')
                                ->get()
                                ->toArray();
                            // dump($data);

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {

                                //Validamos si esta cumpliendo años
                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->birth_date_ti);
                                dump($isBirthdayToday);
                                if ($isBirthdayToday) {
                                // dd('cumple');
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->email_ti != null) {

                                        //Ejecuto el envio de la notificacion
                                        // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                        set_time_limit(0);

                                        Mail::to('solrodriguez@tudrencasa.com')->send(new NotificationMasiveMailBirthday($data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']));
                                        Mail::to('gustavoalberto.camachop@gmail.com')->send(new NotificationMasiveMailBirthday($data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']));

                                        // SendNotificationMasiveMailBirthday::dispatch($email, $name, $content, $file)->onQueue('system');

                                    } else {
                                        continue;
                                    }
                                } else {
                                    continue;
                                }
                            }
                        }
                    }
                    
                    //End...
                }
                
                //End...
            }

            return true;
            
        } catch (Throwable $th) {
            // OPTIMIZACIÓN 100% DEL CATCH PRINCIPAL
            Log::emergency("FALLA CRÍTICA en el Servicio de Notificación de Cumpleaños", [
                'message' => $th->getMessage(),
                'file'    => $th->getFile(),
                'line'    => $th->getLine(),
            ]);

            // Reportar a servicios de monitoreo (Sentry/Flare)
            report($th);

            // Podrías lanzar una excepción personalizada o retornar false
            return false;
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function notificationRemenberMedication()
    {
        try {

            $medications = TelemedicinePatientMedications::with('telemedicinePatient')->get()->toArray();

            for ($i = 0; $i < count($medications); $i++) {

                //... Fecha de asignacion del tratamiento
                $asignationDate = Carbon::parse($medications[$i]['created_at'])->format('Y-m-d');

                //... Fecha de Hoy
                $today = now()->format('Y-m-d');

                //... Dias Trascurridos
                $diasTranscurridos = Carbon::parse($asignationDate)->diffInDays($today);

                if ($diasTranscurridos <= $medications[$i]['duration']) {

                    //... Disparo la notificacion
                }
            }

            
        } catch (\Throwable $th) {
            //throw $th;
        }
        
    }

}