<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\BitacoraDeCaso;
use App\Jobs\SendTelemedicineCaseBitacoraJob;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use App\Support\Operations\TelemedicineCaseBitacora;
use Illuminate\Contracts\Queue\ShouldQueue;

uses(Tests\TestCase::class);

it('registra la página Bitácora de Caso en Telemedicina de operaciones', function (): void {
    expect(BitacoraDeCaso::getNavigationLabel())->toBe('Bitácora de Caso')
        ->and(BitacoraDeCaso::getNavigationGroup())->toBe('TELEMEDICINA')
        ->and(BitacoraDeCaso::getNavigationSort())->toBe(4)
        ->and(BitacoraDeCaso::getSlug())->toBe('bitacora-de-caso');
});

it('registra el permiso de navegación bitacora-de-caso', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(BitacoraDeCaso::class))
        ->toBe(['bitacora-de-caso']);

    $aliases = UserFormPermissionOptions::navToLegacySlugAliases();

    expect($aliases['bitacoradecaso'] ?? null)->toBe(['bitacora-de-caso']);
});

it('busca casos por código, nombre y cédula', function (): void {
    $sql = TelemedicineCaseBitacora::searchQuery('23865467')->toSql();

    expect($sql)
        ->toContain('code')
        ->toContain('patient_name')
        ->toContain('nro_identificacion')
        ->toContain('full_name');
});

it('nombra el PDF de bitácora con el código del caso', function (): void {
    $case = new App\Models\TelemedicineCase([
        'code' => '91819-0110',
    ]);
    $case->id = 110;

    expect(TelemedicineCaseBitacora::documentName($case))->toBe('BITACORA-91819-0110.pdf');
});

it('usa autorización de menú, buscador y envíos en cola', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Pages/BitacoraDeCaso.php');
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Concerns/InteractsWithTelemedicineCaseBitacora.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/pages/bitacora-de-caso.blade.php');
    $documentsView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/partials/bitacora-caso-documents.blade.php');
    $downloadLink = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/partials/bitacora-caso-download-link.blade.php');
    $amdView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/partials/bitacora-caso-amd.blade.php');
    $accordion = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/operations/bitacora-accordion.blade.php');
    $assembler = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/TelemedicineCaseBitacora.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendTelemedicineCaseBitacoraJob.php');
    $pdf = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/bitacora-caso.blade.php');
    $pdfDocuments = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/bitacora-caso-documents.blade.php');

    expect($page)
        ->toContain('AuthorizesDepartmentNavigation')
        ->toContain('InteractsWithTelemedicineCaseBitacora');

    expect($trait)
        ->toContain('SendTelemedicineCaseBitacoraJob::dispatch')
        ->toContain('Descargar PDF')
        ->toContain('Enviar por WhatsApp')
        ->toContain('Enviar por correo')
        ->toContain("data_get(\$this->dossier, 'contacts.phone'")
        ->toContain("data_get(\$this->dossier, 'contacts.email'")
        ->toContain('hasSelectedCase()')
        ->toContain('refreshHeaderActions()')
        ->toContain('#[Computed]')
        ->not->toContain('public ?array $dossier');

    expect($view)
        ->toContain('wire:model.live.debounce.300ms="search"')
        ->toContain('selectCase')
        ->toContain('Consultas y notas médicas')
        ->toContain('Observaciones del caso')
        ->toContain('Alta médica')
        ->toContain('Cargando bitácora')
        ->toContain('bitacora-caso-documents');

    expect($documentsView)
        ->toContain('Documentos del caso')
        ->toContain('download_url')
        ->toContain('bitacora-caso-download-link');

    expect($downloadLink)
        ->toContain('Descargar')
        ->toContain('download=')
        ->toContain('arrow-down-tray');

    expect($amdView)
        ->toContain('Informes AMD')
        ->toContain('bitacora-caso-download-link')
        ->toContain('document_exists');

    expect($accordion)->toContain('x-collapse');

    expect($assembler)
        ->toContain('orWhere(\'nro_identificacion\'')
        ->toContain('observationRows')
        ->toContain('TelemedicineCaseDocumentsCatalog::entries')
        ->toContain('dischargeSummary')
        ->toContain('$requestDossiers');

    expect($job)
        ->toContain('implements ShouldQueue')
        ->toContain('TelemedicineCaseDocumentDeliveryService::send')
        ->toContain("onQueue('system')");

    expect($pdf)
        ->toContain('Bitácora de caso')
        ->toContain('Consultas y notas médicas')
        ->toContain('Alta médica')
        ->toContain('#00ADEF')
        ->toContain('DejaVu Sans')
        ->toContain('logoNewPdf.png')
        ->toContain('TU DOCTOR EN CASA, C. A.')
        ->toContain('position: fixed')
        ->toContain('page-break-after: avoid')
        ->toContain('tr.stick')
        ->not->toContain('#052F60')
        ->not->toContain('keep-together');

    expect($pdfDocuments)->toContain('Documentos del caso');

    expect(is_subclass_of(SendTelemedicineCaseBitacoraJob::class, ShouldQueue::class))->toBeTrue();
});

