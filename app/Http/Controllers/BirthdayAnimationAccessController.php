<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Birthday\BirthdayCelebrationAccessIssuer;
use App\Support\Birthday\BirthdayCouponWhatsAppMessages;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BirthdayAnimationAccessController extends Controller
{
    public function __invoke(Request $request, string $token): View
    {
        $access = BirthdayCelebrationAccessIssuer::findAccessByPublicToken($token);

        if ($access === null || $access->isExpired()) {
            abort(404, 'Enlace de cumpleaños no válido o expirado.');
        }

        $guestName = trim($access->full_name);
        $showTweaks = filter_var($request->query('editor'), FILTER_VALIDATE_BOOL);

        return view('public.birthday-animation', [
            'guestName' => $guestName,
            'showTweaks' => $showTweaks,
            'scenesJson' => '[{"name":"Intro","dur":2,"desc":"El sobre sube al centro"},{"name":"Saludo","dur":3,"desc":"Aparece el saludo"},{"name":"Abrir","dur":2.5,"desc":"Se abre el sobre y asoman los cupones"},{"name":"Cupones","dur":4.5,"desc":"Los cupones se ordenan en la cuadrícula"},{"name":"Cierre","dur":3.5,"desc":"Aviso de cupones adjuntos, firma y logos"}]',
            'playbackJson' => '{"mode":"times","count":1}',
            'couponPayloadJson' => BirthdayCouponWhatsAppMessages::payloadForAnimation($access->couponTokens),
        ]);
    }
}
