<?php

declare(strict_types=1);

namespace App\Support\Farmadoc;

use App\Models\FarmadocOrder;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Nota de recibido y aprobado: el comprobante que IntegraCorp entrega a Farmadoc cuando
 * el pedido entra al inventario. Sin montos. La ven los analistas y Farmadoc (API de aliados).
 */
final class FarmadocReceiptNote
{
    public static function available(FarmadocOrder $order): bool
    {
        return $order->isReceived() && filled($order->receipt_number) && is_array($order->receipt_items);
    }

    public static function filename(FarmadocOrder $order): string
    {
        return 'nota-recibido-'.$order->receipt_number.'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(FarmadocOrder $order): array
    {
        $order->loadMissing('receiptUbication:id,name');

        $lines = array_values(array_filter(
            is_array($order->receipt_items) ? $order->receipt_items : [],
            'is_array',
        ));

        $ordered = array_sum(array_map(fn (array $line): int => (int) ($line['quantity_ordered'] ?? 0), $lines));
        $received = array_sum(array_map(fn (array $line): int => (int) ($line['quantity_received'] ?? 0), $lines));

        return [
            'order' => $order,
            'lines' => $lines,
            'ordered' => $ordered,
            'received' => $received,
            'complete' => $received >= $ordered,
            'ubication' => $order->receiptUbication?->name ?? '—',
            'logo' => self::logo(),
        ];
    }

    public static function render(FarmadocOrder $order): string
    {
        return Pdf::loadView('documents.farmadoc.receipt-note', self::data($order))
            ->setPaper('letter')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    private static function logo(): ?string
    {
        $path = public_path('image/logoNewPdf.png');

        return is_readable($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
