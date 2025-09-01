<?php

namespace App\Filament\Master\Resources\Agencies\Pages;

use App\Models\Agency;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Fieldset;
use App\Filament\Master\Resources\Agencies\AgencyResource;
use App\Filament\Master\Resources\IndividualQuotes\IndividualQuoteResource;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Perfil de Agencia';

    protected function getHeaderActions(): array
    {
        return [
            
            Action::make('preferences_menu')
                ->label('Preferencias de Menú')
                ->icon('heroicon-s-cog')
                ->color('verde')
                ->modalHeading('Preferencias')
                ->modalIcon('heroicon-s-cog')
                ->modalWidth(Width::ExtraLarge)
                ->form([
                    Fieldset::make('Ubicación de Menu')
                        ->schema([
                            Toggle::make('value')
                                ->label('Posicion de Menu? Top(Default)')
                                ->helperText('Al desactivar la opción del menu se posicionara en la parte izquierda de la pantalla.')
                                ->inline()
                                ->onIcon('heroicon-m-arrow-small-up')
                                ->offIcon('heroicon-m-arrow-small-left')
                                ->onColor('success')
                                ->offColor('danger')
                                ->default(fn() => Agency::where('code', Auth::user()->code_agency)->first()->conf_position_menu == true ? true : false),

                        ])->columns(1),
                ])
                ->action(function (array $data, Component $livewire) {

                    $user = Agency::where('code', Auth::user()->code_agency)->first();

                    if (isset($user)) {
                        $user->conf_position_menu = $data['value'];
                        $user->save();

                        return redirect('/master');
                    }
                }),
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(IndividualQuoteResource::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}