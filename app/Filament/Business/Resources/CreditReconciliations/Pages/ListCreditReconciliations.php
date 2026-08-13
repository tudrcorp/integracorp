<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CreditReconciliations\Pages;

use App\Filament\Business\Resources\CreditReconciliations\CreditReconciliationResource;
use Filament\Resources\Pages\ListRecords;

class ListCreditReconciliations extends ListRecords
{
    protected static string $resource = CreditReconciliationResource::class;

    protected static ?string $title = 'Conciliación de crédito';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
