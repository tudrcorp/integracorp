<?php

declare(strict_types=1);

use App\Filament\Business\Resources\IndividualQuotes\Pages\CreateIndividualQuote;
use App\Models\IndividualQuote;
use App\Models\User;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use App\Support\Quotes\IndividualQuoteUpgrades;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Upgrades en la cotización individual, con datos reales: Plan Especial (id 3: 0 A 30, 31 a 65,
 * 66 a 74, 75 a 85), «30% de cobertura de preexistencia» (cobertura 2: 0 a 120 años, US$ 114) y
 * «Funerario 2K» (cobertura 3: 18 a 64 años, US$ 35). Escribe dentro de una transacción que
 * siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    if (DB::table('upgrade_coverages')->whereIn('id', [2, 3])->where('status', 'ACTIVO')->count() < 2 || ! DB::table('age_ranges')->where('plan_id', 3)->exists()) {
        $this->markTestSkipped('Esta base no tiene los upgrades o el Plan Especial de referencia.');
    }

    $this->actingAs(User::factory()->create([
        'email' => 'qa.upgrades.cotizacion.'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));
    Filament::setCurrentPanel('business');
});

afterEach(function (): void {
    DB::rollBack();
});

function especialRows(): array
{
    return [
        ['age_range_id' => 5, 'total_persons' => 2],
        ['age_range_id' => 6, 'total_persons' => 1],
        ['age_range_id' => 8, 'total_persons' => null],
        ['age_range_id' => 2, 'total_persons' => 4],
    ];
}

it('arma los rangos con su población, ignora filas vacías y rangos de otro plan', function (): void {
    expect(IndividualQuoteUpgrades::rateRows('3', especialRows(), []))->toBe([
        ['age_range_label' => '0 A 30', 'population' => 2],
        ['age_range_label' => '31 a 65', 'population' => 1],
    ]);
});

it('en múltiples planes suma la población de rangos iguales', function (): void {
    $rows = IndividualQuoteUpgrades::rateRows('CM', [], [
        ['plan_id' => 2, 'age_range_id' => 2, 'total_persons' => 2],
        ['plan_id' => 27, 'age_range_id' => 31, 'total_persons' => 3],
        ['plan_id' => 3, 'age_range_id' => 6, 'total_persons' => 1],
    ]);

    expect(collect($rows)->firstWhere('age_range_label', '0 a 45')['population'])->toBe(5)
        ->and($rows)->toHaveCount(2);
});

it('un upgrade que cubre todas las edades se cobra a toda la población y se paga completo en el primer pago', function (): void {
    $quote = IndividualQuoteUpgrades::quote([['line_key' => (string) Str::uuid(), 'upgrade_coverage_id' => 2]], IndividualQuoteUpgrades::rateRows('3', especialRows(), []));

    expect($quote['errors'])->toBe([])
        ->and($quote['annual_total'])->toBe(342.0)
        ->and($quote['upgrades'][0]['persons'])->toBe(3)
        ->and(App\Support\Upgrades\UpgradeInstallments::schedule(400.0, 342.0))->toBe([
            'ANUAL' => ['label' => 'Anual', 'installments' => 1, 'first' => 742.0, 'next' => null],
            'SEMESTRAL' => ['label' => 'Semestral', 'installments' => 2, 'first' => 542.0, 'next' => 200.0],
            'TRIMESTRAL' => ['label' => 'Trimestral', 'installments' => 4, 'first' => 442.0, 'next' => 100.0],
        ])
        ->and(App\Support\Upgrades\UpgradeInstallments::first(100.0, 35.0, 'MENSUAL'))->toBe(43.33)
        ->and(App\Support\Upgrades\UpgradeInstallments::regular(100.0, 'MENSUAL'))->toBe(8.33)
        ->and(App\Support\Upgrades\UpgradeInstallments::first(400.0, 35.0, null))->toBe(435.0);
});

