<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\OperationCashPayments\Pages;

use App\Filament\Administration\Resources\OperationCashPayments\OperationCashPaymentResource;
use App\Filament\Operations\Resources\OperationCashPayments\Pages\ListOperationCashPayments as OperationsListOperationCashPayments;

class ListOperationCashPayments extends OperationsListOperationCashPayments
{
    protected static string $resource = OperationCashPaymentResource::class;
}
