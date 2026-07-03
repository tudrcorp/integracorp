<?php

declare(strict_types=1);

namespace App\Support\Companies;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class CompanyAssociateInclusionQrGenerator
{
    /**
     * @return array{
     *     pdf_path: string,
     *     pdf_url: string,
     *     qr_path: string,
     *     qr_url: string,
     *     logo_path: string
     * }
     */
    public static function generate(string $pdfSourcePath): array
    {
        if (! is_file($pdfSourcePath)) {
            throw new RuntimeException('No se encontró el PDF de canales de comunicación en la ruta indicada.');
        }

        self::ensureDirectoriesExist();

        $pdfDestination = Storage::disk('public')->path(CompanyAssociateInclusionQrCatalog::pdfStoragePath());
        File::copy($pdfSourcePath, $pdfDestination);

        $logoPath = self::resolveLogoPath();
        $qrPath = Storage::disk('public')->path(CompanyAssociateInclusionQrCatalog::qrStoragePath());

        $qrBinary = QrCode::format('png')
            ->size(700)
            ->errorCorrection('H')
            ->margin(1)
            ->merge($logoPath, CompanyAssociateInclusionQrCatalog::LOGO_CENTER_SCALE, true)
            ->generate(CompanyAssociateInclusionQrCatalog::pdfPublicUrl());

        File::put($qrPath, $qrBinary);

        return [
            'pdf_path' => $pdfDestination,
            'pdf_url' => CompanyAssociateInclusionQrCatalog::pdfPublicUrl(),
            'qr_path' => $qrPath,
            'qr_url' => CompanyAssociateInclusionQrCatalog::qrPublicUrl(),
            'logo_path' => $logoPath,
        ];
    }

    public static function storeQrFromUpload(string $absolutePngPath): void
    {
        if (! is_file($absolutePngPath)) {
            throw new RuntimeException('No se encontró la imagen PNG del QR para almacenar.');
        }

        self::ensureDirectoriesExist();

        File::copy(
            $absolutePngPath,
            Storage::disk('public')->path(CompanyAssociateInclusionQrCatalog::qrStoragePath()),
        );
    }

    public static function qrAbsolutePath(): ?string
    {
        $path = Storage::disk('public')->path(CompanyAssociateInclusionQrCatalog::qrStoragePath());

        return is_file($path) ? $path : null;
    }

    public static function ensurePublished(): void
    {
        if (CompanyAssociateInclusionQrCatalog::qrExists()) {
            return;
        }

        $pdfPath = Storage::disk('public')->path(CompanyAssociateInclusionQrCatalog::pdfStoragePath());

        if (! is_file($pdfPath)) {
            return;
        }

        self::generate($pdfPath);
    }

    private static function ensureDirectoriesExist(): void
    {
        Storage::disk('public')->makeDirectory(CompanyAssociateInclusionQrCatalog::PDF_STORAGE_DIRECTORY);
        Storage::disk('public')->makeDirectory(CompanyAssociateInclusionQrCatalog::QR_STORAGE_DIRECTORY);
    }

    private static function resolveLogoPath(): string
    {
        $logoPath = CompanyAssociateInclusionQrCatalog::logoSourceAbsolutePath();

        if (! is_file($logoPath)) {
            throw new RuntimeException('No se encontró el logo corporativo para el centro del QR.');
        }

        return $logoPath;
    }
}
