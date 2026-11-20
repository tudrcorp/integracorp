<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\QuoteDesign;
use App\Models\QuoteDesignIssuance;
use App\Models\QuoteDesignVersion;
use App\Support\Quotes\Design\QuoteDesignedProposal;
use App\Support\Quotes\Design\QuoteDesignImageNormalizer;
use App\Support\Quotes\Design\QuoteDesignPage;
use App\Support\Quotes\Design\QuoteDesignPersistence;
use App\Support\Quotes\Design\QuoteDesignPreview;
use App\Support\Quotes\Design\QuoteDesignResolver;
use App\Support\Quotes\Design\QuoteDesignTheme;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

uses(Tests\TestCase::class);

/**
 * Estos tests escriben, así que corren dentro de una transacción que siempre se revierte y con
 * el disco `public` falso: no dejan filas ni archivos.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
    QuoteDesignResolver::flush();
});

afterEach(function (): void {
    DB::rollBack();
    QuoteDesignResolver::flush();
});

/**
 * @return list<int>
 */
function freePlanIds(int $count = 2): array
{
    $ids = Plan::query()
        ->whereNotIn('id', DB::table('quote_design_plan')->pluck('plan_id'))
        ->orderBy('id')
        ->limit($count)
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    if (count($ids) < $count) {
        test()->markTestSkipped('No hay planes libres en la base para probar diseños.');
    }

    return $ids;
}

function fakeUploadedPage(int $width = 816, int $height = 1056): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    $path = 'quote-designs/uploads/'.uniqid('page-', true).'.png';
    Storage::disk('public')->put($path, $png);

    return $path;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function designData(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Renacer',
        'status' => QuoteDesign::STATUS_INACTIVE,
        'plans' => [],
        'theme' => ['primary' => '#17612F', 'accent' => '#C89A2B'],
        'pages_before' => [],
        'pages_after' => [],
    ], $overrides);
}

it('guarda el diseño y deja la versión 1', function (): void {
    $design = QuoteDesignPersistence::save(null, designData());

    expect($design->version)->toBe(1)
        ->and($design->versions()->count())->toBe(1)
        ->and($design->theme['primary'])->toBe('#17612F')
        ->and($design->versions()->first()->snapshot['theme']['accent'])->toBe('#C89A2B');
});

it('no crea una versión nueva si no cambió nada de lo que se imprime', function (): void {
    $design = QuoteDesignPersistence::save(null, designData());
    $design = QuoteDesignPersistence::save($design, designData(['name' => 'Renacer Ecofuneral', 'description' => 'Solo cambia el nombre']));

    expect($design->version)->toBe(1)
        ->and($design->versions()->count())->toBe(1)
        ->and($design->name)->toBe('Renacer Ecofuneral');
});

it('crea una versión nueva al cambiar un color y conserva la anterior intacta', function (): void {
    $design = QuoteDesignPersistence::save(null, designData());
    $design = QuoteDesignPersistence::save($design, designData(['theme' => ['primary' => '#000000']]));

    expect($design->version)->toBe(2)
        ->and(QuoteDesignVersion::query()->where('quote_design_id', $design->id)->where('version', 1)->first()->snapshot['theme']['primary'])->toBe('#17612F')
        ->and(QuoteDesignVersion::query()->where('quote_design_id', $design->id)->where('version', 2)->first()->snapshot['theme']['primary'])->toBe('#000000');
});

it('no deja activar un diseño sin planes', function (): void {
    QuoteDesignPersistence::save(null, designData(['status' => QuoteDesign::STATUS_ACTIVE]));
})->throws(ValidationException::class);

