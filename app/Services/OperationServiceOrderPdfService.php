<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationDocumentSeal;
use App\Support\Operations\ServiceOrderFlowTrace;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Facades\Storage;

class OperationServiceOrderPdfService
{
    /**
     * Se sube al cambiar algo del render que no se refleja en el HTML (opciones de DomPDF),
     * para que las copias guardadas con la configuración anterior se regeneren.
     */
    public const RENDER_VERSION = '2';

    /**
     * Versión reducida del logo: el original (1678 px con transparencia) duplicaba el tiempo de render.
     */
    private const LOGO_FILE = 'image/logoNewPdf-800.png';

    private const DIRECTORY = 'operation-service-orders/generated-pdf';

    public static function make(OperationServiceOrder $order): PdfDocument
    {
        return self::fromHtml(self::html($order));
    }

    /**
     * HTML del documento. Es determinista: la misma orden produce el mismo HTML, y por eso
     * su huella dice si la copia guardada sigue al día sin enumerar de qué datos depende.
     */
    public static function html(OperationServiceOrder $order): string
    {
        $order->loadMissing([
            'operationCoordinationService.state',
            'operationCoordinationService.city',
            'operationCoordinationService.telemedicinePatient',
            'operationCoordinationService.telemedicineCase:id,code',
            'operationCoordinationService.telemedicineConsultationPatient:id,code_reference',
            'supplier.state',
            'supplier.city',
            'doctorNurse',
            'approvedOperationQuote',
            'medicalAppointment',
            'telemedicinePriority',
            'operationInventoryUbication',
            'operationServiceOrderItems',
        ]);

        return view('documents.operation-service-order-pdf', [
            'order' => $order,
            'logoDataUri' => self::logoDataUri(),
            'sealDataUri' => OperationDocumentSeal::dataUri(),
            'closingNotice' => ServiceOrderFlowTrace::closingNoticeForOrder($order),
        ])->render();
    }

    public static function fingerprint(string $html): string
    {
        return substr(sha1(self::RENDER_VERSION.'|'.$html), 0, 12);
    }

    public static function filename(OperationServiceOrder $order): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $order->order_number) ?: 'orden';

        return 'orden-servicio-'.$safe.'.pdf';
    }

    /**
     * Garantiza un PDF persistido en disco público y actualiza service_order_pdf_path.
     */
    public static function ensurePersisted(OperationServiceOrder $order, bool $force = false): string
    {
        return self::document($order, $force)['path'];
    }

    /**
     * PDF vigente de la orden con un solo render como máximo: si la copia guardada coincide con
     * la huella del contenido actual se entrega tal cual; si no, se genera, se guarda y se entrega
     * ese mismo resultado.
     *
     * @return array{path: string, contents: string, fingerprint: string}
     */
    public static function document(OperationServiceOrder $order, bool $force = false): array
    {
        $html = self::html($order);
        $fingerprint = self::fingerprint($html);
        $disk = Storage::disk('public');
        $current = trim((string) ($order->service_order_pdf_path ?? ''));

        if (! $force && self::isCurrent($current, $fingerprint) && $disk->exists($current)) {
            $contents = (string) $disk->get($current);

            if (strlen($contents) > 100) {
                return ['path' => $current, 'contents' => $contents, 'fingerprint' => $fingerprint];
            }
        }

        $contents = self::fromHtml($html)->output();
        $safeOrder = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $order->order_number) ?: (string) $order->id;
        $path = self::DIRECTORY.'/service-order-'.$safeOrder.'-'.now()->format('YmdHis').'-v'.$fingerprint.'.pdf';

        $disk->put($path, $contents);

        // Sin eventos ni updated_at: guardar la ruta de una copia no es un cambio de la orden
        // y no debe dejar traza de flujo ni estadística.
        $order->service_order_pdf_path = $path;
        $order->timestamps = false;

        try {
            $order->saveQuietly();
        } finally {
            $order->timestamps = true;
        }

        return ['path' => $path, 'contents' => $contents, 'fingerprint' => $fingerprint];
    }

    public static function isCurrent(string $path, string $fingerprint): bool
    {
        return $path !== '' && str_ends_with($path, '-v'.$fingerprint.'.pdf');
    }

    private static function fromHtml(string $html): PdfDocument
    {
        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            // Incrusta solo los glifos usados de DejaVu Sans: ~975 KB → ~140 KB por documento.
            ->setOption('isFontSubsettingEnabled', true);
    }

    private static function logoDataUri(): string
    {
        static $dataUri = null;

        if ($dataUri !== null) {
            return $dataUri;
        }

        $path = public_path(self::LOGO_FILE);

        if (! is_file($path)) {
            $path = public_path('image/logoNewPdf.png');
        }

        $contents = is_file($path) ? file_get_contents($path) : false;

        return $dataUri = ($contents === false || $contents === '')
            ? ''
            : 'data:image/png;base64,'.base64_encode($contents);
    }
}
