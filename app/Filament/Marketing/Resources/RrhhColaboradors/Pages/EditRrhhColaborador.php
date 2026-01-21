<?php

namespace App\Filament\Marketing\Resources\RrhhColaboradors\Pages;

use App\Filament\Marketing\Resources\RrhhColaboradors\RrhhColaboradorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhColaborador extends EditRecord
{
    protected static string $resource = RrhhColaboradorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
