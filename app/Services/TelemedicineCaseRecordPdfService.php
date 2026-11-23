<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TelemedicineCase;
use App\Support\Telemedicine\TelemedicineCaseDocumentsCatalog;
use App\Support\Telemedicine\TelemedicinePatientDeliveryEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Expediente del caso de telemedicina: un solo PDF con portada (logo y datos del
 * paciente), índice y, a continuación, todos los documentos del «Centro de
 * documentos del caso» en orden cronológico.
 *
 * Los documentos salen de TelemedicineCaseDocumentsCatalog, la misma lista que ve
 * el analista. Un archivo que no se puede unir (no existe, dañado, protegido o en
 * un formato no compatible) no detiene el expediente: se omite y queda listado en
 * el índice con su motivo.
 *
 * El expediente se guarda en el disco privado con la huella de su contenido en el
 * nombre: si los documentos no cambiaron, se reutiliza el mismo archivo.
 */
final class TelemedicineCaseRecordPdfService
{
    public const DISK = 'local';

    public const DIRECTORY = 'telemedicine-case-records';

    /**
     * Sube cuando cambia el diseño del expediente, para no reutilizar archivos viejos.
     */
    private const LAYOUT_VERSION = 1;

    private const MAX_DOCUMENT_BYTES = 60 * 1024 * 1024;

    private const MAX_IMAGE_SIDE_PX = 2400;

    private const IMAGE_MARGIN_MM = 10;

    private const NORMALIZER_TIMEOUT_SECONDS = 90;

    /**
     * Documentos del caso, en orden cronológico, listos para unir o con el motivo
     * por el que no se pueden unir.
     *
     * @return list<array{name: string, category: string, reference: string, date: string, timestamp: int, path: ?string, kind: ?string, size: int, modified: int, reason: ?string}>
     */
    public static function documents(TelemedicineCase $case): array
    {
        $documents = [];

        foreach (TelemedicineCaseDocumentsCatalog::entries($case) as $entry) {
            $relative = trim((string) ($entry['file_path'] ?? ''));
            $document = [
                'name' => trim((string) ($entry['document_name'] ?? '')) ?: basename($relative) ?: 'Documento',
                'category' => trim((string) ($entry['category'] ?? '')) ?: 'Documento',
                'reference' => trim((string) ($entry['reference'] ?? '')),
                'date' => trim((string) ($entry['uploaded_at_label'] ?? '')),
                'timestamp' => (int) ($entry['sort_timestamp'] ?? 0),
                'path' => null,
                'kind' => null,
                'size' => 0,
                'modified' => 0,
                'reason' => null,
            ];

            [$document['path'], $document['kind'], $document['reason']] = self::resolveSource($relative);

            if ($document['path'] !== null) {
                $document['size'] = (int) @filesize($document['path']);
                $document['modified'] = (int) @filemtime($document['path']);
            }

            $documents[] = $document;
        }

        usort($documents, static fn (array $a, array $b): int => [$a['timestamp'], $a['name']] <=> [$b['timestamp'], $b['name']]);

        return $documents;
    }

    /**
     * Datos del paciente y del caso que van en la portada.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function patientDetails(TelemedicineCase $case): array
    {
        $case->loadMissing(['telemedicinePatient.plan', 'telemedicineDoctor']);
        $patient = $case->telemedicinePatient;

        $age = $case->patient_age ?: $patient?->age;

        if (! filled($age) && filled($patient?->birth_date)) {
            try {
                $age = Carbon::parse((string) $patient->birth_date)->age;
            } catch (Throwable) {
                $age = null;
            }
        }

        $affiliation = trim(implode(' · ', array_filter([
            filled($patient?->type_affiliation) ? mb_strtoupper((string) $patient->type_affiliation) : null,
            filled($patient?->code_affiliation) ? (string) $patient->code_affiliation : null,
            filled($patient?->name_corporate) ? (string) $patient->name_corporate : null,
        ])));

        return [
            ['Paciente', self::text($case->patient_name ?: $patient?->full_name)],
            ['Cédula', self::text($patient?->nro_identificacion)],
            ['Edad', is_numeric($age) ? ((int) $age).' años' : self::text($age)],
            ['Sexo', self::text($case->patient_sex ?: $patient?->sex)],
            ['Teléfono', self::text($case->patient_phone ?: $patient?->phone)],
            ['Correo', self::text(TelemedicinePatientDeliveryEmail::for($patient))],
            ['Plan', self::text($patient?->plan?->description)],
            ['Afiliación', self::text($affiliation)],
            ['Caso', self::caseLabel($case)],
            ['Estatus del caso', self::text($case->status)],
            ['Fecha de apertura', $case->created_at?->format('d/m/Y h:i A') ?? '—'],
            ['Médico asignado', self::text($case->telemedicineDoctor?->full_name)],
        ];
    }

    /**
     * Huella del expediente: cambia si cambia un documento, el paciente o el diseño.
     *
     * @param  list<array<string, mixed>>  $documents
     */
    public static function fingerprint(TelemedicineCase $case, array $documents): string
    {
        return sha1((string) json_encode([
            'version' => self::LAYOUT_VERSION,
            'case' => (int) $case->getKey(),
            'patient' => self::patientDetails($case),
            'documents' => array_map(static fn (array $document): array => [
                $document['name'],
                $document['category'],
                $document['reference'],
                $document['date'],
                $document['path'],
                $document['size'],
                $document['modified'],
                $document['reason'],
            ], $documents),
        ]));
    }

