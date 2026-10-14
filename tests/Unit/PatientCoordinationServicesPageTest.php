<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTableFilters;
use App\Filament\Operations\Resources\TelemedicinePatients\Pages\PatientCoordinationServices;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Operations\CoordinationServiceItemsManager;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    CoordinationServiceItemsManager::flushClinicalItemsCache();

    $this->actingAs(User::factory()->make([
        'id' => 999_999_002,
        'name' => 'QA SERVICIOS PACIENTE',
        'email' => 'qa.servicios.paciente@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
    ]));

    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

/**
 * Paciente real con coordinaciones visibles de al menos dos estatus.
 */
function pacienteConServiciosDeVariosEstatus(): ?TelemedicinePatient
{
    $patientId = OperationCoordinationService::query()
        ->whereNotNull('telemedicine_patient_id')
        ->groupBy('telemedicine_patient_id')
        ->havingRaw('COUNT(DISTINCT status) > 1')
        ->orderByRaw('COUNT(*) DESC')
        ->value('telemedicine_patient_id');

    return $patientId === null ? null : TelemedicinePatient::query()->find($patientId);
}

it('lista todas las coordinaciones del paciente y ninguna de otro', function (): void {
    $patient = pacienteConServiciosDeVariosEstatus();

    if (! $patient instanceof TelemedicinePatient) {
        $this->markTestSkipped('No hay pacientes con coordinaciones en la base local.');
    }

    $own = OperationCoordinationService::query()->where('telemedicine_patient_id', $patient->id)->get();
    $others = OperationCoordinationService::query()
        ->where('telemedicine_patient_id', '!=', $patient->id)
        ->latest('id')
        ->limit(3)
        ->get();

    Livewire::test(PatientCoordinationServices::class, ['record' => $patient->getKey()])
        ->assertSuccessful()
        ->assertSee('Servicios del paciente')
        ->call('loadTable')
        ->assertCanSeeTableRecords($own)
        ->assertCanNotSeeTableRecords($others)
        ->assertCountTableRecords($own->count());
});

it('filtra por estatus al instante y sin pestañas sobre la tabla', function (): void {
    $patient = pacienteConServiciosDeVariosEstatus();

    if (! $patient instanceof TelemedicinePatient) {
        $this->markTestSkipped('No hay pacientes con coordinaciones en la base local.');
    }

    $own = OperationCoordinationService::query()->where('telemedicine_patient_id', $patient->id)->get();
    $status = (string) $own->first()->status;
    $group = collect(OperationCoordinationServicesTableFilters::STATUS_GROUPS)
        ->search(fn (array $values): bool => in_array($status, $values, true));

    if ($group === false) {
        $this->markTestSkipped('El estatus '.$status.' no pertenece a ningún grupo del filtro.');
    }

    $groupValues = OperationCoordinationServicesTableFilters::STATUS_GROUPS[$group];
    [$matching, $rest] = $own->partition(fn (OperationCoordinationService $service): bool => in_array($service->status, $groupValues, true));

    expect($rest)->not->toBeEmpty();

    $component = Livewire::test(PatientCoordinationServices::class, ['record' => $patient->getKey()])
        ->call('loadTable');

    expect($component->instance()->getTabs())->toBe([])
        ->and($component->instance()->getTable()->hasDeferredFilters())->toBeFalse();

    $component->filterTable('status', [$group])
        ->assertCanSeeTableRecords($matching)
        ->assertCanNotSeeTableRecords($rest);
});

it('busca por orden, cotización o factura aunque no tengan columna', function (string $column): void {
    $record = OperationCoordinationService::query()
        ->whereNotNull('telemedicine_patient_id')
        ->whereNotNull($column)
        ->whereRaw('CHAR_LENGTH(TRIM('.$column.')) > 3')
        ->latest('id')
        ->first();

    if (! $record instanceof OperationCoordinationService) {
        $this->markTestSkipped('No hay coordinaciones con '.$column.' en la base local.');
    }

    Livewire::test(PatientCoordinationServices::class, ['record' => $record->telemedicine_patient_id])
        ->call('loadTable')
        ->searchTable(trim((string) $record->{$column}))
        ->assertCanSeeTableRecords([$record]);
})->with(['reference_number', 'service_order_number', 'quote_number', 'bill_number']);

