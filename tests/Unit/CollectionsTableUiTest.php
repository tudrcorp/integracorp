<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Collections\Tables\CollectionsTable;
use App\Models\Collection;
use App\Support\Filament\SummaryCards;
use Carbon\CarbonImmutable;

uses(Tests\TestCase::class);

it('muestra como vencida una cuota por pagar con la fecha pasada sin cambiar su estado', function (string $status, ?string $due, string $display, ?string $label): void {
    $today = CarbonImmutable::parse('2026-10-01');
    $record = new Collection(['status' => $status, 'filter_next_payment_date' => $due]);

    expect(CollectionsTable::displayStatus($record, $today))->toBe($display)
        ->and(CollectionsTable::dueLabel($record, $today))->toBe($label)
        ->and($record->status)->toBe($status);
})->with([
    'vencida' => ['POR PAGAR', '2026-09-20', 'VENCIDO', '11 días de atraso'],
    'vencida ayer' => ['POR PAGAR', '2026-09-30', 'VENCIDO', '1 día de atraso'],
    'vence hoy' => ['POR PAGAR', '2026-10-01', 'POR PAGAR', 'Vence hoy'],
    'por vencer' => ['POR PAGAR', '2026-10-09', 'POR PAGAR', 'Vence en 8 días'],
    'sin fecha' => ['POR PAGAR', null, 'POR PAGAR', null],
    'pagada con fecha pasada' => ['PAGADO', '2026-09-20', 'PAGADO', null],
    'anulada' => ['ANULADO', '2026-12-09', 'ANULADO', null],
]);

it('reconoce las cuotas corporativas con o sin tilde', function (?string $type, bool $corporate): void {
    expect(CollectionsTable::isCorporate($type))->toBe($corporate);
})->with([
    ['AFILIACIÓN CORPORATIVA', true],
    ['AFILIACION CORPORATIVA', true],
    ['AFILIACION INDIVIDUAL', false],
    [null, false],
]);

it('conserva las acciones de la tabla y carga las relaciones de una vez', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Collections/Tables/CollectionsTable.php');

    expect($source)
        ->toContain("Action::make('send_email')")
        ->toContain("Action::make('download_pdf')")
        ->toContain("Action::make('regenerate_pdf')")
        ->toContain('DeleteBulkAction::make()')
        ->toContain("TextInputColumn::make('next_payment_date')")
        ->toContain("'plan:id,description'")
        ->toContain("'agent:id,name'")
        ->toContain("'coverage:id,price'")
        ->toContain('FiltersLayout::AboveContentCollapsible')
        ->not->toContain("'Venta desde '")
        ->not->toContain("->label('Número de teléfono')");
});

it('el encabezado resume por cobrar, vencido y cobrado', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Collections/Pages/ListCollections.php');

    expect($source)
        ->toContain("protected static ?string \$title = 'Gestión de Cobranza';")
        ->toContain("'label' => 'Por cobrar'")
        ->toContain("'label' => 'Vencido'")
        ->toContain("'label' => 'Cobrado'")
        ->toContain('getFilteredTableQuery()');
});

it('las tarjetas de resumen escapan el contenido y formatean montos', function (): void {
    $html = (string) SummaryCards::render([
        ['label' => '<b>Por cobrar</b>', 'value' => SummaryCards::money(60842.78), 'detail' => SummaryCards::count(1, 'cuota', 'cuotas'), 'color' => SummaryCards::BLUE],
        ['label' => 'Vencido', 'value' => SummaryCards::money(0), 'detail' => null, 'color' => SummaryCards::RED],
    ]);

    expect($html)
        ->toContain('&lt;b&gt;Por cobrar&lt;/b&gt;')
        ->toContain('US$ 60.842,78')
        ->toContain('1 cuota')
        ->toContain('US$ 0,00')
        ->and(SummaryCards::count(1500, 'cuota', 'cuotas'))->toBe('1.500 cuotas');
});
