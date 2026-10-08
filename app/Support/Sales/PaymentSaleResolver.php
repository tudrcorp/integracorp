<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Venta que corresponde a un pago de afiliación (individual o corporativa).
 *
 * El enlace es el número de recibo (`invoice_number`) **más** el código de la
 * afiliación: el recibo solo no es único entre afiliaciones distintas. Si dentro
 * de la misma afiliación hay más de una venta con ese recibo, no se adivina.
 *
 * Una instancia carga una sola vez las ventas de la afiliación, para que la
 * tabla de pagos no consulte una vez por fila.
 */
final class PaymentSaleResolver
{
    public const OK = 'ok';

    public const NO_RECEIPT = 'no_receipt';

    public const NOT_FOUND = 'not_found';

    public const AMBIGUOUS = 'ambiguous';

    /** @var Collection<int, Sale>|null */
    private ?Collection $sales = null;

    public function __construct(private readonly ?string $affiliationCode) {}

    /**
     * @return array{status: string, sale: Sale|null}
     */
    public function resolve(Model $payment): array
    {
        $receipt = trim((string) $payment->getAttribute('invoice_number'));

        if ($receipt === '' || blank($this->affiliationCode)) {
            return ['status' => self::NO_RECEIPT, 'sale' => null];
        }

        $matches = $this->sales()->filter(fn (Sale $sale): bool => trim((string) $sale->invoice_number) === $receipt)->values();

        return match ($matches->count()) {
            0 => ['status' => self::NOT_FOUND, 'sale' => null],
            1 => ['status' => self::OK, 'sale' => $matches->first()],
            default => ['status' => self::AMBIGUOUS, 'sale' => null],
        };
    }

    public static function reasonLabel(string $status): ?string
    {
        return match ($status) {
            self::NO_RECEIPT => 'Este pago no tiene número de recibo: no se puede enlazar con su venta.',
            self::NOT_FOUND => 'No se encontró la venta de este pago en Administración → Ventas.',
            self::AMBIGUOUS => 'Hay más de una venta con este recibo en la afiliación. Revise Ventas antes de facturar.',
            default => null,
        };
    }

    /**
     * @return Collection<int, Sale>
     */
    private function sales(): Collection
    {
        return $this->sales ??= Sale::query()
            ->where('affiliation_code', (string) $this->affiliationCode)
            ->get();
    }
}
