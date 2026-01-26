<?php

namespace App\Services;

use App\Http\Controllers\LogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Jobs\SendNotificationMasiveMailBirthday;
use App\Mail\NotificationMasiveMail;
use App\Mail\NotificationMasiveMailBirthday;
use App\Models\AgentDocument;
use App\Models\BirthdayNotification;
use App\Models\DataNotification;
use App\Models\NotificationFailed;
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

                    /**
                     * CANAL DE ENVIO WHATSAPP
                     * --------------------------------------------------------------------------
                     * Notificacion masivas de forma automatica
                     * para envio de tarjeta de cumpleaños
                     * 
                     * @version 3.0
                     */
                    if($rowsNotifications[$i]['channels'][$j] == 'whatsapp') {

                        // AGENTES -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'agents') {

                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('name', 'phone', 'birth_date')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->birth_date == null || $data[$k]->birth_date == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->name,
                                        null,
                                        $data[$k]->phone,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'agentes'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->birth_date)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->name,
                                        null,
                                        $data[$k]->phone,
                                        'Formato de fecha de cumpleaños inválido',
                                        'agentes'
                                    );
                                    continue;
                                }

                                if ($data[$k]->phone == null || $data[$k]->phone == '') {
                                    Log::warning("Numero de telefono es nulo o vacio para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->name,
                                        null,
                                        $data[$k]->phone,
                                        'Numero de telefono es nulo o vacio',
                                        'agentes'
                                    );
                                    continue;
                                }

                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->birth_date);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->phone != null) {

                                        //Ejecuto el envio de la notificacion
                                        set_time_limit(0);

                                        NotificationController::notificationBirthday(
                                            $data[$k]->name,
                                            $data[$k]->phone,
                                            $rowsNotifications[$i]['content'],
                                            $rowsNotifications[$i]['file'],
                                            $rowsNotifications[$i]['type']
                                        );

                                        // LogController::logSuccessWp($data[$k]->phone);

                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info("No se envio el WhatsApp de cumpleaños para agentes");
                                    continue;

                                }
                            }
                        }

                        // AGENCIAS -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'agencies') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('name_corporative', 'phone', 'brithday_date')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->brithday_date == null || $data[$k]->brithday_date == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->name_corporative ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->name_corporative,
                                        null,
                                        $data[$k]->phone,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'agencias'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->brithday_date)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->name_corporative ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->name_corporative,
                                        null,
                                        $data[$k]->phone,
                                        'Formato de fecha de cumpleaños inválido',
                                        'agencias'
                                    );
                                    continue;
                                }

                                if ($data[$k]->phone == null || $data[$k]->phone == '') {
                                    Log::warning("Numero de telefono es nulo o vacio para el usuario: " . ($data[$k]->name_corporative ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->name_corporative,
                                        null,
                                        $data[$k]->phone,
                                        'Numero de telefono es nulo o vacio',
                                        'agencias'
                                    );
                                    continue;
                                }

                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->brithday_date);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->phone != null) {

                                        //Ejecuto el envio de la notificacion
                                        set_time_limit(0);

                                        NotificationController::notificationBirthday(
                                            $data[$k]->name_corporative,
                                            $data[$k]->phone,
                                            $rowsNotifications[$i]['content'],
                                            $rowsNotifications[$i]['file'],
                                            $rowsNotifications[$i]['type']
                                        );

                                        // LogController::logSuccessWp($data[$k]->phone);

                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info("No se envio el WhatsApp de cumpleaños para agentes");
                                    continue;
                                }
                            }
                        }

                        // AFILIACIONES INDIVIDUALES -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'affiliates') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('full_name', 'phone', 'birth_date')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->birth_date == null || $data[$k]->birth_date == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->full_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->full_name,
                                        null,
                                        $data[$k]->phone,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'afiliaciones'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->birth_date)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->full_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->full_name,
                                        null,
                                        $data[$k]->phone,
                                        'Formato de fecha de cumpleaños inválido',
                                        'afiliaciones'
                                    );
                                    continue;
                                }

                                if ($data[$k]->phone == null || $data[$k]->phone == '') {
                                    Log::warning("Numero de telefono es nulo o vacio para el usuario: " . ($data[$k]->full_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->full_name,
                                        null,
                                        $data[$k]->phone,
                                        'Numero de telefono es nulo o vacio',
                                        'afiliaciones'
                                    );
                                    continue;
                                }

                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->birth_date);

                                if ($data[$k]->phone != null) {
                                    if ($isBirthdayToday) {
                                        /**
                                         * En caso de que la data venga NULL
                                         */
                                        if ($data[$k]->phone != null) {

                                            //Ejecuto el envio de la notificacion
                                            // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                            set_time_limit(0);

                                            //Envio Principal al Cliente
                                            NotificationController::notificationBirthday(
                                                $data[$k]->full_name,
                                                $data[$k]->phone,
                                                $rowsNotifications[$i]['content'],
                                                $rowsNotifications[$i]['file'],
                                                $rowsNotifications[$i]['type']
                                            );

                                            // LogController::logSuccess($data[$k]->email);

                                        } else {
                                            continue;
                                        }
                                    } else {
                                        Log::info("No se envio el correo de cumpleaños para afiliados");
                                        continue;
                                    }
                                }
                            }
                        }

                        // AFILIACIONES CORPORATIVAS -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'affiliate_corporates') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('first_name', 'phone', 'birth_date')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->birth_date == null || $data[$k]->birth_date == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->first_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->first_name,
                                        null,
                                        $data[$k]->phone,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'afiliaciones corporativas'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->birth_date)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->first_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->first_name,
                                        null,
                                        $data[$k]->phone,
                                        'Formato de fecha de cumpleaños inválido',
                                        'afiliaciones corporativas'
                                    );
                                    continue;
                                }

                                if ($data[$k]->phone == null || $data[$k]->phone == '') {
                                    Log::warning("Numero de telefono es nulo o vacio para el usuario: " . ($data[$k]->first_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->first_name,
                                        null,
                                        $data[$k]->phone,
                                        'Numero de telefono es nulo o vacio',
                                        'afiliaciones corporativas'
                                    );
                                    continue;
                                }

                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->birth_date);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->phone != null) {

                                        //Ejecuto el envio de la notificacion
                                        // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                        set_time_limit(0);

                                        //Envio Principal al Cliente
                                        NotificationController::notificationBirthday(
                                            $data[$k]->first_name,
                                            $data[$k]->phone,
                                            $rowsNotifications[$i]['content'],
                                            $rowsNotifications[$i]['file'],
                                            $rowsNotifications[$i]['type']
                                        );

                                        // LogController::logSuccess($data[$k]->email);
                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info("No se envio el correo de cumpleaños para afiliados");
                                    continue;
                                }
                            }
                        }

                        // COLABORADORES -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'rrhh_colaboradors') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('fullName', 'telefono', 'fechaNacimiento')
                                ->get()
                                ->toArray();


                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->fechaNacimiento == null || $data[$k]->fechaNacimiento == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->fullName ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->fullName,
                                        null,
                                        $data[$k]->telefono,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'colaboradores'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->fechaNacimiento)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->fullName ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->fullName,
                                        null,
                                        $data[$k]->telefono,
                                        'Formato de fecha de cumpleaños inválido',
                                        'colaboradores'
                                    );
                                    continue;
                                }

                                if ($data[$k]->telefono == null || $data[$k]->telefono == '') {
                                    Log::warning("Numero de telefono es nulo o vacio para el usuario: " . ($data[$k]->fullName ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->fullName,
                                        null,
                                        $data[$k]->telefono,
                                        'Numero de telefono es nulo o vacio',
                                        'colaboradores'
                                    );
                                    continue;
                                }

                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->fechaNacimiento);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->telefono != null) {

                                        //Ejecuto el envio de la notificacion
                                        // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                        set_time_limit(0);

                                        //Envio Principal al Cliente
                                        NotificationController::notificationBirthday(
                                            $data[$k]->fullName,
                                            $data[$k]->telefono,
                                            $rowsNotifications[$i]['content'],
                                            $rowsNotifications[$i]['file'],
                                            $rowsNotifications[$i]['type']
                                        );

                                        LogController::logSuccess($data[$k]->emailCorporativo);
                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info('No se envio el correo de cumpleaños para colaboradores');
                                    continue;
                                }
                            }
                        }

                        // PROVEEDORES -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'suppliers') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('name', 'personal_phone', 'afiliacion_proveedor')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->afiliacion_proveedor == null || $data[$k]->afiliacion_proveedor == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->name,
                                        null,
                                        $data[$k]->personal_phone,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'proveedores'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->afiliacion_proveedor)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->name,
                                        null,
                                        $data[$k]->personal_phone,
                                        'Formato de fecha de cumpleaños inválido',
                                        'proveedores'
                                    );
                                    continue;
                                }

                                if ($data[$k]->personal_phone == null || $data[$k]->personal_phone == '') {
                                    Log::warning("Numero de telefono es nulo o vacio para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'whatsapp',
                                        $data[$k]->name,
                                        null,
                                        $data[$k]->personal_phone,
                                        'Numero de telefono es nulo o vacio',
                                        'proveedores'
                                    );
                                    continue;
                                }

                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->afiliacion_proveedor);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->personal_phone != null) {

                                        //Ejecuto el envio de la notificacion
                                        // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                        set_time_limit(0);

                                        //Envio Principal al Cliente
                                        NotificationController::notificationBirthday(
                                            $data[$k]->name,
                                            $data[$k]->personal_phone,
                                            $rowsNotifications[$i]['content'],
                                            $rowsNotifications[$i]['file'],
                                            $rowsNotifications[$i]['type']
                                        );

                                        // LogController::logSuccess($data[$k]->personal_phone);
                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info('No se envio el correo de cumpleaños para proveedores');
                                    continue;
                                }
                            }
                        }
                        
                    }

                    /**
                     * CANAL DE ENVIO EMAIL
                     * --------------------------------------------------------------------------
                     * Notificacion masivas de forma automatica
                     * para envio de tarjeta de cumpleaños
                     * 
                     * @version 3.0
                     */
                    if ($rowsNotifications[$i]['channels'][$j] == 'email') {

                        // AGENTES -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'agents') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('name', 'email', 'birth_date')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->birth_date == null || $data[$k]->birth_date == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->name,
                                        $data[$k]->email,
                                        null,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'agentes'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->birth_date)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->name,
                                        $data[$k]->email,
                                        null,
                                        'Formato de fecha de cumpleaños inválido',
                                        'agentes'
                                    );
                                    continue;
                                }

                                if ($data[$k]->email == null || $data[$k]->email == '') {
                                    Log::warning("Email es nulo o vacio para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->name,
                                        $data[$k]->email,
                                        null,
                                        'Email es nulo o vacio',
                                        'agentes'
                                    );
                                    continue;
                                }

                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->birth_date);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->email != null) {

                                        //Ejecuto el envio de la notificacion
                                        // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                        set_time_limit(0);

                                        //Envio Principal al Cliente
                                        Mail::to($data[$k]->email)
                                            ->cc('solrodriguez@tudrencasa.com')
                                            ->send(new NotificationMasiveMailBirthday(
                                                $data[$k]->name,
                                                $rowsNotifications[$i]['file'],
                                                $data[$k]->email
                                            ));

                                        LogController::logSuccess($data[$k]->email);

                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info("No se envio el correo de cumpleaños para agentes");
                                    continue;
                                }

                            }
                        }

                        // AGENCIAS -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'agencies') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('name_corporative', 'email', 'brithday_date')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->brithday_date == null || $data[$k]->brithday_date == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->name_corporative ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->name_corporative,
                                        $data[$k]->email,
                                        null,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'agencias'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->brithday_date)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->name_corporative ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->name_corporative,
                                        $data[$k]->email,
                                        null,
                                        'Formato de fecha de cumpleaños inválido',
                                        'agencias'
                                    );
                                    continue;
                                }

                                if ($data[$k]->email == null || $data[$k]->email == '') {
                                    Log::warning("Email es nulo o vacio para el usuario: " . ($data[$k]->name_corporative ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->name_corporative,
                                        $data[$k]->email,
                                        null,
                                        'Email es nulo o vacio',
                                        'agencias'
                                    );
                                    continue;
                                }

                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->brithday_date);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->email != null) {

                                        //Ejecuto el envio de la notificacion
                                        // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                        set_time_limit(0);

                                        //Envio Principal al Cliente
                                        Mail::to($data[$k]->email)
                                            ->cc('solrodriguez@tudrencasa.com')
                                            ->send(new NotificationMasiveMailBirthday(
                                                $data[$k]->name_corporative,
                                                $rowsNotifications[$i]['file'],
                                                $data[$k]->email
                                            ));

                                        LogController::logSuccess($data[$k]->email);

                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info("No se envio el correo de cumpleaños para agentes");
                                    continue;
                                }
                            }
                        }

                        // AFILIACIONES INDIVIDUALES -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'affiliates') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('full_name', 'email', 'birth_date')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->birth_date == null || $data[$k]->birth_date == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->full_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->full_name,
                                        $data[$k]->email,
                                        null,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'afiliaciones'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->birth_date)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->full_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->full_name,
                                        $data[$k]->email,
                                        null,
                                        'Formato de fecha de cumpleaños inválido',
                                        'afiliaciones'
                                    );
                                    continue;
                                }

                                if ($data[$k]->email == null || $data[$k]->email == '') {
                                    Log::warning("Email es nulo o vacio para el usuario: " . ($data[$k]->full_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->full_name,
                                        $data[$k]->email,
                                        null,
                                        'Email es nulo o vacio',
                                        'afiliaciones'
                                    );
                                    continue;
                                }
                                
                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->birth_date);
                                if ($data[$k]->email != null) {
                                    if ($isBirthdayToday) {
                                        /**
                                         * En caso de que la data venga NULL
                                         */
                                        if ($data[$k]->email != null) {

                                            //Ejecuto el envio de la notificacion
                                            // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                            set_time_limit(0);

                                            //Envio Principal al Cliente
                                            Mail::to($data[$k]->email)
                                                ->cc('solrodriguez@tudrencasa.com')
                                                ->send(new NotificationMasiveMailBirthday(
                                                    $data[$k]->full_name, 
                                                    $rowsNotifications[$i]['file'], 
                                                    $data[$k]->email
                                                ));

                                            LogController::logSuccess($data[$k]->email);


                                        } else {
                                            continue;
                                        }
                                    } else {
                                        Log::info("No se envio el correo de cumpleaños para afiliados");
                                        continue;
                                    }
                                }

                            }
                        }

                        // AFILIACIONES CORPORATIVAS -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'affiliate_corporates') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('first_name', 'email', 'birth_date')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->birth_date == null || $data[$k]->birth_date == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->full_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->full_name,
                                        $data[$k]->email,
                                        null,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'afiliaciones_corporativas'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->birth_date)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->full_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->full_name,
                                        $data[$k]->email,
                                        null,
                                        'Formato de fecha de cumpleaños inválido',
                                        'afiliaciones_corporativas'
                                    );
                                    continue;
                                }

                                if ($data[$k]->email == null || $data[$k]->email == '') {
                                    Log::warning("Email es nulo o vacio para el usuario: " . ($data[$k]->full_name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->full_name,
                                        $data[$k]->email,
                                        null,
                                        'Email es nulo o vacio',
                                        'afiliaciones_corporativas'
                                    );
                                    continue;
                                }
                                
                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->birth_date);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->email != null) {

                                        //Ejecuto el envio de la notificacion
                                        // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                        set_time_limit(0);

                                        //Envio Principal al Cliente
                                        Mail::to($data[$k]->email)
                                            ->cc('solrodriguez@tudrencasa.com')
                                            ->send(new NotificationMasiveMailBirthday(
                                                $data[$k]->first_name,
                                                $rowsNotifications[$i]['file'],
                                                $data[$k]->email
                                            ));

                                        LogController::logSuccess($data[$k]->email);
                                        
                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info("No se envio el correo de cumpleaños para afiliados");
                                    continue;
                                }
                            }
                        }

                        // COLABORADORES -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'rrhh_colaboradors') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('fullName', 'emailCorporativo', 'fechaNacimiento')
                                ->get()
                                ->toArray();


                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->fechaNacimiento == null || $data[$k]->fechaNacimiento == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->fullName ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->fullName,
                                        $data[$k]->emailCorporativo,
                                        null,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'colaboradores'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->fechaNacimiento)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->fullName ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->fullName,
                                        $data[$k]->emailCorporativo,
                                        null,
                                        'Formato de fecha de cumpleaños inválido',
                                        'colaboradores'
                                    );
                                    continue;
                                }

                                if ($data[$k]->emailCorporativo == null || $data[$k]->emailCorporativo == '') {
                                    Log::warning("Email es nulo o vacio para el usuario: " . ($data[$k]->fullName ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->fullName,
                                        $data[$k]->emailCorporativo,
                                        null,
                                        'Email es nulo o vacio',
                                        'colaboradores'
                                    );
                                    continue;
                                }
                                
                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->fechaNacimiento);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->emailCorporativo != null) {

                                        //Ejecuto el envio de la notificacion
                                        // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                        set_time_limit(0);

                                        //Envio Principal al Cliente
                                        Mail::to($data[$k]->emailCorporativo)
                                            ->cc('solrodriguez@tudrencasa.com')
                                            ->send(new NotificationMasiveMailBirthday(
                                                $data[$k]->fullName,
                                                $rowsNotifications[$i]['file'],
                                                $data[$k]->emailCorporativo
                                            ));

                                        LogController::logSuccess($data[$k]->emailCorporativo);

                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info('No se envio el correo de cumpleaños para colaboradores');
                                    continue;
                                }

                            }
                        }

                        // PROVEEDORES -- Logica Actualizada parav envio de tarjeta de cumpleaños
                        // @version 2.1
                        if ($rowsNotifications[$i]['data_type'] == 'suppliers') {
                            $data = DB::table($rowsNotifications[$i]['data_type'])
                                ->select('name', 'correo_principal', 'afiliacion_proveedor')
                                ->get()
                                ->toArray();

                            //for para recorrer la data, tomar la fecha y enviar la notificacion
                            for ($k = 0; $k < count($data); $k++) {
                                //Validamos si esta cumpliendo años
                                if ($data[$k]->afiliacion_proveedor == null || $data[$k]->afiliacion_proveedor == '') {
                                    // Si el formato es inválido, registramos el nombre y saltamos a la siguiente persona
                                    Log::warning("Fecha de cumpleaños es nula o vacia para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->name,
                                        $data[$k]->correo_principal,
                                        null,
                                        'Fecha de cumpleaños es nula o vacia',
                                        'proveedores'
                                    );
                                    continue;
                                }

                                if (!UtilsController::validateDateFormat($data[$k]->afiliacion_proveedor)) {
                                    Log::warning("Formato de fecha inválido para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->name,
                                        $data[$k]->correo_principal,
                                        null,
                                        'Formato de fecha de cumpleaños inválido',
                                        'proveedores'
                                    );
                                    continue;
                                }

                                if ($data[$k]->correo_principal == null || $data[$k]->correo_principal == '') {
                                    Log::warning("Email es nulo o vacio para el usuario: " . ($data[$k]->name ?? 'Desconocido'));
                                    UtilsController::notificationFailed(
                                        'email',
                                        $data[$k]->name,
                                        $data[$k]->correo_principal,
                                        null,
                                        'Email es nulo o vacio',
                                        'proveedores'
                                    );
                                    continue;
                                }
                                
                                $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->afiliacion_proveedor);

                                if ($isBirthdayToday) {
                                    /**
                                     * En caso de que la data venga NULL
                                     */
                                    if ($data[$k]->correo_principal != null) {

                                        //Ejecuto el envio de la notificacion
                                        // self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                        set_time_limit(0);

                                        //Envio Principal al Cliente
                                        Mail::to($data[$k]->correo_principal)
                                            ->cc('solrodriguez@tudrencasa.com')
                                            ->send(new NotificationMasiveMailBirthday(
                                                $data[$k]->name,
                                                $rowsNotifications[$i]['file'],
                                                $data[$k]->correo_principal
                                            ));

                                        LogController::logSuccess($data[$k]->correo_principal);

                                    } else {
                                        continue;
                                    }
                                } else {
                                    Log::info('No se envio el correo de cumpleaños para proveedores');
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