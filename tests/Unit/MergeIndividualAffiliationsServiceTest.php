<?php

declare(strict_types=1);

use App\Support\Affiliations\MergeIndividualAffiliationsException;
use App\Support\Affiliations\MergeIndividualAffiliationsPreview;
use App\Support\Affiliations\MergeIndividualAffiliationsService;

it('exige exactamente un titular en el mapa de parentescos', function (): void {
    $blockers = MergeIndividualAffiliationsService::relationshipBlockers(
        [10, 11, 12],
        10,
        [
            10 => 'TITULAR',
            11 => 'ESPOSA',
            12 => 'HIJO',
        ],
    );

    expect($blockers)->toBe([]);
});

it('bloquea dos titulares o un titular que no es la persona elegida', function (): void {
    expect(MergeIndividualAffiliationsService::relationshipBlockers(
        [10, 11],
        10,
        [10 => 'TITULAR', 11 => 'TITULAR'],
    ))->toContain('El grupo unificado debe tener exactamente un titular.')
        ->toContain('Solo la persona elegida como titular puede tener parentesco TITULAR.');

    expect(MergeIndividualAffiliationsService::relationshipBlockers(
        [10, 11],
        10,
        [10 => 'ESPOSO', 11 => 'ESPOSA'],
    ))->toContain('La persona elegida como titular debe tener parentesco TITULAR.');
});

it('bloquea al titular si no pertenece al grupo', function (): void {
    expect(MergeIndividualAffiliationsService::relationshipBlockers(
        [10, 11],
        99,
        [10 => 'TITULAR', 11 => 'HIJO'],
    ))->toContain('El titular elegido no pertenece al grupo que se va a unificar.');
});

it('normaliza cedulas ignorando puntos, guiones y espacios', function (): void {
    expect(MergeIndividualAffiliationsService::normalizeIdentification('V-12.345.678'))
        ->toBe('V12345678')
        ->and(MergeIndividualAffiliationsService::normalizeIdentification(' v 12345678 '))
        ->toBe('V12345678');
});

it('detecta cedulas duplicadas en el grupo', function (): void {
    expect(MergeIndividualAffiliationsService::duplicateIdentificationBlockers([
        'V-1.234',
        'V1234',
        'J-9',
    ]))->not->toBeEmpty();
});

it('el preview HTML escapa datos y no permite ejecutar con bloqueos', function (): void {
    $preview = new MergeIndividualAffiliationsPreview(
        blockers: ['El plan no coincide <script>'],
        warnings: ['Comisiones históricas se conservan'],
        target: [
            'id' => 1,
            'code' => 'TDEC-IND-0001',
            'titular' => 'Papa <b>',
            'status' => 'ACTIVA',
            'plan_id' => 1,
            'fee_anual' => 100,
            'total_amount' => 100,
            'family_members' => 1,
            'agency' => 'TDG-100',
            'frequency' => 'ANUAL',
        ],
        sources: [[
            'id' => 2,
            'code' => 'TDEC-IND-0002',
            'titular' => 'Mama',
            'status' => 'ACTIVA',
            'fee_anual' => 80,
            'family_members' => 1,
        ]],
        members: [[
            'affiliate_id' => 10,
            'name' => 'Papa <b>',
            'identification' => 'V-1',
            'from_code' => 'TDEC-IND-0001',
            'old_relationship' => 'TITULAR',
            'new_relationship' => 'TITULAR',
            'fee_before' => 100.0,
            'fee_after' => 100.0,
            'status' => 'ACTIVO',
        ]],
        collectionsToCancel: [],
        annualCollectionsToCancel: [],
        renovationsToCancel: [],
        telemedicinePatients: [],
        newFeeAnual: 180,
        newTotalAmount: 180,
        newFamilyMembers: 2,
        pendingCollectionsToRecalculate: 1,
    );

    $html = (string) $preview->toHtml();

    expect($preview->canExecute())->toBeFalse()
        ->and($html)->toContain('TDEC-IND-0001')
        ->and($html)->toContain('Papa &lt;b&gt;')
        ->and($html)->not->toContain('<script>')
        ->and($html)->toContain('EXCLUIDO');
});

it('el servicio unifica dentro de transaccion, no borra y cancela solo cuotas pendientes', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Affiliations/MergeIndividualAffiliationsService.php');

    expect($source)
        ->toContain('DB::transaction')
        ->toContain('lockForUpdate')
        ->toContain("status = 'EXCLUIDO'")
        ->toContain('COLLECTION_CANCELLED_STATUS = \'CANCELADO\'')
        ->toContain('COLLECTION_PENDING_STATUS = \'POR PAGAR\'')
        ->toContain('RENOVATION_CANCELLED_STATUS = \'ANULADA\'')
        ->toContain('RegenerateMergedAffiliationDocumentsJob::dispatch')
        ->toContain('DB::afterCommit')
        ->toContain('AUDIT_AFFILIATION_FAMILY_MERGE')
        ->not->toContain('->delete()')
        ->not->toContain('migrate:fresh');
});

it('el job de documentos va en cola y no bloquea el merge', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/RegenerateMergedAffiliationDocumentsJob.php');

    expect($job)
        ->toContain('implements ShouldQueue')
        ->toContain("config('affiliate-card.documents_queue', 'documents')")
        ->toContain('regenerateCertificateAndTarjetas')
        ->toContain('dispatchForIndividual');
});

it('el comando es dry-run por defecto y exige --execute con motivo', function (): void {
    $command = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/MergeIndividualAffiliationsCommand.php');

    expect($command)
        ->toContain('{--execute : Sin este flag solo muestra la vista previa}')
        ->toContain('if (! $this->option(\'execute\'))')
        ->toContain('Para ejecutar debe indicar --reason=');
});

it('lanza una excepcion con los bloqueos unidos', function (): void {
    $exception = MergeIndividualAffiliationsException::fromBlockers([
        'El plan no coincide.',
        'El plan no coincide.',
        'Falta el titular.',
    ]);

    expect($exception->getMessage())
        ->toContain('El plan no coincide.')
        ->toContain('Falta el titular.');
});