it('un upgrade que cubre solo parte de los rangos no se cobra sin inclusión manual, y con ella cobra lo declarado', function (): void {
    $rateRows = IndividualQuoteUpgrades::rateRows('3', especialRows(), []);
    $line = ['line_key' => (string) Str::uuid(), 'upgrade_coverage_id' => 3];

    $sinManual = IndividualQuoteUpgrades::quote([$line], $rateRows);

    expect($sinManual['annual_total'])->toBe(0.0)
        ->and(collect($sinManual['upgrades'][0]['rows'])->pluck('status')->all())->toBe([PlanGeneratorUpgrades::STATUS_PARTIAL, PlanGeneratorUpgrades::STATUS_PARTIAL])
        ->and($sinManual['errors'][0])->toContain('no se aplica a ninguna persona');

    $line['manual_ranges'] = [['key' => PlanGeneratorUpgrades::rangeKey('0 A 30'), 'label' => '0 A 30', 'persons' => 1, 'note' => 'Una de las dos personas tiene 25 años según la cédula.']];
    $conManual = IndividualQuoteUpgrades::quote([$line], $rateRows);

    expect($conManual['errors'])->toBe([])
        ->and($conManual['annual_total'])->toBe(35.0);
});

it('no deja repetir el mismo upgrade ni agregar upgrades sin rangos', function (): void {
    $rateRows = IndividualQuoteUpgrades::rateRows('3', especialRows(), []);

    expect(IndividualQuoteUpgrades::quote([
        ['line_key' => 'a', 'upgrade_coverage_id' => 2],
        ['line_key' => 'b', 'upgrade_coverage_id' => 2],
    ], $rateRows)['errors'])->toContain('El upgrade «30% DE COBERTURA DE PREEXISTENCIA» está dos veces. Elija una sola cobertura por upgrade.')
        ->and(IndividualQuoteUpgrades::quote([['line_key' => 'a', 'upgrade_coverage_id' => 2]], [])['errors'])
        ->toContain('Indique los rangos de edad y la cantidad de personas antes de agregar upgrades.');
});

