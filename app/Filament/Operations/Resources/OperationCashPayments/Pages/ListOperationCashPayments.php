<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCashPayments\Pages;

use App\Filament\Operations\Resources\OperationCashPayments\OperationCashPaymentResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListOperationCashPayments extends ListRecords
{
    protected static string $resource = OperationCashPaymentResource::class;

    protected static ?string $title = 'Pagos de contado';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pagos hechos al proveedor al cargar la factura de una orden de servicio. Cada pago dejó su cuenta por pagar «Pagada»; si algo salió mal, reverse el pago con su razón: queda registrado y la cuenta vuelve a como estaba.';
    }
}
