<?php

declare(strict_types=1);

use App\Enums\DressTaylorCompany;
use App\Models\CorporateQuoteRequest;

it('define el enum de empresa dress taylor con tdec y tdev', function (): void {
    expect(DressTaylorCompany::options())->toBe([
        'TDEC' => 'TDEC',
        'TDEV' => 'TDEV',
    ])
        ->and(DressTaylorCompany::Tdec->filamentColor())->toBe('info')
        ->and(DressTaylorCompany::Tdev->filamentColor())->toBe('success');
});

it('incluye company en fillable y casts del modelo de solicitudes', function (): void {
    $model = new CorporateQuoteRequest;

    expect($model->getFillable())->toContain('company')
        ->and($model->getCasts())->toHaveKey('company')
        ->and($model->getCasts()['company'])->toBe(DressTaylorCompany::class);
});

it('agrega el campo empresa en el formulario de creacion y edicion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Schemas/CorporateQuoteRequestForm.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain("Select::make('company')")
        ->toContain("->label('Empresa')")
        ->toContain('DressTaylorCompany::options()')
        ->toContain('->required()');
});

it('muestra la empresa en el infolist de la solicitud', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Schemas/CorporateQuoteRequestInfolist.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain("TextEntry::make('company')")
        ->toContain("->label('Empresa')")
        ->toContain('DressTaylorCompany');
});

it('muestra la empresa en la tabla de solicitudes', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Tables/CorporateQuoteRequestsTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain("TextColumn::make('company')")
        ->toContain("SelectFilter::make('company')")
        ->toContain('DressTaylorCompany::options()');
});

it('incluye desglose tdec/tdev en el hover del grafico de canal', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Widgets/CorporateQuoteRequestChannelChart.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('monthlyCompanyCounts')
        ->toContain("'tdecCounts'")
        ->toContain("'tdevCounts'")
        ->toContain("lines.push(' TDEC: '")
        ->toContain("lines.push(' TDEV: '")
        ->not->toContain('companyCountsForGroupedKeys')
        ->not->toContain('openMonthDetail');
});

it('tiene migracion para la columna company en corporate_quote_requests', function (): void {
    $migrations = glob(dirname(__DIR__, 2).'/database/migrations/*add_company_to_corporate_quote_requests_table.php');

    expect($migrations)->not->toBeEmpty();

    $code = file_get_contents($migrations[0]);

    expect($code)->not->toBeFalse()
        ->toContain("\$table->string('company', 10)")
        ->toContain("->dropColumn('company')");
});
