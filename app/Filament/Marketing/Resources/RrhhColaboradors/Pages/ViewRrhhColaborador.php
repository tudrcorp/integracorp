<?php

namespace App\Filament\Marketing\Resources\RrhhColaboradors\Pages;

use App\Filament\Marketing\Resources\RrhhColaboradors\RrhhColaboradorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRrhhColaborador extends ViewRecord
{
    protected static string $resource = RrhhColaboradorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // EditAction::make(),
        ];
    }
}
