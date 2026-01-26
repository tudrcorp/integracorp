<?php

namespace App\Http\Middleware;

use App\Http\Controllers\UtilsController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;


class DuplicatedSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = DB::table('sessions')->where('user_id', Auth::user()->id)->get();

        if (count($user) > 1) {

            $user = DB::table('sessions')->where('user_id', Auth::user()->id)->delete();

            UtilsController::notificacionSesionDuplicada(Auth::id());

            //Retorno al Login de Filament
            return redirect()->to(Filament::getLoginUrl());
        } else {
            return $next($request);
        }
    }
}
