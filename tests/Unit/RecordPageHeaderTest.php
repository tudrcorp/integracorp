<?php

declare(strict_types=1);

use App\Support\Filament\RecordPageHeader;

it('arma el encabezado con estado, etiquetas y datos, omitiendo los vacíos', function (): void {
    $html = (string) RecordPageHeader::render(
        eyebrow: 'Afiliación individual · TDEC-IND-000001',
        title: 'María Pérez',
        status: RecordPageHeader::statusFor('activa'),
        chips: [RecordPageHeader::tag('PLAN INICIAL'), null],
        facts: ['Cédula titular' => '123', 'Agente' => null, 'Correo' => '  '],
    );

    expect($html)
        ->toContain('Afiliación individual · TDEC-IND-000001')
        ->toContain('María Pérez')
        ->toContain('ACTIVA')
        ->toContain('PLAN INICIAL')
        ->toContain('Cédula titular')
        ->toContain('MP')
        ->not->toContain('Agente')
        ->not->toContain('Correo');
});

it('acota el ancho de los datos y corta los correos después de la arroba', function (): void {
    $html = (string) RecordPageHeader::render('Empresa', 'Vive Plus', facts: ['Correo' => 'info@viveplus.com']);

    expect($html)
        ->toContain('max-width:680px')
        ->toContain('gap:8px 28px')
        ->not->toContain('grid-template-columns')
        ->toContain('info@<wbr>viveplus.com');
});

it('escapa el contenido del registro', function (): void {
    $html = (string) RecordPageHeader::render(
        eyebrow: 'Agencia',
        title: '<script>alert(1)</script>',
        facts: ['Nota' => '"><img src=x>'],
    );

    expect($html)
        ->not->toContain('<script>')
        ->not->toContain('<img src=x>')
        ->toContain('&lt;script&gt;');
});

it('usa el logo cuando hay imagen y las iniciales cuando no', function (): void {
    expect((string) RecordPageHeader::render('Empresa aliada', 'Vive Plus', imageUrl: 'https://example.test/logo.png'))
        ->toContain('<img src="https://example.test/logo.png"')
        ->and((string) RecordPageHeader::render('Empresa aliada', 'Vive Plus'))
        ->not->toContain('<img')
        ->toContain('VP');
});

it('colorea el estado según su significado, con o sin tilde', function (string $status, string $tone): void {
    expect(RecordPageHeader::statusFor($status)['tone'])->toBe($tone);
})->with([
    ['ACTIVA', RecordPageHeader::TONE_SUCCESS],
    ['Activo', RecordPageHeader::TONE_SUCCESS],
    ['VIGENTE', RecordPageHeader::TONE_SUCCESS],
    ['PRE-APROBADA', RecordPageHeader::TONE_WARNING],
    ['PERIODO DE RENOVACIÓN', RecordPageHeader::TONE_WARNING],
    ['EXCLUIDO', RecordPageHeader::TONE_DANGER],
    ['OTRO', RecordPageHeader::TONE_NEUTRAL],
]);

it('no muestra estado vacío y formatea montos', function (): void {
    expect(RecordPageHeader::statusFor(null))->toBeNull()
        ->and(RecordPageHeader::statusFor('  '))->toBeNull()
        ->and(RecordPageHeader::money('7706'))->toBe('US$ 7.706,00')
        ->and(RecordPageHeader::money(0))->toBe('US$ 0,00')
        ->and(RecordPageHeader::money(0, hideZero: true))->toBeNull()
        ->and(RecordPageHeader::money('N/A'))->toBeNull()
        ->and(RecordPageHeader::initials('  '))->toBe('·');
});

it('traduce los días restantes de la renovación', function (int $days, string $label, string $tone): void {
    expect(RecordPageHeader::remainingDaysTag($days))->toBe(['label' => $label, 'tone' => $tone]);
})->with([
    'vencida' => [-246, 'Vencida hace 246 días', RecordPageHeader::TONE_DANGER],
    'vencida un día' => [-1, 'Vencida hace 1 día', RecordPageHeader::TONE_DANGER],
    'hoy' => [0, 'Vence hoy', RecordPageHeader::TONE_DANGER],
    'próxima' => [21, 'Vence en 21 días', RecordPageHeader::TONE_WARNING],
    'lejana' => [101, 'Faltan 101 días', RecordPageHeader::TONE_INFO],
]);

