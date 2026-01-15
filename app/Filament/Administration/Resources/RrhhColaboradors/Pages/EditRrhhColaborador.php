<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Pages;

use App\Filament\Administration\Resources\RrhhColaboradors\RrhhColaboradorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhColaborador extends EditRecord
{
    protected static string $resource = RrhhColaboradorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
