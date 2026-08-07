<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\TechnologyAdvancesPresentationSlides;
use Illuminate\View\View;

class TechnologyAdvancesPresentationController extends Controller
{
    public function __invoke(): View
    {
        return view('technology-advances-presentation', [
            'slides' => TechnologyAdvancesPresentationSlides::all(),
        ]);
    }
}