it('es una tabla de solo consulta: sin enlaces, sin selección y una sola acción de ver', function (): void {
    $patient = pacienteConServiciosDeVariosEstatus();

    if (! $patient instanceof TelemedicinePatient) {
        $this->markTestSkipped('No hay pacientes con coordinaciones en la base local.');
    }

    $table = Livewire::test(PatientCoordinationServices::class, ['record' => $patient->getKey()])
        ->call('loadTable')
        ->instance()
        ->getTable();
    $record = OperationCoordinationService::query()->where('telemedicine_patient_id', $patient->id)->firstOrFail();

    expect(array_keys($table->getFlatActions()))->toBe(['detail'])
        ->and($table->getToolbarActions())->toBe([])
        ->and($table->isSelectionEnabled())->toBeFalse()
        ->and($table->getRecordUrl($record))->toBeNull()
        ->and($table->getRecordAction($record))->toBe('detail');

    $source = file_get_contents(app_path('Filament/Operations/Resources/TelemedicinePatients/Pages/PatientCoordinationServices.php'));

    expect($source)->not->toContain('renderCoordinationClinicalItemsCompactList')
        ->not->toContain('OperationCoordinationServiceResource::getUrl')
        ->not->toContain('OperationCoordinationServiceInfolist');
});

it('el detalle se abre en la misma página, sin guardar ni enlaces', function (): void {
    $record = OperationCoordinationService::query()
        ->whereNotNull('telemedicine_patient_id')
        ->whereHas('telemedicinePatientMedications')
        ->latest('id')
        ->first();

    if (! $record instanceof OperationCoordinationService) {
        $this->markTestSkipped('No hay coordinaciones con medicamentos en la base local.');
    }

    $hasDocuments = App\Support\Operations\CoordinationServiceDocumentsAggregator::forCoordination($record) !== [];

    $component = Livewire::test(PatientCoordinationServices::class, ['record' => $record->telemedicine_patient_id])
        ->call('loadTable')
        ->mountTableAction('detail', $record)
        ->assertSuccessful()
        ->assertMountedActionModalSee(['Estatus', 'Ítems clínicos', 'Auditoría', 'Cerrar', (string) $record->servicie])
        ->assertMountedActionModalDontSee(['Guardar', 'Gestionar', 'Negociación y facturación', 'Observaciones', 'Farmadoc', 'Tipo de servicio']);

    if (! $hasDocuments) {
        $component->assertMountedActionModalDontSee('Documentos');
    }

    $action = PatientCoordinationServices::detailAction();

    expect($action->isModalSlideOver())->toBeTrue()
        ->and($action->getModalSubmitAction())->toBeNull();
});

it('los ítems de la tabla salen en texto plano', function (): void {
    $record = OperationCoordinationService::query()
        ->whereHas('telemedicinePatientLabs')
        ->orWhereHas('telemedicinePatientMedications')
        ->first();

    if (! $record instanceof OperationCoordinationService) {
        $this->markTestSkipped('No hay coordinaciones con ítems en la base local.');
    }

    $lines = PatientCoordinationServices::clinicalItemLines($record);

    expect($lines)->not->toBeEmpty();

    foreach ($lines as $line) {
        expect($line)->not->toContain('<')->toContain(' — ');
    }
});

