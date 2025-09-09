<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Capemiac;
use App\Mail\MyTestEmail;
use App\Mail\ExampleCsvEmail;
use App\Mail\AgentRegisterEmail;
use App\Models\DataNotification;
use App\Mail\AgencyRegisterEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendNotificacionWhatsApp;
use Filament\Notifications\Notification;
use App\Http\Controllers\UtilsController;

class NotificationController extends Controller
{
    static function agency_activated($phone, $email, $path_panel)
    {
        try {

            $path = config('parameters.INTEGRACORP_URL') . $path_panel;
            $body = <<<HTML

            🌟¡Bienvenido/a a Tu Dr. Group! 

            Estamos encantados de que tu experiencia y cartera de clientes se sumen a nuestra compañía. Tu profesionalismo es un gran valor y nos impulsa a seguir ofreciendo la mejor protección. 

            Usuario: {$email}
            Clave: 12345678
            Enlace: {$path} 

            Contáctanos para mayor información. 

            📱 WhatsApp: (+58) 424 227 1498
            ✉️ Email: comercial@tudrencasa.com

            Tu visión y nuestro respaldo harán una combinación poderosa para ofrecer soluciones excepcionales. ¡ Esperamos una relación exitosa y duradera! 🫱🏼‍🫲🏼 

            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'body' => $body
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
            $err = curl_error($curl);

            if ($err) {
                Log::error($err);
                return false;
            } else {
                $array = json_decode($response, true);
                if ($array['error'][0]) {
                    Log::info($array['error'][0]['to']);
                    $data = [
                        'action' => 'N-WApp => Registro publico de agencias',
                        'objeto' => 'NotificationController::agency_activated',
                        'message' => $array['error'][0]['to'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    UtilsController::notificacionToAdmin($data);
                    return false;
                }

                return true;
            }
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function agent_activated($phone, $email, $path_panel)
    {
        try {

            $path = config('parameters.INTEGRACORP_URL') . $path_panel;
            $body = <<<HTML

            ¡Hola! 👋   

            ✨ Bienvenido/a a Integracorp-TDC  ✨   

            Estamos encantados de tenerte aquí. Puede empezar tu auto gestion a travez de nuestro aplicativo:   
            Tus credenciales son:

            👉 *Usuario:* {$email}
            👉 *Clave:* 12345678
            👉 *Panel Administrativo:* {$path}
            
            Equipo Integracorp-TDC 
            📱 WhatsApp: (+58) 424 227 1498
            ✉️ Email: comercial@tudrencasa.com    

            ¡Esperamos que sea el inicio de una gran experiencia! 💼💡 

            HTML;

            /**
             * Jobs para el envido de notificaciones
             * Canal: whatsapp
             * 
             * @var [body]
             * @var [phone]
             * @var [document]
             * 
             */
            $user_id = Auth::user()->id;
            $jobWhatsApp = SendNotificacionWhatsApp::dispatch($user_id, $body, $phone);

            if (isset($jobWhatsApp)) {
                return $response = [
                    'success' => true,
                    'message' => 'La Notificacion de activacion fue enviada con exito',
                    'color' => 'success'
                ];
            } else {
                return $response = [
                    'success' => false,
                    'message' => 'La Notificacion de activacion no fue enviada, por favor comunicarse con el administrador del sistema',
                    'color' => 'danger'
                ];
            }
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::send_link_preAffiliation()', $th->getMessage());
        }
    }

    static function send_link_preAffiliation($phone, $fullname)
    {
        try {

            $body = <<<HTML

            *Saludos, Sr(a): {$fullname}*

            Le informamos que usted se encuentra en proceso de afiliación, para poder seguir adelante debe ingresar al siguiente link:

            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'body' => $body
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
            $err = curl_error($curl);
            $res = json_decode($response, true);

            curl_close($curl);

            if (isset($res['sent']) and $res['sent'] == 'true') {
                LogController::log(Auth::user()->id, 'NOTIFICACION-WP-PRE-AFILIACION', 'NotififcacionController::send_link_preAffiliation()', $response);
                return $response = [
                    'success' => true,
                    'message' => 'El link de pre-afiliacion fue enviado con exito',
                    'color' => 'success'
                ];
            }

            if (isset($res['error'])) {
                LogController::log(Auth::user()->id, 'NOTIFICACION-WP-PRE-AFILIACION', 'NotififcacionController::send_link_preAffiliation()', $response);
                return $response = [
                    'success' => false,
                    'message' => 'Falla al enviar el link de pre-afiliacion, por favor comunicarse con el administrador del sistema',
                    'color' => 'danger'
                ];
            }

            if (isset($err)) {
                LogController::log(Auth::user()->id, 'EXCEPCION-CURL', 'NotififcacionController::send_link_preAffiliation()', $err);
            }
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::send_link_preAffiliation()', $th->getMessage());
        }
    }

    static function sendEmail_propuesta_economica($email, $record, $data)
    {
        try {

            $details = [
                'name' => $record->full_name,
                'message' => 'Este es un correo de prueba enviado desde Laravel.',
                'date' => now()->format('d-m-Y'),
                'data' => $data
            ];
            // dd($details);

            // Enviar el correo
            Mail::to($email)->send(new MyTestEmail($details));

            return 'Correo enviado correctamente.';
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::send_link_preAffiliation()', $th->getMessage());
        }
    }

    static function send_email_agency_register($link, $email)
    {
        try {

            $content = [
                'link' => $link,
            ];

            // Enviar el correo
            Mail::to($email)->send(new AgencyRegisterEmail($content));

            return true;
            //code...
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::send_email_agency_register()', $th->getMessage());
        }
    }

    static function send_email_agent_register($link, $email)
    {
        try {

            $content = [
                'link' => $link,
            ];

            // Enviar el correo
            Mail::to($email)->send(new AgentRegisterEmail($content));

            return true;
            //code...
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::send_email_agency_register()', $th->getMessage());
        }
    }

    static function send_link_agency_register_wp($link, $phone)
    {
        try {

            $body = <<<HTML

                ¡Hola! 👋   

                ✨ Bienvenido/a a Integracorp-TDC  ✨   

                Estamos encantados de tenerte aquí. Para comenzar a disfrutar de todos nuestros beneficios y servicios, te invitamos a completar tu registro haciendo clic en el siguiente enlace:   

                👉 {$link}     

                Si tienes dudas o necesitas ayuda, no dudes en contactarnos. Estamos para servirte. 🚀   

                Equipo Integracorp-TDC 
                📱 WhatsApp: (+58) 424 227 1498
                ✉️ Email: comercial@tudrencasa.com    

                ¡Esperamos que sea el inicio de una gran experiencia! 💼💡 

            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'body' => $body
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
            $err = curl_error($curl);

            if ($err) {
                Log::error($err);
                return false;
            } else {
                $array = json_decode($response, true);
                if ($array['error'][0]) {
                    Log::info($array['error'][0]['to']);
                    $data = [
                        'action' => 'N-WApp => Envio de link para registro del agencia',
                        'objeto' => 'NotificationController::send_link_agency_register_wp',
                        'message' => $array['error'][0]['to'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    UtilsController::notificacionToAdmin($data);
                    return false;
                }

                return true;
            }

            
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::send_link_preAffiliation()', $th->getMessage());
        }
    }

    /**
     * Notificacion de link de registro de agente
     * Canal: Whatsapp   
     * 
     * @author TuDrEnCasa
     * @version 4.0
     * 
     * @return boolean
     */
    static function send_link_agent_register_wp($link, $phone)
    {
        try {

            $body = <<<HTML

            ¿Listo para transformar tus herramientas como asesor?

            Te invitamos a registrarte en nuestra plataforma web, diseñada específicamente para profesionales como tú. Hemos creado una plataforma online donde la eficiencia, la conexión y el crecimiento se encuentran.

            El proceso es rápido, sencillo y te abrirá las puertas a un sinfín de posibilidades para hacer crecer tu portafolio.


            Enlace: 
            {$link}     

            Contáctanos para mayor información. 

            📱 WhatsApp: (+58) 424 227 1498
            ✉️ Email: 
            comercial@tudrencasa.com
            comercial@tudrenviajes.com

            ¡Esperamos verte pronto en nuestra plataforma!

            Atentamente,
            Gerencia Comercial Tu Dr. Group 🫱🏼‍🫲🏼 

            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                Log::error($err);
                return false;
            } else {
                $array = json_decode($response, true);
                if ($array['error'][0]) {
                    Log::info($array['error'][0]['to']);
                    $data = [
                        'action' => 'N-WApp => Envio de link para registro del agente',
                        'objeto' => 'NotificationController::send_link_agent_register_wp',
                        'message' => $array['error'][0]['to'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    UtilsController::notificacionToAdmin($data);
                    return false;
                }

                return true;
            }
            
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::send_link_preAffiliation()', $th->getMessage());
        }
    }



    /**
     * Notificacion de link de registro de agente
     * Canal: Email
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return boolean
     */
    static function send_email_example_file_csv($email)
    {
        try {

            $content = [
                'link' => 'gustavo',
            ];

            // Enviar el correo
            Mail::to($email)->send(new ExampleCsvEmail($content));

            return true;
            //code...
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::send_email_agency_register()', $th->getMessage());
        }
    }

    /**
     * NOTOFICACIONES:
     * MODULO: Cotizaciones Individuales
     * -----------------------------------
     * 
     * Gripo de Notificaciones que se envian via Whatsapp
     * desde el modulo de Cotizaciones Individuales
     * 
     * @version 1.0
     * @since 1.0
     * 
     * @param $phone
     * @param $message
     * @return bool
     */

    static function sendQuote($phone, $message)
    {

        try {

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'body' => $message,
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

            curl_close($curl);

            curl_close($curl);

            if ($err) {
                Log::error($err);
                return false;
            } else {
                Log::info($response);
                return true;
            }

            //code...
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::sendCotizaPlanInicial()', $th->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    static function massNotificacionSend($record)
    {

        try {

            $infoArray = $record->toArray();
            
            $array = DataNotification::where('mass_notification_id', $record->id)->get()->toArray();

            for ($i = 0; $i < count($array); $i++) {

                if ($infoArray['header_title'] != null) {

                    $record->heading = $infoArray['header_title'] . ' ' . $array[$i]['fullName'];
                    $body = <<<HTML
    
                    *{$record->heading}* 
    
                    {$record->content}
    
                    HTML;

                    dd(config('parameters.INTEGRACORP_URL') . '/storage/' . $infoArray['image']);

                    $params = array(
                        'token' => 'yuvh9eq5kn8bt666',
                        'to' => $array[$i],
                        'image' => config('parameters.INTEGRACORP_URL') . '/storage/'.$infoArray['image'],
                        'caption' => $body
                    );
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => "https://api.ultramsg.com/instance117518/messages/image",
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
                        'token' => 'yuvh9eq5kn8bt666',
                        'to' => $array[$i],
                        // 'image' => 'https://tudrenviajes.com/images/logo_3.png',14986
                        'image' => 'https://tudrgroup.com/images/logoTDG.png',
                        'caption' => $body
                    );
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => "https://api.ultramsg.com/instance117518/messages/image",
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
     * NOTOFICACIONES:
     * MODULO: Cotizaciones Corporativas
     * -----------------------------------
     * 
     * Gripo de Notificaciones que se envian via Whatsapp
     * desde el modulo de Cotizaciones Corporativas
     * 
     * @version 1.0
     * @since 1.0
     * 
     * @param $phone
     * @param $message
     * @return bool
     */

    static function sendUploadDataCorporate($agent, $code)
    {
        try {

            $body = <<<HTML

            El agente *{$agent}* acaba de subir el archivo con la data asociada a la cotizacion nro: *{$code}*.
            
            El archivo ya está disponible para su revisión y procesamiento. Agradecemos su atención y rapidez para seguir avanzando en este proceso. 
            
            Muchas gracias. 🙌

            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => config('parameters.PHONE_COTIZACIONES_AFILIACIONES'),
                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                Log::error($err);
                return false;
            } else {
                $array = json_decode($response, true);
                if ($array['error'][0]) {
                    Log::info($array['error'][0]['to']);
                    $data = [
                        'action' => 'N-WApp => Envio de link interactivo de Cotizacion Individual',
                        'objeto' => 'NotificationController::sendUploadDataCorporate',
                        'message' => $array['error'][0]['to'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    UtilsController::notificacionToAdmin($data);
                    return false;
                }

                return true;
            }
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    /**
     * NOTOFICACIONES:
     * MODULO: Cotizaciones Individuales
     * -----------------------------------
     * 
     * Gripo de Notificaciones que se envian via Whatsapp
     * desde el modulo de Cotizaciones Individuales
     * con un link interactivo para el agente o el cliente
     * donde podra encontrar la cotizacion solicitada
     * en formato blade.php
     * 
     * @version 1.0
     * @since 1.0
     * 
     * @param $phone
     * @param $link
     * @return bool
     */

    static function sendLinkIndividualQuote($phone, $link)
    {
        try {

            $body = <<<HTML

            Hola!👋

            Gracias por tu solicitud.
            En este mensaje encontrarás el enlace interactivo de la cotización que solicitaste. Solo debes hacer clic en el botón para ver todos los detalles.

            $link
            
            El archivo ya está disponible para su revisión y procesamiento. Agradecemos su atención y rapidez para seguir avanzando en este proceso. 
            
            Muchas gracias. 🙌
 
            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($response) {

                Log::info($response);
                $data = [
                    'action' => 'sendLinkIndividualQuote',
                    'objeto' => 'NotificationController::sendLinkIndividualQuote',
                    'message' => $response,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'success'
                ];
                UtilsController::notificacionToAdmin($data);
                return true;
            }

            if ($err) {

                Log::error($err);
                $data = [
                    'action' => 'assignedCase',
                    'objeto' => 'NotificationController::assignedCase',
                    'message' => $err,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'error'
                ];
                UtilsController::notificacionToAdmin($data);
                return false;
            }
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function createdIndividualQuote($code, $agent)
    {
        try {

            $body = <<<HTML

            Hola!👋

            El Agente: *{$agent}* ha creado una cotización individual con el siguiente codigo: 
            
            *{$code}*
            
            Por favor, comuniquese con el agente para continuar con el proceso.
         
            Muchas gracias. 🙌
 
            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => config('parameters.PHONE_COTIZACIONES_AFILIACIONES'),
                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($response) {

                Log::info($response);
                $data = [
                    'action' => 'createdIndividualQuote',
                    'objeto' => 'NotificationController::createdIndividualQuote',
                    'message' => $response,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'success'
                ];
                UtilsController::notificacionToAdmin($data);
                return true;
            }

            if ($err) {

                Log::error($err);
                $data = [
                    'action' => 'assignedCase',
                    'objeto' => 'NotificationController::assignedCase',
                    'message' => $err,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'error'
                ];
                UtilsController::notificacionToAdmin($data);
                return false;
            }
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function createdCorporateQuote($code, $agent)
    {
        try {

            $body = <<<HTML

            Hola!👋

            El Agente: *{$agent}* ha creado una cotización corporativa con el siguiente codigo: 
            
            *{$code}*
            
            Por favor, comuniquese con el agente para continuar con el proceso.
         
            Muchas gracias. 🙌
 
            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => config('parameters.PHONE_COTIZACIONES_AFILIACIONES'),
                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($response) {

                Log::info($response);
                $data = [
                    'action' => 'createdCorporateQuote',
                    'objeto' => 'NotificationController::createdCorporateQuote',
                    'message' => $response,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'success'
                ];
                UtilsController::notificacionToAdmin($data);
                return true;
            }

            if ($err) {

                Log::error($err);
                $data = [
                    'action' => 'assignedCase',
                    'objeto' => 'NotificationController::assignedCase',
                    'message' => $err,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'error'
                ];
                UtilsController::notificacionToAdmin($data);
                return false;
            }
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function createdRequestDressTaylor($code, $agent, $observations)
    {
        try {

            $body = <<<HTML

            Hola!👋

            El Agente: *{$agent}* ha generado una Solicitud Dress-Taylor con el siguiente codigo: 
            
            *{$code}*

            *Caracteristicas:*
            {$observations}
            
            Por favor, comuniquese con el agente para continuar con el proceso.
         
            Muchas gracias. 🙌
 
            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                // 'to' => config('parameters.PHONE_COTIZACIONES_AFILIACIONES'),
                'to' => '+584241869168',

                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($response) {

                Log::info($response);
                $data = [
                    'action' => 'createdIndividualQuote',
                    'objeto' => 'NotificationController::createdIndividualQuote',
                    'message' => $response,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'success'
                ];
                UtilsController::notificacionToAdmin($data);
                return true;
            }

            if ($err) {

                Log::error($err);
                $data = [
                    'action' => 'assignedCase',
                    'objeto' => 'NotificationController::assignedCase',
                    'message' => $err,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'error'
                ];
                UtilsController::notificacionToAdmin($data);
                return false;
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function saddObervationToIndividualQuote($code, $agent, $observation)
    {
        try {

            $body = <<<HTML

            Hola!👋

            El Agente: *{$agent}* ha registro una observación a la cotización individual con el siguiente codigo: 
            
            *{$code}*

            *Observación:*
            {$observation}
            
            Por favor, comuniquese con el agente para continuar con el proceso.
         
            Muchas gracias. 🙌
 
            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => config('parameters.PHONE_COTIZACIONES_AFILIACIONES'),
                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($response) {

                Log::info($response);
                $data = [
                    'action' => 'saddObervationToIndividualQuote',
                    'objeto' => 'NotificationController::saddObervationToIndividualQuote',
                    'message' => $response,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'success'
                ];
                UtilsController::notificacionToAdmin($data);
                return true;
            }

            if ($err) {

                Log::error($err);
                $data = [
                    'action' => 'saddObervationToIndividualQuote',
                    'objeto' => 'NotificationController::saddObervationToIndividualQuote',
                    'message' => $err,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'error'
                ];
                UtilsController::notificacionToAdmin($data);
                return false;
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function saddObervationToCorporateQuote($code, $agent, $observation)
    {
        try {

            $body = <<<HTML

            Hola!👋

            El Agente: *{$agent}* ha registro una observación a la cotización corporativa con el siguiente codigo: 

            *{$code}*

            *Observación:*
            {$observation}

            Por favor, comuniquese con el agente para continuar con el proceso.

            Muchas gracias. 🙌
 
            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => config('parameters.PHONE_COTIZACIONES_AFILIACIONES'),
                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($response) {

                Log::info($response);
                $data = [
                    'action' => 'saddObervationToCorporateQuote',
                    'objeto' => 'NotificationController::saddObervationToCorporateQuote',
                    'message' => $response,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'success'
                ];
                UtilsController::notificacionToAdmin($data);
                return true;
            }

            if ($err) {

                Log::error($err);
                $data = [
                    'action' => 'saddObervationToCorporateQuote',
                    'objeto' => 'NotificationController::saddObervationToCorporateQuote',
                    'message' => $err,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'error'
                ];
                UtilsController::notificacionToAdmin($data);
                return false;
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function documentUploadReminder($phone, $agent, $document_list)
    {
        try {

            $body = <<<HTML

            Hola, *{$agent}* 👋 

            Esperamos que estés muy bien. 😊 

            Solo queremos recordarte que es importante mantener tu información actualizada para seguir brindándote el mejor apoyo y servicio. 

            Por eso, te pedimos amablemente que cargues los siguientes documentos pendientes en tu perfil:

            *{$document_list}*

            ➡️ Puedes subirlos fácilmente desde tu panel de control en unos pocos clics.

            ¡Gracias por tu colaboración! 🙌
                
            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($response) {

                Log::info($response);
                $data = [
                    'action' => 'saddObervationToCorporateQuote',
                    'objeto' => 'NotificationController::saddObervationToCorporateQuote',
                    'message' => $response,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'success'
                ];
                UtilsController::notificacionToAdmin($data);
                return true;
            }

            if ($err) {

                Log::error($err);
                $data = [
                    'action' => 'saddObervationToCorporateQuote',
                    'objeto' => 'NotificationController::saddObervationToCorporateQuote',
                    'message' => $err,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'error'
                ];
                UtilsController::notificacionToAdmin($data);
                return false;
            }
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function assignedCase($phone, $doctor, $code, $reason, $name_patient)
    {
        // dd($phone, $doctor, $code, $reason);
        try {
            

            $body = <<<HTML

            ¡Hola Dr. *{$doctor}*! 👋   

            Te informamos que el caso *#{$code}* acaba de ser asignado a tu equipo.   

            El paciente *{$name_patient}* está estable y listo para evaluación. Puedes revisar todos los detalles en tu panel de control.
            
            *Motivo de la Consulta:* 
            *{$reason}*

            ¡Gracias por tu colaboración! 🙌
                
            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'body' => $body
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
            $err = curl_error($curl);

            curl_close($curl);

            if ($response) {

                Log::info($response);
                $data = [
                    'action' => 'assignedCase',
                    'objeto' => 'NotificationController::assignedCase',
                    'message' => $response,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'success'
                ];
                UtilsController::notificacionToAdmin($data);
                return true;
            }

            if ($err) {
                
                Log::error($err);
                $data = [
                    'action' => 'assignedCase',
                    'objeto' => 'NotificationController::assignedCase',
                    'message' => $err,
                    'created_at' => date('Y-m-d H:i:s'),
                    'icon' => 'error'
                ];
                UtilsController::notificacionToAdmin($data);
                return false;
            } 

        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function notificationVideo()
    {

        try {

            $array = Guest::all()->toArray();
            dd($array);

            for ($i = 0; $i < count($array); $i++) {

                $body = <<<HTML

                Hola!👋

                Apreciado/a: *{$array[$i]['firstName']}*

                Usted ha sido seleccionado para esta misión con Tu Dr. Group.
                Donde la innovación será parte de nuestras lineas de negocios de salud y viajes.

                ¿ACEPTAS LA MISIÓN?🕵🏼 Ingresa nuestro sitio web https://tudrgroup.com
                Y llena el formulario

                Más información sobre este encuentro aquí 👇🏼
                https://wa.me/+584142510805
    
                HTML;

                $params = array(
                    'token' => 'yuvh9eq5kn8bt666',
                    'to' => $array[$i]['phone'],
                    'image' => 'https://tudrgroup.com/images/imageNotificacionDos.jpg',
                    'caption' => $body
                );
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://api.ultramsg.com/instance117518/messages/image",
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
                Log::info($array[$i]['phone']);
                Log::error($err);
            }

            curl_close($curl);


        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function notificationImage()
    {

        set_time_limit(0);

        try {

            $array = Guest::all()->toArray();

            for ($i = 0; $i < count($array); $i++) {
                
                $body = <<<HTML

                Apreciado/a: *{$array[$i]['firstName']}*

                Desde Tu Doctor Group estamos muy emocionados de que seas parte de esta nueva era de negocios.

                Estas cordialmente invitado a nuestro encuentro especial:

                🗓️Viernes 12 de Septiembre.
                ⏰ A las 6 pm.
                📍 Centro Lido, Av. Francisco de Miranda Torre A, Piso 15, El Rosal, Caracas.
                (En piso 13 hay una escala para tomar otro ascensor, allí estará una ejecutiva para guiarte).
                🎩 Casual business será nuestro estilo.

                ¡Nos vemos este viernes!💙🔥
    
                HTML;

                $params = array(
                    'token' => 'yuvh9eq5kn8bt666',
                    'to' => $array[$i]['phone'],
                    'image' => 'https://tudrgroup.com/images/nuevaInvitacion.jpg',
                    'caption' => $body
                );
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://api.ultramsg.com/instance117518/messages/image",
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

                curl_close($curl);

                Log::info($response);
                Log::info($array[$i]['phone']);
                Log::error($err);
            
            }

            curl_close($curl);
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    
}