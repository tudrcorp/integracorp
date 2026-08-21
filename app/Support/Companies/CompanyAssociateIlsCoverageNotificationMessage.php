<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\CompanyAssociate;
use App\Support\RunReportMessageFormatter;
use Illuminate\Support\Facades\Storage;

final class CompanyAssociateIlsCoverageNotificationMessage
{
    /**
     * Extensiones que UltraMsg entrega mejor por el endpoint de imagen que por el
     * de documento: llegan con vista previa en lugar de como archivo adjunto.
     *
     * @var list<string>
     */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    public static function emailSubject(CompanyAssociate $associate): string
    {
        return 'Cobertura confirmada · '.$associate->full_name.' · Voucher '.self::value($associate->vaucher_ils).' · INTEGRACORP';
    }

    public static function whatsappBody(CompanyAssociate $associate): string
    {
        $lines = [
            '*COBERTURA CONFIRMADA · INTEGRACORP*',
            '',
            'El analista confirmó que se realizó toda la gestión y que el cliente *está cubierto en su totalidad*.',
            '',
            '*Voucher ILS*',
            '• Número: '.self::value($associate->vaucher_ils),
            '• Vigencia desde: '.self::value($associate->date_init),
            '• Vigencia hasta: '.self::value($associate->date_end),
            '',
            '*Asociado*',
            '• Nombre: '.self::value($associate->full_name),
            '• Cédula: '.self::value($associate->identity_card),
            '• Edad: '.($associate->age !== null ? $associate->age.' años' : '—'),
            '• Sexo: '.self::value($associate->sex),
            '• Fecha nacimiento: '.($associate->birth_date?->format('d/m/Y') ?? '—'),
            '• Correo: '.self::value($associate->email),
            '• Teléfono: '.self::value($associate->phone),
            '',
            '*Empresa*',
            '• Nombre: '.self::value($associate->company?->name),
            '• RIF: '.self::value($associate->company?->rif),
            '',
            '*Responsable*',
            '• Nombre: '.self::value($associate->responsible?->full_name),
            '• Cédula: '.self::value($associate->responsible?->identity_card),
            '',
            '• Confirmado el: '.now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
            '',
            'Se adjunta el documento del voucher.',
        ];

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }

    public static function whatsappVoucherCaption(CompanyAssociate $associate): string
    {
        return RunReportMessageFormatter::truncateForWhatsAppCaption(
            'Voucher ILS '.self::value($associate->vaucher_ils).' · '.$associate->full_name
            .' · Vigencia '.self::value($associate->date_init).' al '.self::value($associate->date_end),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function emailPayload(CompanyAssociate $associate): array
    {
        return [
            'associate' => $associate,
            'company' => $associate->company,
            'responsible' => $associate->responsible,
            'voucherCode' => self::value($associate->vaucher_ils),
            'voucherDateInit' => self::value($associate->date_init),
            'voucherDateEnd' => self::value($associate->date_end),
            'panelUrl' => CompanyAssociatesTableContext::associateViewUrl($associate),
            'generatedAt' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    public static function voucherStorageRelativePath(CompanyAssociate $associate): ?string
    {
        return filled($associate->document_ils)
            ? ltrim((string) $associate->document_ils, '/')
            : null;
    }

    /**
     * Ruta en disco del voucher, para adjuntarlo al correo. `null` si el archivo
     * ya no está en el disco público.
     */
    public static function voucherAbsolutePath(CompanyAssociate $associate): ?string
    {
        $relativePath = self::voucherStorageRelativePath($associate);

        if ($relativePath === null) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            return null;
        }

        $absolutePath = $disk->path($relativePath);

        return is_file($absolutePath) ? $absolutePath : null;
    }

    /**
     * URL pública del voucher. UltraMsg descarga el archivo por HTTP, así que
     * necesita la URL absoluta del storage, no la ruta local.
     */
    public static function voucherPublicUrl(CompanyAssociate $associate): ?string
    {
        $relativePath = self::voucherStorageRelativePath($associate);

        if ($relativePath === null) {
            return null;
        }

        return CompanyAssociateDocumentsDeliveryMessage::whatsappStorageDocumentUrl($relativePath);
    }

    public static function voucherFilename(CompanyAssociate $associate): ?string
    {
        $relativePath = self::voucherStorageRelativePath($associate);

        return $relativePath === null ? null : basename($relativePath);
    }

    public static function voucherIsImage(CompanyAssociate $associate): bool
    {
        $relativePath = self::voucherStorageRelativePath($associate);

        if ($relativePath === null) {
            return false;
        }

        return in_array(
            strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION)),
            self::IMAGE_EXTENSIONS,
            true,
        );
    }

    public static function emailLogoPath(): string
    {
        $primaryLogo = public_path('image/logoNewPdf.png');

        if (file_exists($primaryLogo)) {
            return $primaryLogo;
        }

        return public_path('image/logoNewTDG.png');
    }

    private static function value(mixed $value): string
    {
        return filled($value) ? (string) $value : '—';
    }
}
