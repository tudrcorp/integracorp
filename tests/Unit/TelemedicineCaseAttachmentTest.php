<?php

declare(strict_types=1);

use App\Enums\TelemedicineCaseAttachmentStage;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseAttachment;
use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\TelemedicineCaseAttachmentRegistrar;
use App\Support\Telemedicine\TelemedicineCaseDocumentsCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Estos tests escriben, así que corren dentro de una transacción que siempre se
 * revierte y con el disco público simulado. No se deja nada en la base ni en disco.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake(TelemedicineCaseAttachmentRegistrar::DISK);
});

afterEach(function (): void {
    DB::rollBack();
});

function casoDeTelemedicinaParaAdjuntos(): TelemedicineCase
{
    $case = TelemedicineCase::query()->orderByDesc('id')->first();

    if (! $case instanceof TelemedicineCase) {
        test()->markTestSkipped('No hay casos de telemedicina en la base para probar.');
    }

    return $case;
}

function subirAdjuntoFalso(int $caseId, string $name = 'resultado.pdf'): string
{
    $path = TelemedicineCaseAttachmentRegistrar::directoryForCase($caseId).'/'.Str::random(12).'-'.$name;
    Storage::disk(TelemedicineCaseAttachmentRegistrar::DISK)->put($path, '%PDF-1.4 prueba');

    return $path;
}

it('un caso sin consultas se marca como consulta inicial', function (): void {
    $stage = TelemedicineCaseAttachmentRegistrar::resolveStage(999_999_999, false, false);

    expect($stage['stage'])->toBe(TelemedicineCaseAttachmentStage::InitialConsultation)
        ->and($stage['follow_up_number'])->toBeNull();
});

it('editando la consulta inicial sigue siendo consulta inicial aunque haya consultas', function (): void {
    $caseId = (int) TelemedicineConsultationPatient::query()->value('telemedicine_case_id');

    if ($caseId < 1) {
        $this->markTestSkipped('No hay consultas de telemedicina en la base.');
    }

    $stage = TelemedicineCaseAttachmentRegistrar::resolveStage($caseId, true, true);

    expect($stage['stage'])->toBe(TelemedicineCaseAttachmentStage::InitialConsultation);
});

it('un caso con consultas numera el seguimiento siguiente, o conserva el número al editar', function (): void {
    $caseId = (int) TelemedicineConsultationPatient::query()->value('telemedicine_case_id');

    if ($caseId < 1) {
        $this->markTestSkipped('No hay consultas de telemedicina en la base.');
    }

    $followUps = TelemedicineConsultationPatient::query()
        ->where('telemedicine_case_id', $caseId)
        ->where('status', '!=', 'CONSULTA INICIAL')
        ->count();

    $new = TelemedicineCaseAttachmentRegistrar::resolveStage($caseId, false, false);
    $editing = TelemedicineCaseAttachmentRegistrar::resolveStage($caseId, false, true);

    expect($new['stage'])->toBe(TelemedicineCaseAttachmentStage::FollowUp)
        ->and($new['follow_up_number'])->toBe($followUps + 1)
        ->and($editing['follow_up_number'])->toBe(max(1, $followUps));
});

it('las etiquetas del momento distinguen consulta inicial y número de seguimiento', function (): void {
    expect(TelemedicineCaseAttachmentStage::InitialConsultation->labelWithNumber(3))->toBe('Consulta inicial')
        ->and(TelemedicineCaseAttachmentStage::FollowUp->labelWithNumber(2))->toBe('Seguimiento 2')
        ->and(TelemedicineCaseAttachmentStage::FollowUp->labelWithNumber(null))->toBe('Seguimiento');
});