it('guarda la copia congelada y la página dinámica del plan lleva los upgrades debajo de las tarifas', function (): void {
    $quote = IndividualQuote::query()->forceCreate([
        'code' => 'TEST-IQU-'.Str::upper(Str::random(6)),
        'full_name' => 'PRUEBA UPGRADES',
        'plan' => '3',
        'status' => 'PRE-APROBADA',
        'code_agency' => 'TDG-100',
        'owner_code' => 'TDG-100',
        'created_by' => 'Pest',
    ]);
    $rateRows = IndividualQuoteUpgrades::rateRows('3', especialRows(), []);

    IndividualQuoteUpgrades::persist($quote, [['line_key' => (string) Str::uuid(), 'upgrade_coverage_id' => 2]], $rateRows);
    $summary = IndividualQuoteUpgrades::summary($quote->fresh());

    expect($summary['annual_total'])->toBe(342.0)
        ->and($summary['lines'][0]['name'])->toBe('30% DE COBERTURA DE PREEXISTENCIA')
        ->and($quote->upgrades()->first()->age_prices[0]['price'])->toBe(114);

    App\Http\Controllers\UtilsController::storeDetailsIndividualQuote($quote, $quote->toArray(), [
        ['plan_id' => 3, 'age_range_id' => 5, 'total_persons' => 2],
        ['plan_id' => 3, 'age_range_id' => 6, 'total_persons' => 1],
    ], [], false);

    $details = App\Support\IndividualQuotePdfGenerator::detailsPayload($quote->fresh(), 3, App\Support\IndividualQuotePdfLayout::resolveForIndividual(3));
    $html = view('documents.propuesta-plan-dinamica', [
        'details' => $details,
        'group_collect' => collect($details['data'])->groupBy('age_range'),
        'name_user' => 'Pest',
    ])->render();

    // Beneficios de la configuración del plan, tarifas y, debajo, los upgrades (sin tabla de totales).
    expect($details['layout'])->toBe(App\Support\IndividualQuotePdfLayout::Estructura)
        ->and($html)->toContain('EMERGENCIAS MÉDICAS POR PATOLOGIAS LISTADAS')
        ->toContain('TARIFA GRUPAL ANUAL')
        ->toContain('UPGRADES OPCIONALES')
        ->toContain('30% DE COBERTURA DE PREEXISTENCIA')
        ->toContain('PAGO ÚNICO ANUAL')
        ->toContain(App\Support\Upgrades\UpgradeInstallments::RULE)
        ->not->toContain('TOTAL CON UPGRADES')
        ->and(strpos($html, 'UPGRADES OPCIONALES'))->toBeGreaterThan(strpos($html, 'TARIFA GRUPAL ANUAL'));

    // Cada upgrade lleva su logo (imagen del catálogo o insignia con iniciales) y la página cabe en una hoja aunque haya varios.
    $line = fn (string $name, int $id): array => ['name' => $name, 'coverage' => 'Cobertura $2.000', 'persons' => 1, 'annual' => 35.0, 'rows' => [], 'services' => [], 'logo' => IndividualQuoteUpgrades::logo($id, $name)];
    $crowded = [...$details, 'upgrades' => ['lines' => [$line('PLAN FUNERARIO 2K', 3), $line('PLAN FUNERARIO 3K', 3), $line('PLAN FUNERARIO 5K', 3), $line('ODONTOLOGÍA', 1)], 'annual_total' => 140.0]];
    $crowdedHtml = view('documents.propuesta-plan-dinamica', ['details' => $crowded, 'group_collect' => collect($crowded['data'])->groupBy('age_range'), 'name_user' => 'Pest', 'fit_to_page' => true])->render();
    $onePage = tempnam(sys_get_temp_dir(), 'iq-test-');
    file_put_contents($onePage, App\Support\Quotes\IndividualQuoteProposalPages::renderPlanPage($crowded));

    try {
        expect((new setasign\Fpdi\Fpdi)->setSourceFile($onePage))->toBe(1)
            ->and($crowdedHtml)->toContain('is-compact is-fit')
            ->toContain('upgrade-badge')
            ->toContain('>F2<')
            ->toContain('>O<');
    } finally {
        @unlink($onePage);
    }

    // Documento como el del microservicio: portada, acerca, 1 página del plan, patologías, contraportada.
    $service = Barryvdh\DomPDF\Facade\Pdf::loadHTML(implode('<div style="page-break-after: always;"></div>', ['<p>portada</p>', '<p>acerca</p>', '<p>imagen horneada</p>', '<p>patologias</p>', '<p>contraportada</p>']))->output();
    $composed = App\Support\Quotes\IndividualQuoteProposalPages::replaceCalculationPages($service, (int) $quote->getKey(), [3]);
    $file = tempnam(sys_get_temp_dir(), 'iq-test-');
    file_put_contents($file, $composed['document']);

    try {
        expect($composed['calculation_pages'])->toBeGreaterThanOrEqual(1)
            ->and((new setasign\Fpdi\Fpdi)->setSourceFile($file))->toBe(4 + $composed['calculation_pages']);
    } finally {
        @unlink($file);
    }

    // Si el servicio trae menos páginas de las esperadas, el documento queda como vino.
    expect(App\Support\Quotes\IndividualQuoteProposalPages::replaceCalculationPages($service, (int) $quote->getKey(), [1, 2, 3, 4])['document'])->toBe($service);

    expect(fn () => IndividualQuoteUpgrades::persist($quote, [['line_key' => 'x', 'upgrade_coverage_id' => 3]], $rateRows))
        ->toThrow(InvalidArgumentException::class, 'no se aplica a ninguna persona');
});

