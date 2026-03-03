<?php

namespace App\Filament\Business\Resources\DressTylorQuotes\Pages;

use App\Filament\Business\Resources\DressTylorQuotes\DressTylorQuoteResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateDressTylorQuote extends CreateRecord
{
    protected static string $resource = DressTylorQuoteResource::class;

    protected static ?string $title = 'Creación de Cotizaciones Dress Tylor';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl()),
        ];
    }

    // Deshabilitar el boton "Crear Otro"
    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $benefitsRaw = $data['benefits_repeater'] ?? [];
        dd($benefitsRaw);

        return $data;
    }

    protected function beforeCreate(): void
    {
        dd(session()->get('ageCovegares'));
        // Runs before the form fields are saved to the database.
    }
}
