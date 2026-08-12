<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\AgenciasTdevPresentationSlides;
use App\Support\PresentationHubGate;
use Illuminate\View\View;

class AgenciasTdevPresentationController extends Controller
{
    public function __invoke(): View
    {
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        return view('agencias-tdev-presentation', [
            'slides' => AgenciasTdevPresentationSlides::all(),
            'access' => PresentationHubGate::access(),
            'idleTimeoutSeconds' => PresentationHubGate::idleTimeoutSeconds(),
        ]);
    }
}
