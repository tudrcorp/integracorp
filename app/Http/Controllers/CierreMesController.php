<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CierreMesSlides;
use Illuminate\View\View;

class CierreMesController extends Controller
{
    public function __invoke(): View
    {
        return view('cierre-mes', [
            'slides' => collect(CierreMesSlides::all())
                ->map(function (array $slide): array {
                    if (($slide['preview']['type'] ?? '') === 'system' && isset($slide['preview']['path'])) {
                        $slide['preview']['url'] = url($slide['preview']['path']);
                    }

                    return $slide;
                })
                ->all(),
            'period' => 'Junio 2026',
        ]);
    }
}