it('no deja que un plan tenga dos diseños y dice cuál lo tiene', function (): void {
    [$planId] = freePlanIds(1);

    QuoteDesignPersistence::save(null, designData(['name' => 'Primero', 'plans' => [$planId]]));

    try {
        QuoteDesignPersistence::save(null, designData(['name' => 'Segundo', 'plans' => [$planId]]));
        $this->fail('Debió rechazar el plan repetido.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->implode(' '))->toContain('Primero');
    }

    expect(QuoteDesign::query()->where('name', 'Segundo')->exists())->toBeFalse();
});

it('ignora planes que no existen', function (): void {
    $design = QuoteDesignPersistence::save(null, designData(['plans' => [999999999]]));

    expect($design->plans()->count())->toBe(0);
});

it('lleva las páginas fijas a A4 y descarta la imagen original', function (): void {
    $upload = fakeUploadedPage();

    $design = QuoteDesignPersistence::save(null, designData(['pages_before' => [$upload]]));
    $page = $design->pages_before[0];

    expect(QuoteDesignImageNormalizer::isNormalized($page))->toBeTrue()
        ->and(Storage::disk('public')->exists($page))->toBeTrue()
        ->and(Storage::disk('public')->exists($upload))->toBeFalse()
        ->and(getimagesizefromstring((string) Storage::disk('public')->get($page))[0])->toBe(QuoteDesignImageNormalizer::WIDTH);
});

it('volver a guardar no procesa otra vez una página ya normalizada', function (): void {
    $design = QuoteDesignPersistence::save(null, designData(['pages_before' => [fakeUploadedPage()]]));
    $page = $design->pages_before[0];

    $design = QuoteDesignPersistence::save($design, designData(['pages_before' => [$page]]));

    expect($design->pages_before)->toBe([$page])
        ->and($design->version)->toBe(1);
});

it('rechaza una fuente que no es TrueType', function (): void {
    Storage::disk('public')->put('quote-designs/fonts/falsa.ttf', 'esto no es una fuente');

    QuoteDesignPersistence::save(null, designData(['font_regular_path' => 'quote-designs/fonts/falsa.ttf']));
})->throws(ValidationException::class, 'TrueType');

it('acepta una fuente TrueType real', function (): void {
    Storage::disk('public')->put('quote-designs/fonts/dejavu.ttf', (string) file_get_contents(base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf')));

    $design = QuoteDesignPersistence::save(null, designData(['font_regular_path' => 'quote-designs/fonts/dejavu.ttf']));

    expect($design->font_regular_path)->toBe('quote-designs/fonts/dejavu.ttf');
});

it('una cotización nueva anota la versión vigente y la conserva aunque el diseño cambie', function (): void {
    [$planId] = freePlanIds(1);
    $design = QuoteDesignPersistence::save(null, designData(['status' => QuoteDesign::STATUS_ACTIVE, 'plans' => [$planId]]));

    $issued = QuoteDesignResolver::forIssuance(QuoteDocumentLayout::SCOPE_INDIVIDUAL, 990000001, $planId, quoteIsNew: true);

    QuoteDesignPersistence::save($design, designData(['status' => QuoteDesign::STATUS_ACTIVE, 'plans' => [$planId], 'theme' => ['primary' => '#000000']]));
    QuoteDesignResolver::flush();

    $regenerated = QuoteDesignResolver::forIssuance(QuoteDocumentLayout::SCOPE_INDIVIDUAL, 990000001, $planId, quoteIsNew: false);
    $newQuote = QuoteDesignResolver::forIssuance(QuoteDocumentLayout::SCOPE_INDIVIDUAL, 990000002, $planId, quoteIsNew: true);

    expect($issued->version)->toBe(1)
        ->and($regenerated->getKey())->toBe($issued->getKey())
        ->and($newQuote->version)->toBe(2)
        ->and(QuoteDesignIssuance::query()->where('quote_id', 990000001)->count())->toBe(1);
});

it('regenerar una propuesta emitida sin diseño no le pone uno', function (): void {
    [$planId] = freePlanIds(1);
    QuoteDesignPersistence::save(null, designData(['status' => QuoteDesign::STATUS_ACTIVE, 'plans' => [$planId]]));

    expect(QuoteDesignResolver::forIssuance(QuoteDocumentLayout::SCOPE_INDIVIDUAL, 990000003, $planId, quoteIsNew: false))->toBeNull()
        ->and(QuoteDesignResolver::anyPlanHasDesign(QuoteDocumentLayout::SCOPE_INDIVIDUAL, 990000003, [$planId], quoteIsNew: false))->toBeFalse();
});

it('un diseño inactivo no se aplica a cotizaciones nuevas, pero sí respeta las ya emitidas', function (): void {
    [$planId] = freePlanIds(1);
    $design = QuoteDesignPersistence::save(null, designData(['status' => QuoteDesign::STATUS_ACTIVE, 'plans' => [$planId]]));
    QuoteDesignResolver::forIssuance(QuoteDocumentLayout::SCOPE_CORPORATE, 990000004, $planId, quoteIsNew: true);

    QuoteDesignPersistence::setStatus($design, QuoteDesign::STATUS_INACTIVE);

    expect(QuoteDesignResolver::forIssuance(QuoteDocumentLayout::SCOPE_CORPORATE, 990000005, $planId, quoteIsNew: true))->toBeNull()
        ->and(QuoteDesignResolver::forIssuance(QuoteDocumentLayout::SCOPE_CORPORATE, 990000004, $planId, quoteIsNew: false))->not->toBeNull()
        ->and(QuoteDesignResolver::anyPlanHasDesign(QuoteDocumentLayout::SCOPE_CORPORATE, 990000005, [$planId], quoteIsNew: true))->toBeFalse();
});

it('la anotación es por ámbito: la misma id en individual y corporativa son cotizaciones distintas', function (): void {
    [$planId] = freePlanIds(1);
    QuoteDesignPersistence::save(null, designData(['status' => QuoteDesign::STATUS_ACTIVE, 'plans' => [$planId]]));

    QuoteDesignResolver::forIssuance(QuoteDocumentLayout::SCOPE_INDIVIDUAL, 990000006, $planId, quoteIsNew: true);

    expect(QuoteDesignResolver::forIssuance(QuoteDocumentLayout::SCOPE_CORPORATE, 990000006, $planId, quoteIsNew: false))->toBeNull();
});

it('activar desde la tabla exige planes', function (): void {
    $design = QuoteDesignPersistence::save(null, designData());

    QuoteDesignPersistence::setStatus($design, QuoteDesign::STATUS_ACTIVE);
})->throws(ValidationException::class);

it('las páginas fijas del diseño solo presiden el documento si todos los planes comparten diseño', function (): void {
    $theme = QuoteDesignTheme::fromSnapshot(['theme' => []]);
    $versionA = new QuoteDesignVersion(['quote_design_id' => 1]);
    $versionB = new QuoteDesignVersion(['quote_design_id' => 2]);
    $plan = fn (?QuoteDesignVersion $version): array => ['plan_id' => 1, 'details' => [], 'theme' => $version ? $theme : null, 'version' => $version];

    expect(QuoteDesignedProposal::sharedTheme([$plan($versionA), $plan($versionA)]))->toBe($theme)
        ->and(QuoteDesignedProposal::sharedTheme([$plan($versionA), $plan($versionB)]))->toBeNull()
        ->and(QuoteDesignedProposal::sharedTheme([$plan($versionA), $plan(null)]))->toBeNull();
});

it('una versión con rutas manipuladas no apunta fuera de la carpeta de diseños', function (): void {
    $theme = QuoteDesignTheme::fromSnapshot([
        'logo_path' => '../../.env',
        'font_regular_path' => '/etc/passwd',
        'pages_before' => ['quote-designs/../../../.env', 'quote-designs/pages/no-existe-a4.jpg'],
    ]);

    expect($theme->logoPath)->toBeNull()
        ->and($theme->fontRegularPath)->toBeNull()
        ->and($theme->pagesBefore)->toBe([]);
});

it('dibuja la página de cálculos con el diseño en una sola hoja', function (): void {
    [$planId] = freePlanIds(1);
    $theme = QuoteDesignTheme::fromSnapshot(['theme' => ['primary' => '#17612F', 'footer_left' => 'Asistencia Funeraria']]);
    $details = [
        'plan' => $planId,
        'code' => 'COT-IND-0000001',
        'name' => 'Cliente de prueba',
        'agent_name' => 'Agente de prueba',
        'date' => '17-11-2026',
        'data' => [(object) ['plan_id' => $planId, 'age_range' => '18 a 64', 'total_persons' => 2, 'subtotal_anual' => 150, 'subtotal_biannual' => 76, 'subtotal_quarterly' => 39, 'coverage' => null]],
        'upgrades' => [],
    ];

    $html = view('documents.propuesta-plan-disenio', QuoteDesignPage::viewData($details, $theme, QuoteDocumentLayout::SCOPE_INDIVIDUAL))->render();
    $pdf = QuoteDesignPage::render($details, $theme, QuoteDocumentLayout::SCOPE_INDIVIDUAL);

    expect($html)->toContain('#17612F')
        ->and($html)->toContain('Asistencia Funeraria')
        ->and($html)->toContain('0000001')
        ->and($html)->toContain('17/11/2026')
        ->and($html)->toContain('Sr(a). Cliente de prueba')
        ->and($html)->not->toContain('display: flex')
        ->and($html)->not->toContain('display:grid')
        ->and(str_starts_with($pdf, '%PDF'))->toBeTrue()
        ->and((new Fpdi)->setSourceFile(StreamReader::createByString($pdf)))->toBe(1);
});

it('en la corporativa el titular se rotula como cliente y sin tratamiento', function (): void {
    $theme = QuoteDesignTheme::fromSnapshot(['theme' => []]);
    $data = QuoteDesignPage::viewData(['plan' => 0, 'name' => 'Empresa, C.A.', 'code' => 'COT-CORP-0000009', 'date' => '01-11-2026', 'data' => []], $theme, QuoteDocumentLayout::SCOPE_CORPORATE);

    expect($data['clientLabel'])->toBe('Cliente')
        ->and($data['clientName'])->toBe('Empresa, C.A.')
        ->and($data['controlNumber'])->toBe('0000009')
        ->and($data['issuedAt'])->toBe('01/11/2026');
});

it('la vista previa avisa si el plan no existe', function (): void {
    QuoteDesignPreview::sampleDetails(999999999);
})->throws(RuntimeException::class, 'no existe');
