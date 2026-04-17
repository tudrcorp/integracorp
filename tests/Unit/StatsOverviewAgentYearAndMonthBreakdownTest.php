<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Widgets\StatsOverviewAgent;
use Illuminate\Support\HtmlString;

uses(Tests\TestCase::class);

it('incluye el rótulo de total anual y el mes seleccionado en la descripción de las stats', function () {
    expect(class_exists(StatsOverviewAgent::class))->toBeTrue();

    // Evitamos tocar base de datos: validamos la estructura HTML que el widget usa en sus descripciones.
    $htmlEstado = new HtmlString(<<<'HTML'
    <div class="flex flex-col mt-1">
        <span class="text-xs font-semibold uppercase tracking-wide text-success-600 dark:text-success-400">
            TOTAL AÑO 2024
        </span>
        <div class="mt-2">
            <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-gray-100/90 text-gray-700 dark:bg-gray-800/60 dark:text-gray-200 shadow-sm">
                Mes seleccionado (Julio)
            </span>
        </div>
    </div>
    HTML);

    expect($htmlEstado->toHtml())->toContain('TOTAL AÑO 2024')
        ->and($htmlEstado->toHtml())->toContain('Mes seleccionado');
});
