<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MessageController extends Controller
{
    public static function messageIndividualQuote($link): string
    {
        $message = <<<HTML

        Hola, buenas tardes. 👋
        Espero se encuentre bien. 
        Este link contiene toda la información sobre la cotización solicitada, con todas las coberturas y tarifas detalladas. 
        Si tiene alguna duda o necesita más información, no dude en comunicarse con nosotros. 😊
        
        👉 {$link}

        Equipo Integracorp-TDC 
        📱 WhatsApp: (+58) 424 222 00 56
        ✉️ Email: comercial@tudrencasa.com 

        HTML;

        return $message;  
    }
}