it('registra el documento con su momento y aparece en los documentos de la bitácora', function (): void {
    $case = casoDeTelemedicinaParaAdjuntos();
    $path = subirAdjuntoFalso((int) $case->id);

    $attachments = TelemedicineCaseAttachmentRegistrar::register(
        case: $case,
        storedPaths: [$path],
        originalNames: [$path => 'Hematología Paciente.pdf'],
        description: '  Traído por el paciente  ',
        stage: TelemedicineCaseAttachmentStage::FollowUp,
        followUpNumber: 2,
        consultationId: null,
        doctorId: null,
        userId: null,
    );

    expect($attachments)->toHaveCount(1);

    $attachment = $attachments[0]->fresh();

    expect($attachment->stage)->toBe(TelemedicineCaseAttachmentStage::FollowUp)
        ->and($attachment->follow_up_number)->toBe(2)
        ->and($attachment->original_name)->toBe('Hematología Paciente.pdf')
        ->and($attachment->description)->toBe('Traído por el paciente')
        ->and($attachment->telemedicine_patient_id)->toBe($case->telemedicine_patient_id !== null ? (int) $case->telemedicine_patient_id : null);

    $entry = collect(TelemedicineCaseDocumentsCatalog::entries($case))
        ->firstWhere('file_path', $path);

    expect($entry)->not->toBeNull()
        ->and($entry['category'])->toBe('Seguimiento')
        ->and($entry['reference'])->toBe('Seguimiento 2')
        ->and($entry['document_name'])->toBe('Hematología Paciente.pdf')
        ->and($entry['exists'])->toBeTrue()
        ->and($entry['types'])->toContain('Traído por el paciente');
});

it('en consulta inicial no guarda número de seguimiento', function (): void {
    $case = casoDeTelemedicinaParaAdjuntos();
    $path = subirAdjuntoFalso((int) $case->id, 'foto.jpg');

    $attachment = TelemedicineCaseAttachmentRegistrar::register(
        $case, [$path], [], null, TelemedicineCaseAttachmentStage::InitialConsultation, 4, null, null, null,
    )[0];

    expect($attachment->follow_up_number)->toBeNull()
        ->and($attachment->original_name)->toBe(basename($path))
        ->and($attachment->description)->toBeNull();

    $entry = collect(TelemedicineCaseDocumentsCatalog::entries($case))->firstWhere('file_path', $path);

    expect($entry['category'])->toBe('Consulta inicial')
        ->and($entry['category_tone'])->toBe('primary');
});

it('rechaza rutas fuera de la carpeta del caso', function (): void {
    $case = casoDeTelemedicinaParaAdjuntos();
    $foreign = TelemedicineCaseAttachmentRegistrar::directoryForCase((int) $case->id + 1).'/ajeno.pdf';
    Storage::disk(TelemedicineCaseAttachmentRegistrar::DISK)->put($foreign, 'x');

    $before = TelemedicineCaseAttachment::query()->count();

    expect(fn () => TelemedicineCaseAttachmentRegistrar::register(
        $case, [$foreign, '../../.env', ''], [], null, TelemedicineCaseAttachmentStage::InitialConsultation, null, null, null, null,
    ))->toThrow(InvalidArgumentException::class);

    expect(TelemedicineCaseAttachment::query()->count())->toBe($before);
});

it('rechaza más archivos de los permitidos y borra los subidos', function (): void {
    $case = casoDeTelemedicinaParaAdjuntos();
    $paths = collect(range(1, TelemedicineCaseAttachmentRegistrar::MAX_FILES + 1))
        ->map(fn (int $index): string => subirAdjuntoFalso((int) $case->id, 'archivo-'.$index.'.pdf'))
        ->all();

    expect(fn () => TelemedicineCaseAttachmentRegistrar::register(
        $case, $paths, [], null, TelemedicineCaseAttachmentStage::InitialConsultation, null, null, null, null,
    ))->toThrow(InvalidArgumentException::class);

    foreach ($paths as $path) {
        Storage::disk(TelemedicineCaseAttachmentRegistrar::DISK)->assertMissing($path);
    }
});

it('la acción «Cargar documento» vive en el menú de acciones del asistente de consulta', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($contents)
        ->toContain("Action::make('upload_case_attachment')")
        ->toContain("->label('Cargar documento')")
        ->toContain('->storeFileNamesIn(\'original_names\')')
        ->toContain('TelemedicineCaseAttachmentRegistrar::register(')
        ->toContain('caseAttachmentStageLabel()');
});
