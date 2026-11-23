<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineCases\Schemas\TelemedicineCaseInfolist;
use App\Http\Controllers\TelemedicineCaseRecordController;
use App\Jobs\GenerateTelemedicineCaseRecordJob;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDocument;
use App\Models\User;
use App\Services\TelemedicineCaseRecordPdfService;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Telemedicine\TelemedicineCaseDocumentsCatalog;
use App\Support\Telemedicine\TelemedicineCaseRecordAction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\ExecutableFinder;

uses(Tests\TestCase::class);

/**
 * Expediente del caso. Escribe dentro de una transacción que siempre se revierte;
 * los documentos y el expediente van a discos falsos.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    config(['cache.default' => 'array', 'queue.default' => 'sync']);
    Storage::fake('public');
    Storage::fake('local');

    /** Un caso sin documentos: la prueba no toca ni borra documentos reales. */
    $this->case = TelemedicineCase::query()
        ->whereNotNull('telemedicine_patient_id')
        ->latest('id')
        ->limit(50)
        ->get()
        ->first(fn (TelemedicineCase $case): bool => TelemedicineCaseDocumentsCatalog::entries($case) === []);

    if ($this->case === null) {
        $this->markTestSkipped('La base no tiene un caso de telemedicina sin documentos para la prueba.');
    }
});

afterEach(function (): void {
    DB::rollBack();
});

function caseRecordDocument(TelemedicineCase $case, string $name, ?string $contents, int $minutesAgo): void
{
    if ($contents !== null) {
        Storage::disk('public')->put('telemedicina-doc/'.$name, $contents);
    }

    $document = TelemedicineDocument::query()->create([
        'telemedicine_case_id' => $case->id,
        'telemedicine_consultation_id' => 0,
        'telemedicine_patient_id' => (int) $case->telemedicine_patient_id,
        'name' => $name,
    ]);
    $document->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();
}

function caseRecordPdf(string $html, string $orientation = 'portrait'): string
{
    return Pdf::loadHTML($html)->setPaper('a4', $orientation)->output();
}

