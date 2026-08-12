<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PresentationHubGate;
use App\Support\TechnologyAdvancesPresentationSlides;
use Illuminate\View\View;

class TechnologyAdvancesPresentationController extends Controller
{
    public function __invoke(): View
    {
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        return view('technology-advances-presentation', [
            'slides' => TechnologyAdvancesPresentationSlides::all(),
            'access' => PresentationHubGate::access(),
            'idleTimeoutSeconds' => PresentationHubGate::idleTimeoutSeconds(),
        ]);
    }
}
