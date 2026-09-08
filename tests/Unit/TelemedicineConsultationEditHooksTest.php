<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\EditTelemedicineConsultationPatient;
use Filament\Resources\Pages\EditRecord;

/**
 * La página de edición tenía un `afterCreate()` copiado de la de creación que
 * Filament nunca llamaba. Además de muerto era peligroso: moverlo a `afterSave()`
 * habría duplicado medicamentos y laboratorios en cada edición y descontado el
 * inventario dos veces. Estos tests impiden que vuelva.
 */
const PAGINA_EDITAR_CONSULTA = __DIR__.'/../../app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php';

it('la página de edición no declara un hook de creación que nadie invoca', function (): void {
    $pagina = new ReflectionClass(EditTelemedicineConsultationPatient::class);

    expect($pagina->getParentClass()->getName())->toBe(EditRecord::class)
        ->and($pagina->hasMethod('afterCreate'))->toBeFalse()
        ->and($pagina->hasMethod('afterSave'))->toBeTrue();
});

it('solo la página de creación de Filament invoca el hook afterCreate', function (): void {
    $paginasFilament = dirname(__DIR__, 2).'/vendor/filament/filament/src/Resources/Pages';

    $invocan = [];

    foreach (glob($paginasFilament.'/*.php') ?: [] as $archivo) {
        if (str_contains((string) file_get_contents($archivo), "callHook('afterCreate')")) {
            $invocan[] = basename($archivo);
        }
    }

    // Si Filament pasara a llamarlo también desde EditRecord, este test avisa:
    // habría que decidir qué hace la edición, no heredar el hook por accidente.
    expect($invocan)->toBe(['CreateRecord.php']);
});

it('editar una consulta no reemite recetas ni órdenes clínicas', function (): void {
    $contenido = file_get_contents(PAGINA_EDITAR_CONSULTA);

    expect($contenido)
        ->not->toContain('GeneratePdfMedicamentos')
        ->not->toContain('GeneratePdfLaboratorio')
        ->not->toContain('GeneratePdfImagenologia')
        ->not->toContain('GeneratePdfEspecialista')
        ->not->toContain('SendTelemedicinaDocument')
        ->not->toContain('new TelemedicinePatientMedications')
        ->not->toContain('TelemedicineMedicationInventoryDeductor')
        ->not->toContain('dd($th)');
});

it('editar un seguimiento regenera el informe de seguimiento', function (): void {
    $contenido = file_get_contents(PAGINA_EDITAR_CONSULTA);

    expect($contenido)
        ->toContain('GeneratePdfInformeSeguimiento::dispatch')
        ->toContain('TelemedicineFollowUpReportDocument::payloadFromSavedConsultation')
        ->toContain('dispatchFollowUpReportDocument');
});

it('la edición conserva lo que sí debe correr al guardar', function (): void {
    $contenido = file_get_contents(PAGINA_EDITAR_CONSULTA);

    expect($contenido)
        ->toContain('protected function afterSave(): void')
        ->toContain('TelemedicineSupplyConsumptionRecorder')
        ->toContain('TelemedicineInitialDiagnosisUpdater::syncFromFollowUp');
});
