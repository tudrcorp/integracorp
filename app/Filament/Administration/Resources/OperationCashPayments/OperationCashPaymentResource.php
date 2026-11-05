<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\OperationCashPayments;

use App\Filament\Administration\Resources\OperationCashPayments\Pages\ListOperationCashPayments;
use App\Filament\Operations\Resources\OperationCashPayments\OperationCashPaymentResource as OperationsCashPaymentResource;
use UnitEnum;

/**
 * Los mismos pagos de contado de Operaciones, visibles en Administración con su permiso.
 */
class OperationCashPaymentResource extends OperationsCashPaymentResource
{
    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?int $navigationSort = 41;

    public static function getPages(): array
    {
        return ['index' => ListOperationCashPayments::route('/')];
    }
}
