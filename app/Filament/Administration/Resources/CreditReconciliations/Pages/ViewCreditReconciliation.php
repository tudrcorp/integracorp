<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\CreditReconciliations\Pages;

use App\Filament\Administration\Resources\CreditReconciliations\CreditReconciliationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCreditReconciliation extends ViewRecord
{
    protected static string $resource = CreditReconciliationResource::class;

    protected static ?string $title = 'Movimiento de crédito';
}