it('la ficha corporativa solo muestra RIF, tarifa anual y monto por cuota', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php');

    expect($source)
        ->toContain("'RIF' =>")
        ->toContain("'Tarifa anual' =>")
        ->toContain("'Monto por cuota' =>")
        ->not->toContain("'Contacto' =>")
        ->not->toContain("'Correo' =>")
        ->not->toContain("'Teléfono' =>")
        ->not->toContain("'Agencia' =>");
});

it('la ficha individual solo muestra cédula, tarifa anual y monto por cuota', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Pages/ViewAffiliation.php');

    expect($source)
        ->toContain("'Cédula titular' =>")
        ->toContain("'Tarifa anual' =>")
        ->toContain("'Monto por cuota' =>")
        ->not->toContain("'Personas' =>")
        ->not->toContain("'Vigencia' =>")
        ->not->toContain("'Agente' =>")
        ->not->toContain("'Agencia' =>");
});

it('la ficha de renovación solo muestra fecha, plan y frecuencia', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Renovations/Pages/ViewRenovation.php');

    expect($source)
        ->toContain("'Fecha de renovación' =>")
        ->toContain("'Plan' =>")
        ->toContain("'Frecuencia' =>")
        ->not->toContain("'Personas' =>")
        ->not->toContain("'Edad titular' =>")
        ->not->toContain("'Tarifa anual' =>");
});

it('reparte los datos en las filas indicadas y omite filas vacías', function (): void {
    $html = (string) RecordPageHeader::render('Renovación aceptada', 'X', facts: [
        ['Aceptada el' => '29/09/2026', 'Vigencia' => '20/10/2025 → 20/10/2026'],
        ['Plan' => 'PLAN INICIAL', 'Tarifa anual' => 'US$ 160,00'],
        ['Vacío' => null],
    ]);

    expect(substr_count($html, 'gap:8px 28px'))->toBe(2)
        ->and(strpos($html, 'Vigencia'))->toBeLessThan(strpos($html, 'PLAN INICIAL'))
        ->and($html)->not->toContain('Vacío');
});

it('la ficha de renovación aceptada solo muestra aceptación y vigencia', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationRenovationHistories/Pages/ViewAffiliationRenovationHistory.php');

    expect($source)
        ->toContain("'Aceptada el' =>")
        ->toContain("'Aceptada por' =>")
        ->toContain("'Vigencia' =>")
        ->not->toContain("'Personas' =>")
        ->not->toContain("'Plan' =>")
        ->not->toContain("'Frecuencia' =>")
        ->not->toContain("'Tarifa anual' =>");
});

it('las fichas de administración usan el encabezado compartido sin ensuciar el título de la pestaña', function (string $page): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/'.$page);

    expect($source)
        ->toContain('public function getHeading(): string|Htmlable')
        ->toContain('RecordPageHeader::render(')
        ->not->toContain('new HtmlString(');
})->with([
    'agencia de viajes' => 'TravelAgencies/Pages/ViewTravelAgency.php',
    'empresa aliada' => 'WhiteCompanies/Pages/EditWhiteCompany.php',
    'afiliación individual' => 'Affiliations/Pages/ViewAffiliation.php',
    'afiliación corporativa' => 'AffiliationCorporates/Pages/ViewAffiliationCorporate.php',
    'renovación' => 'Renovations/Pages/ViewRenovation.php',
    'renovación aceptada' => 'AffiliationRenovationHistories/Pages/ViewAffiliationRenovationHistory.php',
    'renovación corporativa' => 'RenovationCorporates/Pages/ViewRenovationCorporate.php',
    'renovación corporativa aceptada' => 'AffiliationCorporateRenovationHistories/Pages/ViewAffiliationCorporateRenovationHistory.php',
]);