    public static function storedPath(TelemedicineCase $case, string $fingerprint): string
    {
        return self::DIRECTORY.'/'.(int) $case->getKey().'/'.$fingerprint.'.pdf';
    }

    /**
     * Expediente ya generado con los documentos actuales, si existe.
     */
    public static function currentFingerprint(TelemedicineCase $case): ?string
    {
        $fingerprint = self::fingerprint($case, self::documents($case));

        return self::readSummary($case, $fingerprint) !== null ? $fingerprint : null;
    }

    /**
     * Resumen del expediente ya generado (páginas, incluidos y omitidos), si existe.
     *
     * @return array{pages: int, included: int, skipped: list<array{name: string, category: string, reason: string}>}|null
     */
    public static function readSummary(TelemedicineCase $case, string $fingerprint): ?array
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists(self::storedPath($case, $fingerprint)) || ! $disk->exists(self::summaryPath($case, $fingerprint))) {
            return null;
        }

        $summary = json_decode((string) $disk->get(self::summaryPath($case, $fingerprint)), true);

        if (! is_array($summary) || ! isset($summary['pages'], $summary['included']) || ! is_array($summary['skipped'] ?? null)) {
            return null;
        }

        return ['pages' => (int) $summary['pages'], 'included' => (int) $summary['included'], 'skipped' => array_values($summary['skipped'])];
    }

    public static function downloadName(TelemedicineCase $case): string
    {
        return 'expediente-'.Str::slug(self::caseLabel($case)).'.pdf';
    }

    /**
     * Genera el expediente (o reutiliza el vigente).
     *
     * @return array{fingerprint: string, path: string, pages: int, included: int, skipped: list<array{name: string, category: string, reason: string}>, reused: bool}
     */
    public static function generate(TelemedicineCase $case, ?string $generatedBy = null): array
    {
        $documents = self::documents($case);
        $fingerprint = self::fingerprint($case, $documents);
        $storedPath = self::storedPath($case, $fingerprint);
        $disk = Storage::disk(self::DISK);

        $reused = self::readSummary($case, $fingerprint);

        if ($reused !== null) {
            return [...$reused, 'fingerprint' => $fingerprint, 'path' => $storedPath, 'reused' => true];
        }

        $workDir = $disk->path(self::DIRECTORY.'/tmp/'.Str::uuid()->toString());
        File::ensureDirectoryExists($workDir);

        try {
            /** Se abre cada PDF en una instancia aparte antes de unirlo: un archivo que falla a mitad no deja páginas sueltas. */
            foreach ($documents as $index => $document) {
                if ($document['reason'] !== null || $document['path'] === null) {
                    continue;
                }

                $prepared = self::prepareSource($document['path'], (string) $document['kind'], $workDir, $index);

                $documents[$index]['merge_path'] = $prepared['path'];
                $documents[$index]['pages'] = $prepared['pages'];
                $documents[$index]['reason'] = $prepared['reason'];
            }

            $included = array_values(array_filter($documents, static fn (array $document): bool => $document['reason'] === null));
            $skipped = array_values(array_map(
                static fn (array $document): array => ['name' => $document['name'], 'category' => $document['category'], 'reason' => (string) $document['reason']],
                array_filter($documents, static fn (array $document): bool => $document['reason'] !== null),
            ));

            $generatedAt = now();
            $coverPath = $workDir.'/cover.pdf';
            self::renderPart('cover', [
                'case' => $case,
                'details' => self::patientDetails($case),
                'documentCount' => count($included),
                'generatedAt' => $generatedAt,
                'generatedBy' => $generatedBy,
            ], $coverPath);
            $coverPages = self::pageCount($coverPath);

            $indexPath = $workDir.'/index.pdf';
            $indexPages = 1;

            /** El índice lleva números de página que dependen de su propio largo: se ajusta hasta que cuadra. */
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $rows = [];
                $page = $coverPages + $indexPages + 1;

                foreach ($included as $document) {
                    $rows[] = [...$document, 'start_page' => $page];
                    $page += (int) $document['pages'];
                }

                self::renderPart('index', [
                    'case' => $case,
                    'patientName' => self::patientDetails($case)[0][1],
                    'rows' => $rows,
                    'skipped' => $skipped,
                ], $indexPath);

                $renderedPages = self::pageCount($indexPath);

                if ($renderedPages === $indexPages) {
                    break;
                }

                $indexPages = $renderedPages;
            }

            $pdf = new Fpdi('P', 'mm', 'A4');
            $pdf->SetAutoPageBreak(false);
            $pdf->SetTitle('Expediente '.self::caseLabel($case), true);
            $pdf->SetAuthor('Tu Dr. Group', true);
            $pdf->SetCreator('IntegraCorp', true);

            self::appendPdf($pdf, $coverPath);
            self::appendPdf($pdf, $indexPath);

            foreach ($included as $document) {
                if ($document['kind'] === 'image') {
                    self::appendImage($pdf, (string) $document['merge_path']);

                    continue;
                }

                self::appendPdf($pdf, (string) $document['merge_path']);
            }

            $outputPath = $workDir.'/expediente.pdf';
            $pdf->Output('F', $outputPath);
            $pages = $pdf->PageNo();
            unset($pdf);

            $finalPath = $disk->path($storedPath);
            File::ensureDirectoryExists(dirname($finalPath));

            if (! @rename($outputPath, $finalPath)) {
                throw new RuntimeException('No se pudo guardar el expediente en el servidor.');
            }

            $summary = ['pages' => $pages, 'included' => count($included), 'skipped' => $skipped];
            $disk->put(self::summaryPath($case, $fingerprint), (string) json_encode($summary, JSON_UNESCAPED_UNICODE));

            self::pruneOlderRecords($case, $fingerprint);

            return [...$summary, 'fingerprint' => $fingerprint, 'path' => $storedPath, 'reused' => false];
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string} ruta absoluta, tipo (pdf|image) y motivo si no se puede unir
     */
    private static function resolveSource(string $relative): array
    {
        if ($relative === '' || str_contains($relative, '..')) {
            return [null, null, 'El documento no tiene un archivo asociado.'];
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($relative)) {
            return [null, null, 'El archivo no está en el servidor.'];
        }

        $absolute = $disk->path($relative);
        $size = (int) @filesize($absolute);

        if (! is_readable($absolute) || $size <= 0) {
            return [null, null, 'El archivo está vacío o no se puede leer.'];
        }

        if ($size > self::MAX_DOCUMENT_BYTES) {
            return [$absolute, null, 'El archivo pesa más de '.(int) (self::MAX_DOCUMENT_BYTES / 1024 / 1024).' MB.'];
        }

        $head = (string) @file_get_contents($absolute, false, null, 0, 1024);

        if (str_contains($head, '%PDF-')) {
            return [$absolute, 'pdf', null];
        }

        if (@getimagesize($absolute) !== false) {
            return [$absolute, 'image', null];
        }

        $extension = mb_strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return [$absolute, null, 'Formato que no se puede unir a un PDF'.($extension !== '' ? ' (.'.$extension.')' : '').'.'];
    }

    /**
     * Deja el documento listo para unir: abre el PDF (normalizándolo si hace falta)
     * o convierte la imagen a JPEG.
     *
     * @return array{path: ?string, pages: int, reason: ?string}
     */
    private static function prepareSource(string $absolute, string $kind, string $workDir, int $index): array
    {
        if ($kind === 'image') {
            $jpeg = $workDir.'/image-'.$index.'.jpg';

            return self::imageToJpeg($absolute, $jpeg)
                ? ['path' => $jpeg, 'pages' => 1, 'reason' => null]
                : ['path' => null, 'pages' => 0, 'reason' => 'La imagen está dañada o en un formato no compatible.'];
        }

        try {
            return ['path' => $absolute, 'pages' => self::probePdf($absolute), 'reason' => null];
        } catch (Throwable $exception) {
            $normalized = $workDir.'/normalized-'.$index.'.pdf';

            if (self::normalizePdf($absolute, $normalized)) {
                try {
                    return ['path' => $normalized, 'pages' => self::probePdf($normalized), 'reason' => null];
                } catch (Throwable) {
                }
            }

            return ['path' => null, 'pages' => 0, 'reason' => self::pdfFailureReason($exception)];
        }
    }

    private static function probePdf(string $path): int
    {
        $probe = new Fpdi;
        $pages = $probe->setSourceFile($path);

        if ($pages < 1) {
            throw new RuntimeException('El PDF no tiene páginas.');
        }

        for ($page = 1; $page <= $pages; $page++) {
            $probe->importPage($page);
        }

        return $pages;
    }

    /**
     * Reescribe un PDF que FPDI no puede abrir (compresión moderna o protegido sin
     * clave de apertura) con qpdf o Ghostscript, si el servidor los tiene.
     */
    private static function normalizePdf(string $source, string $target): bool
    {
        $finder = new ExecutableFinder;
        $commands = [];

        if (($qpdf = $finder->find('qpdf')) !== null) {
            $commands[] = [$qpdf, '--decrypt', '--object-streams=disable', $source, $target];
        }

        if (($gs = $finder->find('gs')) !== null) {
            $commands[] = [$gs, '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4', '-sOutputFile='.$target, $source];
        }

        foreach ($commands as $command) {
            @unlink($target);

            try {
                $result = Process::timeout(self::NORMALIZER_TIMEOUT_SECONDS)->run($command);
            } catch (Throwable) {
                continue;
            }

            /** qpdf devuelve 3 cuando terminó con advertencias: el archivo igual sirve. */
            if (in_array($result->exitCode(), [0, 3], true) && is_file($target) && filesize($target) > 0) {
                return true;
            }
        }

        return false;
    }

    private static function pdfFailureReason(Throwable $exception): string
    {
        if ($exception instanceof CrossReferenceException && $exception->getCode() === CrossReferenceException::ENCRYPTED) {
            return 'El PDF está protegido y el servidor no pudo abrirlo.';
        }

        if ($exception instanceof CrossReferenceException && $exception->getCode() === CrossReferenceException::COMPRESSED_XREF) {
            return 'El PDF usa una compresión que el servidor no pudo leer.';
        }

        return 'El PDF está dañado o no se pudo leer.';
    }

    private static function imageToJpeg(string $source, string $target): bool
    {
        $contents = @file_get_contents($source);
        $image = $contents !== false ? @imagecreatefromstring($contents) : false;
        unset($contents);

        if ($image === false) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_IMAGE_SIDE_PX / max($width, $height, 1));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        /** Fondo blanco: las transparencias del PNG no se vuelven negras en el JPEG. */
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        $saved = imagejpeg($canvas, $target, 85);
        imagedestroy($canvas);

        return $saved && is_file($target);
    }

    private static function appendPdf(Fpdi $pdf, string $path): void
    {
        $pages = $pdf->setSourceFile($path);

        for ($page = 1; $page <= $pages; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);
        }
    }

    private static function appendImage(Fpdi $pdf, string $jpeg): void
    {
        [$width, $height] = getimagesize($jpeg) ?: [1, 1];
        $orientation = $width > $height ? 'L' : 'P';
        $pdf->AddPage($orientation, 'A4');

        $maxWidth = $pdf->GetPageWidth() - (2 * self::IMAGE_MARGIN_MM);
        $maxHeight = $pdf->GetPageHeight() - (2 * self::IMAGE_MARGIN_MM);
        $scale = min($maxWidth / $width, $maxHeight / $height);
        $drawWidth = $width * $scale;
        $drawHeight = $height * $scale;

        $pdf->Image(
            $jpeg,
            ($pdf->GetPageWidth() - $drawWidth) / 2,
            ($pdf->GetPageHeight() - $drawHeight) / 2,
            $drawWidth,
            $drawHeight,
            'JPG',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function renderPart(string $part, array $data, string $target): void
    {
        $html = view('documents.telemedicine-case-record', [
            ...$data,
            'part' => $part,
            'logo' => self::logoDataUri(),
        ])->render();

        file_put_contents($target, Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output());
    }

    private static function pageCount(string $path): int
    {
        return (new Fpdi)->setSourceFile($path);
    }

    private static function logoDataUri(): ?string
    {
        $path = public_path('image/logoNewTDG.png');

        return is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }

    /**
     * Borra los expedientes anteriores del caso: solo vale el vigente.
     */
    private static function pruneOlderRecords(TelemedicineCase $case, string $currentFingerprint): void
    {
        $disk = Storage::disk(self::DISK);

        foreach ($disk->files(self::DIRECTORY.'/'.(int) $case->getKey()) as $file) {
            if (! in_array(basename($file), [$currentFingerprint.'.pdf', $currentFingerprint.'.json'], true)) {
                $disk->delete($file);
            }
        }
    }

    private static function summaryPath(TelemedicineCase $case, string $fingerprint): string
    {
        return self::DIRECTORY.'/'.(int) $case->getKey().'/'.$fingerprint.'.json';
    }

    private static function caseLabel(TelemedicineCase $case): string
    {
        return filled($case->code) ? (string) $case->code : 'Caso #'.$case->getKey();
    }

    private static function text(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '—';
    }
}
