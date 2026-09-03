<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Illuminate\Support\Facades\Storage;

final class TelemedicineInformeLargoPdfGenerator
{
    public const STORAGE_DIRECTORY = 'telemedicina-doc';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function generateAndSave(array $data, string $typeDocument = 'informe-largo'): string
    {
        $fileName = TelemedicineInformeLargoDataBuilder::pdfDocumentName($data, $typeDocument);
        $relativePath = self::STORAGE_DIRECTORY.'/'.$fileName;

        self::ensureStorageDirectoryExists();

        TelemedicineInformePdfRenderer::save(
            TelemedicineInformePdfRenderer::VIEW_LARGO,
            $data,
            $relativePath,
        );

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
