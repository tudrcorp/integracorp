<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Users\Pages;

use App\Filament\Business\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return 'Usuarios internos con acceso a los paneles INTEGRACORP.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo usuario')
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
