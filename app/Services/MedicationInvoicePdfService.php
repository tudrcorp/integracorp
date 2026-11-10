<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationMedicationInvoice;
use App\Support\Operations\MedicationInvoiceLifecycle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PDF de la factura al paciente (medicamentos, laboratorios, estudios o especialistas).
 *
 * Como Quality Control y la orden de servicio, se renderiza como mucho una vez por contenido:
 * el HTML es determinista (sin now()) y su huella nombra el archivo. Vista previa, descarga,
 * correo y WhatsApp usan la misma copia. Si cambia el estado (pagada, anulada), cambia el HTML
 * y se genera una versión nueva. Se guarda en el disco **privado**: trae datos del paciente.
 */
final class MedicationInvoicePdfService
{
    /** Se sube si cambian opciones de DomPDF que no se ven en el HTML. */
    public const RENDER_VERSION = '1';

    public const DISK = 'local';

    public const DIRECTORY = 'medication-invoices-pdf';

    private const LOGO_FILE = 'image/logoNewPdf-800.png';

    /**
     * @return array{path: string, contents: string, fingerprint: string, filename: string}
     */
    public static function document(OperationMedicationInvoice $invoice): array
    {
        $html = self::html($invoice);
        $disk = Storage::disk(self::DISK);
        $fingerprint = self::fingerprint($html);
        $prefix = 'factura-'.$invoice->getKey();
        $path = self::DIRECTORY.'/'.$prefix.'-v'.$fingerprint.'.pdf';
        $filename = self::filename($invoice);

        if ($disk->exists($path)) {
            $contents = (string) $disk->get($path);

            if (strlen($contents) > 100) {
                return ['path' => $path, 'contents' => $contents, 'fingerprint' => $fingerprint, 'filename' => $filename];
            }
        }

        $contents = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();

        /** Las versiones viejas de la misma factura ya no sirven: se reemplazan. */
        foreach ($disk->files(self::DIRECTORY) as $file) {
            if (str_starts_with(basename($file), $prefix.'-v') && $file !== $path) {
                $disk->delete($file);
            }
        }

        $disk->put($path, $contents);

        return ['path' => $path, 'contents' => $contents, 'fingerprint' => $fingerprint, 'filename' => $filename];
    }

    public static function html(OperationMedicationInvoice $invoice): string
    {
        $invoice->loadMissing('items');

        return view('documents.medication-invoice', [
            'invoice' => $invoice,
            'items' => $invoice->items,
            'statusLabel' => MedicationInvoiceLifecycle::statusLabel($invoice->status),
            'typeLabel' => OperationMedicationInvoice::typeLabel($invoice->invoice_type),
            'isMedications' => $invoice->isMedications(),
            'paymentMethodLabel' => MedicationInvoiceLifecycle::paymentMethodLabel($invoice->payment_method),
            'logoDataUri' => self::logoDataUri(),
        ])->render();
    }

    public static function filename(OperationMedicationInvoice $invoice): string
    {
        return 'factura-'.Str::lower((string) $invoice->invoice_number).'.pdf';
    }

    public static function fingerprint(string $html): string
    {
        return substr(sha1(self::RENDER_VERSION.'|'.$html), 0, 16);
    }

    /**
     * Copia publicada para WhatsApp (enlace firmado). Limpia las de más de 3 días.
     */
    public static function shareCopy(string $contents): string
    {
        $disk = Storage::disk(self::DISK);
        $limit = Carbon::now()->subDays(3)->getTimestamp();

        foreach ($disk->files(self::DIRECTORY.'/shared') as $file) {
            if ($disk->lastModified($file) < $limit) {
                $disk->delete($file);
            }
        }

        $share = (string) Str::uuid();
        $disk->put(self::DIRECTORY.'/shared/'.$share.'.pdf', $contents);

        return $share;
    }

    public static function sharedPath(string $share): ?string
    {
        if (! Str::isUuid($share)) {
            return null;
        }

        $path = self::DIRECTORY.'/shared/'.$share.'.pdf';

        return Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    private static function logoDataUri(): string
    {
        static $dataUri = null;

        if ($dataUri !== null) {
            return $dataUri;
        }

        $path = public_path(self::LOGO_FILE);
        $path = is_file($path) ? $path : public_path('image/logoNewPdf.png');
        $contents = is_file($path) ? file_get_contents($path) : false;

        return $dataUri = ($contents === false || $contents === '') ? '' : 'data:image/png;base64,'.base64_encode($contents);
    }
}