it('el PDF de bitácora replica el diseño homologado de entregables', function (): void {
    $html = view('documents.bitacora-caso', [
        'dossier' => [
            'code' => '91819-0110',
            'status' => 'ALTA MEDICA',
            'is_discharge' => true,
            'header' => [
                'Código' => '91819-0110',
                'Estatus' => 'ALTA MEDICA',
                'Motivo' => 'Control',
            ],
            'patient' => [
                'Nombre' => 'Ana Pérez',
                'Cédula' => 'V-123',
                'Edad' => '40',
            ],
            'discharge' => [
                'Fecha' => '11/09/2026',
                'Indicación' => 'Alta',
            ],
            'consultations' => [
                ['Fecha' => '10/09/2026', 'Nota' => 'Evolución favorable'],
            ],
            'documents' => [
                [
                    'category' => 'Informe médico',
                    'reference' => 'INF-1',
                    'document_name' => 'informe.pdf',
                    'uploaded_at_label' => '11/09/2026 10:00',
                    'exists' => true,
                ],
            ],
        ],
    ])->render();

    expect($html)
        ->toContain('#00ADEF')
        ->toContain('DejaVu Sans')
        ->toContain('TU DOCTOR EN CASA, C. A.')
        ->toContain('Bitácora de caso')
        ->toContain('Ana Pérez')
        ->toContain('informe.pdf')
        ->toContain('data:image/png;base64,')
        ->toContain('tr class="stick"')
        ->not->toContain('#052F60')
        ->not->toContain('keep-together');
});

it('parte textos largos del PDF para no dejar cabeceras huérfanas', function (): void {
    $chunks = TelemedicineCaseBitacora::pdfTextChunks(str_repeat('Nota clínica de evolución. ', 80), 80);

    expect($chunks)->not->toHaveCount(1)
        ->and($chunks[0])->toContain('Nota clínica')
        ->and(implode(' ', $chunks))->toContain('evolución')
        ->and(max(array_map(fn (string $chunk): int => mb_strlen($chunk), $chunks)))->toBeLessThanOrEqual(80);

    $rows = TelemedicineCaseBitacora::pdfFieldRows([
        'Fecha' => '10/09/2026',
        'Médico' => 'Dra. López',
        'Historia' => str_repeat('Antecedente relevante. ', 60),
    ]);

    expect($rows[0]['type'])->toBe('pair')
        ->and($rows[1]['type'])->toBe('stack')
        ->and($rows[1]['cells'][0]['chunks'])->toHaveCount(2);
});

it('el HTML de consultas largas pagina por filas junto a la cabecera', function (): void {
    $longNote = str_repeat('Evolución favorable del cuadro abdominal. ', 70);

    $html = view('documents.partials.bitacora-caso-entries', [
        'title' => 'Consultas y notas médicas',
        'entries' => [
            [
                'Fecha' => '10/09/2026',
                'Historia de enfermedad actual' => $longNote,
            ],
        ],
    ])->render();

    expect($html)
        ->toContain('Consultas y notas médicas')
        ->toContain('tr class="stick"')
        ->toContain('prose-box')
        ->toContain('Evolución favorable del cuadro abdominal.')
        ->and(substr_count($html, 'prose-box'))->toBeGreaterThan(1);
});

it('registra Bitácora de Caso en el panel de telemedicina para los médicos', function (): void {
    $page = App\Filament\Telemedicina\Pages\BitacoraDeCaso::class;
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Pages/BitacoraDeCaso.php');
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/TelemedicinaPanelProvider.php');
    $query = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php');
    $assembler = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/TelemedicineCaseBitacora.php');

    expect($page::getNavigationLabel())->toBe('Bitácora de Caso')
        ->and($page::getNavigationGroup())->toBe('GESTIÓN TELEMÉDICA')
        ->and($page::getNavigationSort())->toBe(4)
        ->and($page::getSlug())->toBe('bitacora-de-caso');

    expect($source)
        ->toContain('InteractsWithTelemedicineCaseBitacora')
        ->toContain('SCOPE_TELEMEDICINA')
        ->toContain('filament.operations.pages.bitacora-de-caso')
        ->not->toContain('AuthorizesDepartmentNavigation');

    expect($provider)
        ->toContain("discoverPages(in: app_path('Filament/Telemedicina/Pages')");

    expect($assembler)
        ->toContain('SCOPE_TELEMEDICINA')
        ->toContain('applyTelemedicinaBitacoraConstraints');

    $methodStart = (int) strpos($query, 'function applyTelemedicinaBitacoraConstraints');
    $methodEnd = (int) strpos($query, 'function constrainToTdgDoctorsCases');
    $method = substr($query, $methodStart, $methodEnd - $methodStart);

    expect($method)
        ->toContain("where('telemedicine_doctor_id', \$user->doctor_id)")
        ->toContain('constrainToTdgDoctorsCases')
        ->not->toContain("status', '!=', 'ALTA MEDICA");
});
