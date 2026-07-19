<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ScrumPresentationSlides;
use Illuminate\View\View;

class ScrumPresentationController extends Controller
{
    public function __invoke(): View
    {
        return view('scrum-presentation', [
            'slides' => ScrumPresentationSlides::all(),
        ]);
    }
}
