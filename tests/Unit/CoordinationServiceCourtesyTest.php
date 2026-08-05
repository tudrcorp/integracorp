<?php

declare(strict_types=1);

use App\Support\Operations\CoordinationServiceCourtesy;
use App\Support\Operations\CoordinationServiceCourtesyActions;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;

function courtesyBasePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('declara courtesy_status y auditoría en modelos clínicos', function (): void {
    foreach ([
        'TelemedicinePatientMedications',
        'TelemedicinePatientLab',
        'TelemedicinePatientStudy',
        'TelemedicinePatientSpecialty',
    ] as $model) {
        $src = file_get_contents(courtesyBasePath('app/Models/'.$model.'.php'));

        expect($src)
            ->toContain("'courtesy_status'")
            ->toContain("'courtesy_reason'")
            ->toContain("'courtesy_updated_at'")
            ->toContain("'courtesy_updated_by'");
    }
});

it('declara is_courtesy en modelos financieros', function (): void {
    foreach (['OperationServiceOrder', 'OperationQuoteGenerator', 'OperationAccountsReceivable'] as $model) {
        $src = file_get_contents(courtesyBasePath('app/Models/'.$model.'.php'));

        expect($src)
            ->toContain("'is_courtesy'")
            ->toContain("'is_courtesy' => 'boolean'");
    }
});

it('existe migración de courtesy_status en ítems clínicos', function (): void {
    $files = glob(courtesyBasePath('database/migrations/*add_courtesy_status_to_telemedicine_clinical_items_tables.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain('telemedicine_patient_medications')
        ->toContain('telemedicine_patient_labs')
        ->toContain('telemedicine_patient_studies')
        ->toContain('telemedicine_patient_specialties')
        ->toContain("string('courtesy_status')")
        ->toContain("text('courtesy_reason')");
});

it('existe migración is_courtesy en tablas financieras', function (): void {
    $files = glob(courtesyBasePath('database/migrations/*add_is_courtesy_to_operation_finance_tables.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain('operation_service_orders')
        ->toContain('operation_quote_generators')
        ->toContain('operation_accounts_receivables')
        ->toContain("boolean('is_courtesy')")
        ->toContain('->default(false)');
});

it('itemIsCourtesy reconoce solo CORTESIA', function (): void {
    expect(CoordinationServiceCourtesy::itemIsCourtesy('CORTESIA'))->toBeTrue()
        ->and(CoordinationServiceCourtesy::itemIsCourtesy('cortesia'))->toBeTrue()
        ->and(CoordinationServiceCourtesy::itemIsCourtesy(null))->toBeFalse()
        ->and(CoordinationServiceCourtesy::itemIsCourtesy(''))->toBeFalse()
        ->and(CoordinationServiceCourtesy::itemIsCourtesy('REGULAR'))->toBeFalse();
});

it('el dominio exige TDG, motivo largo y escribe bitácora', function (): void {
    $src = file_get_contents(courtesyBasePath('app/Support/Operations/CoordinationServiceCourtesy.php'));

    expect($src)
        ->toContain('authenticatedUserIsTdgAnalyst()')
        ->toContain('mb_strlen($reason) < 10')
        ->toContain('MARK_PREFIX')
        ->toContain('REVERSE_PREFIX')
        ->toContain('ObservationCase::query()->create')
        ->toContain('partitionKeysByCourtesy')
        ->toContain("'medication'")
        ->toContain("'lab'")
        ->toContain("'study'")
        ->toContain("'specialty'");
});

it('expone acciones Filament bulk y por fila solo para TDG', function (): void {
    expect(CoordinationServiceCourtesyActions::makeMarkBulkAction())->toBeInstanceOf(BulkAction::class)
        ->and(CoordinationServiceCourtesyActions::makeReverseBulkAction())->toBeInstanceOf(BulkAction::class)
        ->and(CoordinationServiceCourtesyActions::makeMarkRecordAction())->toBeInstanceOf(Action::class)
        ->and(CoordinationServiceCourtesyActions::makeReverseRecordAction())->toBeInstanceOf(Action::class);

    $src = file_get_contents(courtesyBasePath('app/Support/Operations/CoordinationServiceCourtesyActions.php'));

    expect($src)
        ->toContain('authenticatedUserIsTdgAnalyst()')
        ->toContain('markItems')
        ->toContain('reverseItems')
        ->toContain('FilamentIosButton');
});

it('la tabla de coordinación cablea columna, bulk y acciones de cortesía', function (): void {
    $src = file_get_contents(courtesyBasePath(
        'app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'
    ));

    expect($src)
        ->toContain("TextColumn::make('courtesy_badge')")
        ->toContain('courtesyItemsCount')
        ->toContain('CoordinationServiceCourtesyActions::makeMarkBulkAction()')
        ->toContain('CoordinationServiceCourtesyActions::makeReverseBulkAction()')
        ->toContain('CoordinationServiceCourtesyActions::makeMarkRecordAction()')
        ->toContain('CoordinationServiceCourtesyActions::makeReverseRecordAction()');
});

it('particiona creación de OS y cotización por cortesía', function (): void {
    $src = file_get_contents(courtesyBasePath('app/Support/Operations/CoordinationServiceItemsManager.php'));

    expect($src)
        ->toContain('partitionKeysByCourtesy')
        ->toContain("\$payload['is_courtesy'] = \$isCourtesy")
        ->toContain("\$groupData['is_courtesy'] = \$isCourtesy")
        ->toContain("'is_courtesy' => \$isCourtesy")
        ->toContain('CourtesyFinanceNotifier::dispatchForQuote')
        ->toContain('Órdenes separadas por cortesía')
        ->toContain('Cotizaciones separadas por cortesía')
        ->toContain("'courtesy_status'");
});

it('propaga is_courtesy a CxC y notifica', function (): void {
    $src = file_get_contents(courtesyBasePath('app/Support/Operations/AccountsReceivableManager.php'));

    expect($src)
        ->toContain("'is_courtesy' => (bool) \$quote->is_courtesy")
        ->toContain('CourtesyFinanceNotifier::dispatchForReceivable')
        ->toContain('(bool) $order->is_courtesy');
});

it('copia is_courtesy al crear OS desde cotización aprobada', function (): void {
    $src = file_get_contents(courtesyBasePath('app/Support/Operations/CoordinationServiceQuoteManager.php'));

    expect($src)->toContain("\$payload['is_courtesy'] = (bool) \$quote->is_courtesy");
});

it('persiste is_courtesy al crear orden de servicio', function (): void {
    $src = file_get_contents(courtesyBasePath('app/Http/Controllers/OperationServiceOrderController.php'));

    expect($src)->toContain("'is_courtesy' => (bool) (\$data['is_courtesy'] ?? false)");
});

it('muestra badge CORTESÍA en tablas financieras y de órdenes', function (): void {
    expect(file_get_contents(courtesyBasePath(
        'app/Filament/Operations/Resources/AccountsPayables/Tables/AccountsPayablesTable.php'
    )))->toContain("TextColumn::make('is_courtesy')")
        ->toContain("'CORTESÍA'");

    expect(file_get_contents(courtesyBasePath(
        'app/Filament/Operations/Resources/AccountsReceivables/Tables/AccountsReceivablesTable.php'
    )))->toContain("TextColumn::make('is_courtesy')")
        ->toContain("'CORTESÍA'");

    expect(file_get_contents(courtesyBasePath(
        'app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php'
    )))->toContain("TextColumn::make('is_courtesy')")
        ->toContain("'CORTESÍA'");
});
