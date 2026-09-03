<?php

declare(strict_types=1);

use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Renderiza la página del plan de verdad. Es lo único que prueba que la matriz,
 * los precios y las condiciones salgan juntos y bien, porque la plantilla arma
 * la página entera en HTML en lugar de apoyarse en una imagen.
 *
 * De solo lectura: monta el componente con datos en memoria y no guarda nada.
 */
function datosAgrupadosDePrueba(Plan $plan): Illuminate\Support\Collection
{
    $coberturas = Coverage::query()->where('plan_id', $plan->getKey())->orderBy('price')->get();
    $rangos = AgeRange::query()->where('plan_id', $plan->getKey())->get();

    $agrupado = [];

    foreach ($rangos as $rango) {
        $filas = [];

        if ($coberturas->isEmpty()) {
            $filas[] = (object) [
                'age_range' => $rango->range,
                'total_persons' => 3,
                'subtotal_anual' => 480,
                'subtotal_biannual' => 260,
                'subtotal_quarterly' => 140,
            ];
        } else {
            foreach ($coberturas as $cobertura) {
                $filas[] = (object) [
                    'age_range' => $rango->range,
                    'coverage' => $cobertura->price,
                    'total_persons' => 3,
                    'subtotal_anual' => 360,
                ];
            }
        }

        $agrupado[(string) $rango->range] = collect($filas);
    }

    return collect($agrupado);
}

function renderizarPaginaDePlan(Plan $plan): string
{
    return (string) Livewire::mount('planes-cotizacion-estructura', [
        'data' => datosAgrupadosDePrueba($plan),
        'name' => 'JUAN PEREZ',
        'name_user' => 'AGENTE DEMO',
        'number_control' => '0001',
        'planId' => $plan->getKey(),
    ]);
}

it('compone la página de un plan con coberturas', function (): void {
    $plan = Plan::query()->get()->first(
        fn (Plan $p): bool => $p->pricingMode()->usesCoverages()
            && $p->coverages()->exists()
            && Fee::query()->where('plan_id', $p->getKey())->whereNotNull('coverage_id')->exists(),
    );

    if ($plan === null) {
        $this->markTestSkipped('No hay un plan con coberturas y tarifas en la base.');
    }

    $html = renderizarPaginaDePlan($plan);

    expect($html)
        // El título dice el nombre real del plan, no «Plan Accidentes».
        ->toContain('Propuesta Económica - '.$plan->description)
        ->toContain('BENEFICIOS DEL PLAN')
        ->toContain('Beneficios para enfermedades agudas')
        ->toContain('Zona de cobertura Venezuela')
        // Con coberturas, el precio se desglosa por columna.
        ->toContain('TARIFA ANUAL');
});

it('compone la página de un paquete de beneficios con tarifa plana', function (): void {
    $plan = Plan::query()->get()->first(
        fn (Plan $p): bool => $p->isBenefitPackage()
            && Fee::query()->where('plan_id', $p->getKey())->whereNull('coverage_id')->exists()
            && AgeRange::query()->where('plan_id', $p->getKey())->exists(),
    );

    if ($plan === null) {
        $this->markTestSkipped('No hay un paquete de beneficios con tarifas en la base.');
    }

    $html = renderizarPaginaDePlan($plan);

    expect($html)
        ->toContain('Propuesta Económica - '.$plan->description)
        ->toContain('BENEFICIOS DEL PLAN')
        // Sin coberturas no hay columnas que desglosar: una sola tarifa por rango.
        ->toContain('TOTAL SEMESTRAL')
        ->not->toContain('TARIFA ANUAL');
});

it('avisa en vez de dibujar una tabla vacía si el plan no tiene estructura', function (): void {
    Illuminate\Support\Facades\DB::beginTransaction();

    try {
        $vacio = Plan::query()->create([
            'code' => 'PEST-RENDER-VACIO',
            'description' => 'PLAN SIN ESTRUCTURA',
            'business_unit_id' => 1,
            'type' => 'BASICO',
            'status' => 'ACTIVO',
            'created_by' => 'pest',
            'pricing_mode' => App\Enums\PlanPricingMode::Coberturas->value,
            'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
        ]);

        $html = (string) Livewire::mount('planes-cotizacion-estructura', [
            'data' => collect(),
            'name' => 'JUAN PEREZ',
            'name_user' => 'AGENTE DEMO',
            'number_control' => '0001',
            'planId' => $vacio->getKey(),
        ]);

        expect($html)->toContain('todavía no tiene beneficios y coberturas configurados');
    } finally {
        Illuminate\Support\Facades\DB::rollBack();
    }
});

it('no repite las trampas de DomPDF que recortaban la hoja', function (): void {
    $pagina = (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/views/livewire/planes-cotizacion-estructura.blade.php',
    );
    $matriz = (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/views/livewire/partials/quote-pdf-benefits-table.blade.php',
    );

    expect($pagina)
        // Los márgenes van con padding en un <td>: con padding en un div al
        // 100% del ancho, DomPDF desbordaba y recortaba la última columna.
        ->toContain('page-frame')
        ->toContain('.page-cell')
        // DejaVu Sans es la fuente embebida que trae el glifo del check; con
        // Arial, DomPDF lo imprimía como «?».
        ->toContain('font-family: DejaVu Sans');

    // Un nombre de beneficio largo estiraba la tabla fuera de la hoja.
    expect($matriz)->toContain('table-layout: fixed');
    expect($pagina)->toContain('table-layout: fixed');

    // La matriz armada desde el catálogo de beneficios va más chica que el
    // resto de la propuesta, tanto en individual como en corporativa.
    expect($matriz)
        ->toContain("\$fontSize = \$compact ? '5.5pt' : (\$isDense ? '6pt' : '7pt')")
        ->toContain("\$checkFontSize = \$compact ? '7pt' : (\$isDense ? '8pt' : '9pt')");

    expect($pagina)
        ->toContain('.data-table th,')
        ->toContain('font-size: 7pt;');
});

it('alinea los totales con las tarifas en una sola tabla', function (): void {
    $plan = Plan::query()->get()->first(
        fn (Plan $p): bool => $p->pricingMode()->usesCoverages()
            && $p->coverages()->exists()
            && Fee::query()->where('plan_id', $p->getKey())->whereNotNull('coverage_id')->exists(),
    );

    if ($plan === null) {
        $this->markTestSkipped('No hay un plan con coberturas y tarifas en la base.');
    }

    $html = renderizarPaginaDePlan($plan);

    // Tarifas y totales comparten tabla: en tablas separadas cada una calculaba
    // sus columnas por su cuenta y los totales quedaban corridos.
    $tablas = preg_split('/<table[^>]*class="data-table"/', $html);
    $tablaDePrecios = end($tablas);

    expect($tablaDePrecios)
        ->toContain('TARIFA ANUAL')
        ->toContain('TARIFA GRUPAL ANUAL')
        ->toContain('TARIFA GRUPAL SEMESTRAL')
        ->toContain('TARIFA GRUPAL TRIMESTRAL');
});

it('usa el logo institucional de las cotizaciones', function (): void {
    $pagina = (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/views/livewire/planes-cotizacion-estructura.blade.php',
    );

    expect($pagina)->toContain("public_path('image/logoNewPdf.png')")
        ->and(file_exists(dirname(__DIR__, 2).'/public/image/logoNewPdf.png'))->toBeTrue();
});
