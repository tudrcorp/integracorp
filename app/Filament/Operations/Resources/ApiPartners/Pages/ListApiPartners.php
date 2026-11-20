<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners\Pages;

use App\Filament\Operations\Resources\ApiPartners\ApiPartnerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApiPartners extends ListRecords
{
    protected static string $resource = ApiPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo aliado'),
        ];
    }
}
