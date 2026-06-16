<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class TelemedicineInformeLargoPdfGenerator
{
    public const STORAGE_DIRECTORY = 'telemedicina-doc';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function generateAndSave(array $data, string $typeDocument = 'informe-largo'): string
    {
        ini_set('memory_limit', '2048M');

        $fileName = TelemedicineInformeLargoDataBuilder::pdfDocumentName($data, $typeDocument);
        $relativePath = self::STORAGE_DIRECTORY.'/'.$fileName;

        self::ensureStorageDirectoryExists();

        $pdf = Pdf::loadView('documents.informe-medico-largo', ['data' => $data]);
        $pdf->save(public_path('storage/'.$relativePath));

        return $fileName;
    }

    public static function fileExists(string $fileName): bool
    {
        return Storage::disk('public')->exists(self::STORAGE_DIRECTORY.'/'.$fileName);
    }

    public static function ensureStorageDirectoryExists(): void
    {
        $directory = public_path('storage/'.self::STORAGE_DIRECTORY);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