it('cargar la tabla no escribe nada y usa pocas consultas', function (): void {
    $patient = pacienteConServiciosDeVariosEstatus();

    if (! $patient instanceof TelemedicinePatient) {
        $this->markTestSkipped('No hay pacientes con coordinaciones en la base local.');
    }

    $component = Livewire::test(PatientCoordinationServices::class, ['record' => $patient->getKey()]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $component->call('loadTable')->assertSuccessful();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    $writes = collect($log)->filter(fn (array $query): bool => preg_match('/^\s*(insert|update|delete)\b/i', $query['query']) === 1);

    expect($writes)->toBeEmpty()
        ->and(count($log))->toBeLessThan(20);
});

it('un usuario de proveedor no entra a un paciente de otro proveedor', function (): void {
    $patient = TelemedicinePatient::query()->whereNull('supplier_id')
        ->whereHas('operationCoordinationServices')
        ->first();

    if (! $patient instanceof TelemedicinePatient) {
        $this->markTestSkipped('No hay pacientes TDG con coordinaciones en la base local.');
    }

    $this->actingAs(User::factory()->make([
        'id' => 999_999_003,
        'email' => 'qa.proveedor@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => 999_999,
    ]));

    Livewire::test(PatientCoordinationServices::class, ['record' => $patient->getKey()])
        ->assertNotFound();
});

it('la tabla no muestra columnas de orden, cotización, factura ni monto', function (): void {
    $patient = pacienteConServiciosDeVariosEstatus();

    if (! $patient instanceof TelemedicinePatient) {
        $this->markTestSkipped('No hay pacientes con coordinaciones en la base local.');
    }

    $columns = array_keys(
        Livewire::test(PatientCoordinationServices::class, ['record' => $patient->getKey()])->instance()->getTable()->getColumns(),
    );

    expect($columns)->not->toContain('service_order_number')
        ->not->toContain('quote_number')
        ->not->toContain('bill_number')
        ->not->toContain('bill_price');
});

it('registra la ruta y la acción en el menú de la ficha', function (): void {
    expect(TelemedicinePatientResource::getPages())->toHaveKey('services')
        ->and(TelemedicinePatientResource::getPages()['services']->getPage())->toBe(PatientCoordinationServices::class);

    $view = file_get_contents(app_path('Filament/Operations/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php'));

    expect($view)->toContain('PatientCoordinationServicesAction::make()');
});

it('el resumen trata como vacíos los rellenos de los formularios', function (mixed $value, ?string $expected): void {
    $meaningful = new ReflectionMethod(PatientCoordinationServices::class, 'meaningful');

    expect($meaningful->invoke(null, $value))->toBe($expected);
})->with([
    'puntos suspensivos' => ['...', null],
    'raya' => ['—', null],
    'guion' => ['-', null],
    'no aplica' => ['n/a', null],
    'nulo' => [null, null],
    'solo espacios' => ['   ', null],
    'dato real' => ['  TDG ', 'TDG'],
]);

it('las órdenes y cotizaciones del detalle se pueden ver y descargar', function (): void {
    $record = OperationCoordinationService::query()
        ->whereNotNull('telemedicine_patient_id')
        ->whereHas('operationServiceOrders')
        ->whereHas('operationQuoteGenerators')
        ->latest('id')
        ->first();

    if (! $record instanceof OperationCoordinationService) {
        $this->markTestSkipped('No hay coordinaciones con orden y cotización en la base local.');
    }

    $order = $record->operationServiceOrders()->latest('id')->firstOrFail();
    $quoteNumber = App\Support\Operations\CoordinationServiceQuoteManager::formatCoordinationQuoteNumber(
        (int) $record->operationQuoteGenerators()->latest('id')->value('id'),
    );

    Livewire::test(PatientCoordinationServices::class, ['record' => $record->telemedicine_patient_id])
        ->call('loadTable')
        ->mountTableAction('detail', $record)
        ->assertMountedActionModalSee(['Órdenes', 'Cotizaciones', (string) $order->order_number, $quoteNumber, 'Ver', 'Descargar'])
        ->assertMountedActionModalSeeHtml('target="_blank"');
});

it('los enlaces de archivo escapan la URL y solo descargan con nombre si es archivo guardado', function (): void {
    $both = (string) PatientCoordinationServices::fileLinksHtml('https://x.test/a.pdf?a=1&b="2"', 'https://x.test/a.pdf', 'orden-1.pdf');

    expect($both)->toContain('>Ver</a>')
        ->toContain('>Descargar</a>')
        ->toContain('download="orden-1.pdf"')
        ->toContain('rel="noopener noreferrer"')
        ->not->toContain('b="2"');

    expect((string) PatientCoordinationServices::fileLinksHtml('https://x.test/ruta', 'https://x.test/ruta/download', null))
        ->not->toContain('download=');

    expect((string) PatientCoordinationServices::fileLinksHtml(null, null, null))
        ->toContain('Sin archivo')
        ->not->toContain('<a ');
});

it('no arma enlaces a archivos inexistentes ni fuera del disco público', function (?string $path): void {
    $publicFileUrl = new ReflectionMethod(PatientCoordinationServices::class, 'publicFileUrl');

    expect($publicFileUrl->invoke(null, $path))->toBeNull();
})->with([
    'vacío' => [''],
    'nulo' => [null],
    'inexistente' => ['operation-service-orders/generated-pdf/no-existe-'.uniqid().'.pdf'],
    'sale del disco' => ['../../.env'],
]);