function caseRecordJpeg(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

/**
 * PDF mínimo con tabla de referencias comprimida (PDF 1.5), que el parser gratuito de FPDI no abre.
 */
function caseRecordCompressedXrefPdf(): string
{
    $objects = [
        "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n",
        "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n",
        "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n",
    ];
    $pdf = "%PDF-1.5\n";
    $offsets = [];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefOffset = strlen($pdf);
    $rows = "\x00\x00\x00\xff";

    foreach ([...$offsets, $xrefOffset] as $offset) {
        $rows .= "\x01".pack('n', $offset)."\x00";
    }

    return $pdf.'4 0 obj<</Type/XRef/Size 5/W[1 2 1]/Root 1 0 R/Length '.strlen($rows).">>stream\n".$rows
        ."\nendstream\nendobj\nstartxref\n{$xrefOffset}\n%%EOF";
}

function caseRecordPageOrientations(string $absolutePath): array
{
    $pdf = new Fpdi;
    $pages = $pdf->setSourceFile($absolutePath);
    $orientations = [];

    for ($page = 1; $page <= $pages; $page++) {
        $orientations[] = $pdf->getTemplateSize($pdf->importPage($page))['orientation'];
    }

    return $orientations;
}

it('une portada, índice y los documentos en orden cronológico, y omite con motivo lo que no puede unir', function (): void {
    caseRecordDocument($this->case, 'informe.pdf', caseRecordPdf('<div style="page-break-after:always">1</div><div style="page-break-after:always">2</div><div>3</div>'), 50);
    caseRecordDocument($this->case, 'foto.jpg', caseRecordJpeg(600, 900), 40);
    caseRecordDocument($this->case, 'laboratorio.pdf', caseRecordPdf('<h1>Lab</h1>', 'landscape'), 30);
    caseRecordDocument($this->case, 'danado.pdf', "%PDF-1.4\nsin tabla de referencias", 20);
    caseRecordDocument($this->case, 'no-existe.pdf', null, 10);

    $result = TelemedicineCaseRecordPdfService::generate($this->case, 'QA');
    $reasons = array_column($result['skipped'], 'reason', 'name');

    expect($result['reused'])->toBeFalse()
        ->and($result['included'])->toBe(3)
        ->and($result['pages'])->toBe(7)
        ->and($reasons)->toBe([
            'danado.pdf' => 'El PDF está dañado o no se pudo leer.',
            'no-existe.pdf' => 'El archivo no está en el servidor.',
        ])
        ->and(Storage::disk('local')->exists($result['path']))->toBeTrue()
        ->and(Storage::disk('public')->exists($result['path']))->toBeFalse()
        ->and(caseRecordPageOrientations(Storage::disk('local')->path($result['path'])))->toBe(['P', 'P', 'P', 'P', 'P', 'P', 'L'])
        ->and(Storage::disk('local')->allFiles(TelemedicineCaseRecordPdfService::DIRECTORY.'/tmp'))->toBe([]);
});

it('una imagen horizontal entra como página horizontal', function (): void {
    caseRecordDocument($this->case, 'panoramica.jpg', caseRecordJpeg(1600, 900), 5);

    $result = TelemedicineCaseRecordPdfService::generate($this->case);

    expect(caseRecordPageOrientations(Storage::disk('local')->path($result['path'])))->toBe(['P', 'P', 'L']);
});

it('un PDF con compresión moderna se normaliza si el servidor puede, y si no se omite con su motivo', function (): void {
    caseRecordDocument($this->case, 'escaneo.pdf', caseRecordCompressedXrefPdf(), 5);

    $result = TelemedicineCaseRecordPdfService::generate($this->case);
    $finder = new ExecutableFinder;

    if ($finder->find('qpdf') !== null || $finder->find('gs') !== null) {
        expect($result['included'])->toBe(1)->and($result['skipped'])->toBe([]);

        return;
    }

    expect($result['included'])->toBe(0)
        ->and($result['skipped'][0]['reason'])->toBe('El PDF usa una compresión que el servidor no pudo leer.');
});

it('reutiliza el expediente vigente y lo reemplaza cuando cambian los documentos', function (): void {
    caseRecordDocument($this->case, 'informe.pdf', caseRecordPdf('<h1>Informe</h1>'), 20);

    $first = TelemedicineCaseRecordPdfService::generate($this->case);
    $again = TelemedicineCaseRecordPdfService::generate($this->case);

    expect($again['reused'])->toBeTrue()
        ->and($again['fingerprint'])->toBe($first['fingerprint'])
        ->and(TelemedicineCaseRecordPdfService::currentFingerprint($this->case))->toBe($first['fingerprint']);

    caseRecordDocument($this->case, 'nuevo.pdf', caseRecordPdf('<h1>Nuevo</h1>'), 1);
    $replaced = TelemedicineCaseRecordPdfService::generate($this->case);

    expect($replaced['reused'])->toBeFalse()
        ->and($replaced['fingerprint'])->not->toBe($first['fingerprint'])
        ->and(Storage::disk('local')->exists($first['path']))->toBeFalse()
        ->and(Storage::disk('local')->exists($replaced['path']))->toBeTrue();
});

it('la portada lleva los datos del paciente y del caso', function (): void {
    $labels = array_column(TelemedicineCaseRecordPdfService::patientDetails($this->case), 0);

    expect($labels)->toBe(['Paciente', 'Cédula', 'Edad', 'Sexo', 'Teléfono', 'Correo', 'Plan', 'Afiliación', 'Caso', 'Estatus del caso', 'Fecha de apertura', 'Médico asignado']);

    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/telemedicine-case-record.blade.php');

    expect($view)
        ->toContain('.cover-cell { height: 296mm; vertical-align: middle; text-align: center;')
        ->toContain('Índice del expediente')
        ->toContain('Documentos no incluidos');
});

it('pedir el expediente lo encola, y si ya está vigente lo entrega sin encolar', function (): void {
    $user = User::query()->where('status', 'ACTIVO')->first();
    caseRecordDocument($this->case, 'informe.pdf', caseRecordPdf('<h1>Informe</h1>'), 20);

    Queue::fake();
    TelemedicineCaseRecordAction::request($this->case, $user);

    expect(Queue::pushed(GenerateTelemedicineCaseRecordJob::class, fn (GenerateTelemedicineCaseRecordJob $job): bool => $job->caseId === $this->case->id && $job->userId === $user->id))->toHaveCount(1);

    TelemedicineCaseRecordPdfService::generate($this->case);
    Queue::fake();
    TelemedicineCaseRecordAction::request($this->case, $user);

    expect(Queue::pushed(GenerateTelemedicineCaseRecordJob::class))->toHaveCount(0);
});

it('un caso sin documentos no encola nada', function (): void {
    Queue::fake();
    TelemedicineCaseRecordAction::request($this->case, User::query()->where('status', 'ACTIVO')->first());

    expect(Queue::pushed(GenerateTelemedicineCaseRecordJob::class))->toHaveCount(0);
});

it('el job avisa con el botón para descargar el expediente', function (): void {
    $user = User::query()->where('status', 'ACTIVO')->first();
    caseRecordDocument($this->case, 'informe.pdf', caseRecordPdf('<h1>Informe</h1>'), 20);

    (new GenerateTelemedicineCaseRecordJob($this->case->id, $user->id))->handle();

    $data = json_decode((string) DB::table('notifications')->where('notifiable_id', $user->id)->orderByDesc('created_at')->value('data'), true);

    expect($data['title'])->toBe('Expediente listo')
        ->and($data['actions'][0]['url'])->toContain('/operations/telemedicine-cases/'.$this->case->id.'/expediente/');
});

it('solo genera y descarga quien tiene el permiso nuevo en Operaciones', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::GENERATE_TELEMEDICINE_CASE_RECORD]['modules'])->toBe(['OPERACIONES'])
        ->and(BusinessFilamentActionPermissionRegistry::GENERATE_TELEMEDICINE_CASE_RECORD)->toBe('generar-expediente-caso')
        ->and(TelemedicineCaseRecordAction::userCanGenerate(null))->toBeFalse()
        ->and(TelemedicineCaseRecordController::userCanDownload(null))->toBeFalse()
        ->and(Route::has('operations.telemedicine-cases.record.download'))->toBeTrue();

    $infolist = file_get_contents((new ReflectionClass(TelemedicineCaseInfolist::class))->getFileName());

    expect($infolist)->toContain('->headerActions([')
        ->toContain('TelemedicineCaseRecordAction::make()');
});
