<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\CompanyAssociates\Pages;

use App\Filament\Operations\Resources\CompanyAssociates\NuevosNegociosAssociateResource;
use Filament\Resources\Pages\ListRecords;

class ListCompanyAssociates extends ListRecords
{
    protected static string $resource = NuevosNegociosAssociateResource::class;

    protected static ?string $title = 'Asociados de Nuevos Negocios';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
