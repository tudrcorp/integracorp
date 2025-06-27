<?php

namespace App\Http\Controllers;

use App\Mail\MyTestEmail;
use App\Mail\ExampleCsvEmail;
use App\Mail\AgentRegisterEmail;
use App\Mail\AgencyRegisterEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendNotificacionWhatsApp;

class NotificationController extends Controller
{
    static function agency_activated($code, $phone, $email, $path_panel)
    {
        try {

            $path = env('APP_URL') . $path_panel;
            $body = <<<HTML

            ¡Hola! 👋   

            ✨ Bienvenido/a a Integracorp-TDC  ✨   

            Estamos encantados de que tu agencia pertenezca a nuestro equipo de trabajo. Puede empezar tu auto gestion a travez de nuestro aplicativo:   
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
            $jobWhatsApp = SendNotificacionWhatsApp::dispatch($code, $body, $phone);

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

    static function agent_activated($phone, $email, $path_panel)
    {
        try {

            $path = env('APP_URL') . $path_panel;
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

    static function send_link_agent_register_wp($link, $phone)
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
     * @return void
     */

    static function sendCotizaPlanInicial($phone, $message, $link, $name_pdf)
    {
        try {


            $params = array(
                'token'     => config('parameters.TOKEN'),
                'to'        => $phone,
                'filename'  => $name_pdf,
                'document'  => $link,
                'caption'   => $message
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => env('CURLOPT_URL_SEND_DOCUMENT'),
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
                echo "cURL Error #:" . $err;
            } else {
                echo $response;
            }

            //code...
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::sendCotizaPlanInicial()', $th->getMessage());
        }
    }

    static function sendCotizaPlanIdeal($phone, $message, $link, $name_pdf)
    {
        try {


            $params = array(
                'token'     => config('parameters.TOKEN'),
                'to'        => $phone,
                'filename'  => $name_pdf,
                'document'  => $link,
                'caption'   => $message
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => env('CURLOPT_URL_SEND_DOCUMENT'),
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
                echo "cURL Error #:" . $err;
            } else {
                echo $response;
            }

            //code...
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::sendCotizaPlanInicial()', $th->getMessage());
        }
    }

    static function sendCotizaPlanEspecial($phone, $message, $link, $name_pdf)
    {
        try {


            $params = array(
                'token'     => config('parameters.TOKEN'),
                'to'        => $phone,
                'filename'  => $name_pdf,
                'document'  => $link,
                'caption'   => $message
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => env('CURLOPT_URL_SEND_DOCUMENT'),
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
                echo "cURL Error #:" . $err;
            } else {
                echo $response;
            }

            //code...
        } catch (\Throwable $th) {
            LogController::log(Auth::user()->id, 'EXCEPTION', 'NotififcacionController::sendCotizaPlanInicial()', $th->getMessage());
        }
    }
}
