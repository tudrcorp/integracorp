<?php

declare(strict_types=1);

use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Telemedicine\TelemedicineCaseDeletion;

function telemedicineCaseDeletionPath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

function telemedicineCaseDeletionSource(string $path): string
{
    return (string) file_get_contents(telemedicineCaseDeletionPath($path));
}

it('reconoce el estatus ELIMINADO sin importar espacios ni minúsculas', function (): void {
    expect(TelemedicineCaseDeletion::STATUS)->toBe('ELIMINADO')
        ->and(TelemedicineCaseDeletion::statusIsDeleted('ELIMINADO'))->toBeTrue()
        ->and(TelemedicineCaseDeletion::statusIsDeleted('  eliminado '))->toBeTrue()
        ->and(TelemedicineCaseDeletion::statusIsDeleted('EN SEGUIMIENTO'))->toBeFalse()
        ->and(TelemedicineCaseDeletion::statusIsDeleted(null))->toBeFalse();
});

it('advierte sobre el alta médica pero no la trata como estatus bloqueante', function (): void {
    expect(TelemedicineCaseDeletion::SENSITIVE_STATUSES)->toBe(['ALTA MEDICA'])
        ->and(TelemedicineCaseDeletion::canBeDeleted(null))->toBeFalse()
        ->and(TelemedicineCaseDeletion::blockedReason(null))
        ->toBe('El servicio no tiene un caso de telemedicina vinculado.');
});

it('registra el permiso de eliminación solo en el módulo de operaciones', function (): void {
    $slug = BusinessFilamentActionPermissionRegistry::DELETE_TELEMEDICINE_CASE;

    expect($slug)->toBe('eliminar-caso-servicios-medicos')
        ->and(BusinessFilamentActionPermissionRegistry::modulesForSlug($slug))->toBe(['OPERACIONES'])
        ->and(BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule($slug, 'OPERACIONES'))->toBeTrue()
        ->and(BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule($slug, 'NEGOCIOS'))->toBeFalse()
        ->and(BusinessFilamentActionPermissionRegistry::navigationGroupForSlug($slug))->toBe('COORDINACIÓN DE SERVICIOS');
});

it('sustituye el borrado físico del cuadro de control por la eliminación lógica', function (): void {
    $table = telemedicineCaseDeletionSource(
        'app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'
    );

    expect($table)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('use Filament\Actions\DeleteBulkAction;')
        ->toContain('CoordinationServiceCaseDeletion::makeDeleteBulkAction()')
        ->toContain('CoordinationServiceCaseDeletion::makeRestoreBulkAction()');
});

it('exige permiso y motivo para eliminar o restaurar un caso', function (): void {
    $action = telemedicineCaseDeletionSource('app/Support/Operations/CoordinationServiceCaseDeletion.php');

    expect($action)
        ->toContain('BusinessFilamentActionPermissionRegistry::DELETE_TELEMEDICINE_CASE')
        ->toContain("Textarea::make('deletion_reason')")
        ->toContain("Textarea::make('restoration_reason')")
        ->toContain('->minLength(10)')
        ->toContain('self::userCanDeleteCases() && ! self::viewingDeletedTab($livewire)')
        ->toContain('self::userCanDeleteCases() && self::viewingDeletedTab($livewire)');
});

it('publica la pestaña de eliminados solo a quien puede eliminar casos', function (): void {
    $page = telemedicineCaseDeletionSource(
        'app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ListOperationCoordinationServices.php'
    );

    expect($page)
        ->toContain('if (CoordinationServiceCaseDeletion::userCanDeleteCases())')
        ->toContain("Tab::make('ELIMINADOS')")
        ->toContain('CoordinationServiceCaseDeletion::applyDeletedCasesScope($query)');
});

it('oculta las trazas del caso en todos los modelos que lo referencian', function (): void {
    $models = [
        'ObservationCase',
        'OperationAccountsReceivable',
        'OperationCoordinationService',
        'OperationMedicalAppointment',
        'OperationQuoteGenerator',
        'OperationServiceStatistic',
        'TelemedicineAmdInform',
        'TelemedicineCaseChatRead',
        'TelemedicineCaseMessage',
        'TelemedicineConsultationPatient',
        'TelemedicineDocument',
        'TelemedicineFollowUp',
        'TelemedicineMedicalReport',
        'TelemedicineOperationsLog',
        'TelemedicinePatientLab',
        'TelemedicinePatientMedications',
        'TelemedicinePatientSpecialty',
        'TelemedicinePatientStudy',
    ];

    foreach ($models as $model) {
        expect(telemedicineCaseDeletionSource('app/Models/'.$model.'.php'))
            ->toContain('use HidesDeletedTelemedicineCaseTraces;');
    }

    expect(telemedicineCaseDeletionSource('app/Models/TelemedicineCase.php'))
        ->toContain('#[ScopedBy([HideDeletedTelemedicineCasesScope::class])]')
        ->and(telemedicineCaseDeletionSource('app/Models/OperationServiceOrder.php'))
        ->toContain('#[ScopedBy([HideDeletedTelemedicineCaseServiceOrdersScope::class])]');
});

it('deja el inventario fuera del ocultamiento para no descuadrar el stock', function (): void {
    expect(telemedicineCaseDeletionSource('app/Models/OperationInventoryMovement.php'))
        ->not->toContain('HidesDeletedTelemedicineCaseTraces')
        ->and(telemedicineCaseDeletionSource('app/Models/OperationInventoryOutflow.php'))
        ->not->toContain('HidesDeletedTelemedicineCaseTraces');
});

it('devuelve los cupos clínicos y escribe bitácora y auditoría al eliminar', function (): void {
    $service = telemedicineCaseDeletionSource('app/Services/TelemedicineCaseLogicalDeletionService.php');

    expect($service)
        ->toContain('ClinicalUsageLedger::reverseForCase((int) $case->id)')
        ->toContain('DB::transaction(')
        ->toContain("SecurityAudit::log(\n                'AUDIT_TELEMEDICINE_CASE_LOGICALLY_DELETED'")
        ->toContain("'AUDIT_TELEMEDICINE_CASE_LOGICALLY_RESTORED'")
        ->toContain('ObservationCase::query()->create(')
        ->toContain('\'deletion_status_before\' => $previousStatus');
});
