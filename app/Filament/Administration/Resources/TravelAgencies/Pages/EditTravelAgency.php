<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\TravelAgencies\Pages;

use App\Filament\Administration\Resources\TravelAgencies\TravelAgencyResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditTravelAgency extends EditRecord
{
    protected static string $resource = TravelAgencyResource::class;

    protected static ?string $title = 'Editar Agencia de Viajes';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()?->name;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver')
                ->icon(Heroicon::OutlinedEye),
        ];
    }
}
