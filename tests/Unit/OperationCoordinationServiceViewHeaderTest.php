<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ViewOperationCoordinationService;
use App\Models\OperationCoordinationService;

/**
 * Necesita contenedor porque la cabecera lee relaciones Eloquent. Solo lee: no
 * persiste nada, así que no requiere transacción revertida.
 */
uses(Tests\TestCase::class);

function coordinationHeaderPills(OperationCoordinationService $record): string
{
    $page = new ViewOperationCoordinationService;
    $method = new ReflectionMethod($page, 'headerPillsHtml');
    $method->setAccessible(true);

    return (string) $method->invoke($page, $record);
}

function coordinationHeaderServiceDate(OperationCoordinationService $record): string
{
    $page = new ViewOperationCoordinationService;
    $method = new ReflectionMethod($page, 'serviceDateLabel');
    $method->setAccessible(true);

    return (string) $method->invoke($page, $record);
}

it('omite los pills de cabecera cuyo dato no existe', function (): void {
    $html = coordinationHeaderPills(new OperationCoordinationService([
        'reference_number' => 'REF-1',
        'ci_patient' => null,
        'date_service' => null,
    ]));

    expect($html)
        ->toContain('Referencia:')
        ->toContain('REF-1')
        ->not->toContain('C.I. paciente:')
        ->not->toContain('Caso:')
        ->not->toContain('Fecha de servicio:')
        ->not->toContain('Médico tratante:');
});

it('escapa los valores del header para no romper el marcado', function (): void {
    $html = coordinationHeaderPills(new OperationCoordinationService([
        'reference_number' => '<script>alert(1)</script>',
        'ci_patient' => '1&2',
    ]));

    expect($html)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('1&amp;2')
        ->not->toContain('<script>');
});

it('formatea la fecha de servicio y tolera valores no parseables', function (): void {
    expect(coordinationHeaderServiceDate(new OperationCoordinationService(['date_service' => '2026-08-31'])))
        ->toBe('31/08/2026')
        ->and(coordinationHeaderServiceDate(new OperationCoordinationService(['date_service' => '2026-08-31 14:30:00'])))
        ->toBe('31/08/2026')
        ->and(coordinationHeaderServiceDate(new OperationCoordinationService(['date_service' => 'sin definir'])))
        ->toBe('sin definir')
        ->and(coordinationHeaderServiceDate(new OperationCoordinationService(['date_service' => null])))
        ->toBe('');
});

it('enlaza el número de caso a la ficha del caso de telemedicina', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ViewOperationCoordinationService.php');

    expect($page)
        ->toContain("TelemedicineCaseResource::getUrl('view', ['record' => \$case])")
        ->toContain("\$this->renderHeaderPill(\n                'Caso',")
        ->toContain("'Abrir la ficha del caso de telemedicina'")
        ->toContain('fi-coordination-header__pill--link')
        ->toContain('$case !== null && $caseCode !== \'\'');
});

it('construye el header con clases de tema en vez de estilos claros fijos', function (): void {
    $root = dirname(__DIR__, 2);
    $page = file_get_contents($root.'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ViewOperationCoordinationService.php');
    $theme = file_get_contents($root.'/resources/css/filament/admin/theme.css');

    expect($page)
        ->toContain('fi-coordination-header__eyebrow')
        ->toContain('fi-coordination-header__title')
        ->toContain('fi-coordination-header__pills')
        ->toContain('fi-coordination-header__statuses')
        ->not->toContain('background:linear-gradient(180deg,#f4f8ff')
        ->not->toContain('background:linear-gradient(180deg,#f8fafc');

    expect($theme)
        ->toContain('.fi-coordination-header__pill {')
        ->toContain('.fi-coordination-header__pill--link {')
        ->toContain('.fi-coordination-header__title {');

    $pillRule = mb_substr(
        $theme,
        (int) mb_strpos($theme, '.fi-coordination-header__pill {'),
        320,
    );

    expect($pillRule)->toContain('dark:');
});
