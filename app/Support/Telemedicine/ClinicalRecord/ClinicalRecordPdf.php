<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\ClinicalRecord;

use App\Models\TelemedicinePatient;
use App\Support\PdfOpaqueImage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PDF de la ficha clínica: mismo contenido que la pantalla, guardado en el disco privado.
 * El nombre lleva el usuario dueño: solo él puede descargarlo (ver la ruta de descarga).
 */
final class ClinicalRecordPdf
{
    public const DIRECTORY = 'clinical-records';

    public const KEEP_HOURS = 24;

    public static function render(TelemedicinePatient $patient, string $issuer, string $kind = ClinicalRecordAccess::KIND_CLINICAL): string
    {
        $technical = $kind === ClinicalRecordAccess::KIND_TECHNICAL;
        $html = view('documents.clinical-record', [
            'record' => PatientClinicalRecord::build($patient),
            'issuer' => $issuer,
            'technical' => $technical,
            'logo' => PdfOpaqueImage::fromPath(public_path('image/logoNewPdf.png')),
        ])->render();

        $dompdf = Pdf::loadHTML($html)->setPaper('letter', 'portrait')->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $footer = $technical ? 'Ficha técnica del paciente · USO INTERNO · Confidencial' : 'Ficha clínica del paciente · Confidencial';
        $canvas->page_script(function (int $page, int $pages, $cv) use ($font, $footer): void {
            $cv->text(40, $cv->get_height() - 30, $footer, $font, 7, [0.3, 0.34, 0.42]);
            $cv->text($cv->get_width() - 95, $cv->get_height() - 30, 'Página '.$page.' de '.$pages, $font, 7, [0.3, 0.34, 0.42]);
        });

        return (string) $dompdf->output();
    }

    /**
     * Guarda el PDF y devuelve el nombre del archivo (sin carpeta).
     */
    public static function store(TelemedicinePatient $patient, int $userId, string $issuer, string $kind = ClinicalRecordAccess::KIND_CLINICAL): string
    {
        self::prune();

        $kind = $kind === ClinicalRecordAccess::KIND_TECHNICAL ? ClinicalRecordAccess::KIND_TECHNICAL : ClinicalRecordAccess::KIND_CLINICAL;
        $file = $userId.'-'.$patient->getKey().'-'.$kind.'-'.Str::uuid()->toString().'.pdf';
        Storage::disk('local')->put(self::DIRECTORY.'/'.$file, self::render($patient, $issuer, $kind));

        return $file;
    }

    /**
     * @return array{user_id: int, patient_id: int, kind: string}|null
     */
    public static function owner(string $file): ?array
    {
        if (preg_match('/^(\d{1,12})-(\d{1,12})-(clinica|tecnica)-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$/', $file, $match) !== 1) {
            return null;
        }

        return ['user_id' => (int) $match[1], 'patient_id' => (int) $match[2], 'kind' => $match[3]];
    }

    /**
     * Borra los PDF de más de un día: son copias de un momento, la ficha vive en pantalla.
     */
    public static function prune(): void
    {
        $disk = Storage::disk('local');
        $limit = now()->subHours(self::KEEP_HOURS)->getTimestamp();

        foreach ($disk->files(self::DIRECTORY) as $path) {
            if ($disk->lastModified($path) < $limit) {
                $disk->delete($path);
            }
        }
    }
}