it('el formulario muestra las tarjetas y un toque agrega o quita el upgrade con su primera cobertura', function (): void {
    $page = Livewire::test(CreateIndividualQuote::class)
        ->assertSuccessful()
        ->set('data.plan', '3')
        ->assertSee('Upgrades (opcional)')
        ->assertSee('FUNERARIO 2K')
        ->assertSee('Cobertura US$ 2.000 · 18 a 64 años')
        ->assertSee('desde US$ 35/año')
        ->assertSee('toggleQuoteUpgrade(3)', false);

    $page->call('toggleQuoteUpgrade', 3);
    $items = array_values($page->get('data.upgrades'));

    expect($items)->toHaveCount(1)
        ->and($items[0]['upgrade_id'])->toBe(3)
        ->and($items[0]['upgrade_coverage_id'])->toBe(3);

    $page->call('toggleQuoteUpgrade', 2)->call('toggleQuoteUpgrade', 3)->call('toggleQuoteUpgrade', 999999);

    expect(array_column(array_values($page->get('data.upgrades')), 'upgrade_id'))->toBe([2])
        ->and(method_exists(CreateIndividualQuote::class, 'togglePlanGeneratorUpgradeRange'))->toBeTrue()
        ->and(method_exists(CreateIndividualQuote::class, 'includeUpgradeRangeManuallyAction'))->toBeTrue();
});

it('el logo del upgrade en el PDF usa su imagen o una insignia con iniciales y el color de su tarjeta', function (): void {
    expect(App\Support\Upgrades\UpgradeCardCatalog::initials('PLAN FUNERARIO 2K'))->toBe('F2')
        ->and(App\Support\Upgrades\UpgradeCardCatalog::initials('30% DE COBERTURA DE PREEXISTENCIA'))->toBe('3C')
        ->and(App\Support\Upgrades\UpgradeCardCatalog::initials('  '))->toBe('U')
        ->and(IndividualQuoteUpgrades::logo(3, 'FUNERARIO 2K'))->toBe(['image' => null, 'color' => App\Support\Upgrades\UpgradeCardCatalog::fallbackGradient(3)[0], 'initials' => 'F2'])
        ->and(IndividualQuoteUpgrades::logo(3, 'FUNERARIO 2K', new App\Models\Upgrade(['image_path' => 'otra-carpeta/x.png']))['image'])->toBeNull();
});

it('las tarjetas resumen cada upgrade activo: cobertura, edades, precio desde y su imagen', function (): void {
    $cards = App\Support\Upgrades\UpgradeCardCatalog::cards();

    expect($cards[3]['eyebrow'])->toBe('Cobertura US$ 2.000 · 18 a 64 años')
        ->and($cards[3]['from_label'])->toBe('desde US$ 35/año')
        ->and($cards[2]['eyebrow'])->toStartWith('Cobertura única')
        ->and($cards)->not->toHaveKey(1)
        ->and(App\Support\Upgrades\UpgradeCardCatalog::upgradeIdForCoverage(3))->toBe(3);

    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::disk('public')->put('upgrades/images/funerario.jpg', 'x');
    $upgrade = App\Models\Upgrade::query()->findOrFail(3);

    expect($upgrade->imageUrl())->toBeNull();

    $upgrade->image_path = 'upgrades/images/funerario.jpg';

    expect($upgrade->imageUrl())->toContain('upgrades/images/funerario.jpg');

    $upgrade->image_path = '../.env';

    expect($upgrade->imageUrl())->toBeNull()
        ->and(App\Support\Upgrades\UpgradeStructureValidator::normalize(['image_path' => ['upgrades/images/a.png']])['image_path'])->toBe('upgrades/images/a.png')
        ->and(App\Support\Upgrades\UpgradeStructureValidator::normalize(['image_path' => 'otra/carpeta/a.png'])['image_path'])->toBeNull()
        ->and((string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Upgrades/Schemas/UpgradeForm.php'))->toContain("FileUpload::make('image_path')");
});
