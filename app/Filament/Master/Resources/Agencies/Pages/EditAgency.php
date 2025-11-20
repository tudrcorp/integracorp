<?php

namespace App\Filament\Master\Resources\Agencies\Pages;

use App\Models\Agency;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Fieldset;
use App\Filament\Master\Resources\Agencies\AgencyResource;
use App\Filament\Master\Resources\IndividualQuotes\IndividualQuoteResource;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Perfil de la Agencia';

    protected function getHeaderActions(): array
    {
        return [

            Action::make('preferences_grafic')
                ->label('Preferencias de Gráficos')
                ->icon('fontisto-bar-chart')
                ->color('verde')
                ->modalHeading('Preferencias de Gráficos')
                ->modalIcon('fontisto-bar-chart')
                ->modalWidth(Width::ExtraLarge)
                ->form([
                    Fieldset::make('Tipo de Gráficos')
                        ->schema([
                            Select::make('value')
                                ->label('Selecciona el tipo de Gráfico')
                                ->helperText('Por default se muestran los gráficos tipo barras.')
                                ->options([
                                    'bar'   => 'Barras',
                                    'line'  => 'Lineas',
                                ])
                                ->default(fn() => Agency::where('code', Auth::user()->code_agency)->first()->type_chart),

                        ])->columns(1),
                ])
                ->action(function (array $data, Component $livewire) {

                    $user = Agency::where('code', Auth::user()->code_agency)->first();

                    if (isset($user)) {
                        $user->type_chart = $data['value'];
                        $user->save();

                        return redirect()->route('filament.master.pages.dashboard');
                    }
                }),
            
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
                
            Action::make('back')
                ->label('Dashboard')
                ->icon(Heroicon::Home)
                ->color('success')
                ->url(route('filament.master.pages.dashboard')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->icon('heroicon-s-check-circle')
            ->title('PERFIL ACTUALIZADO!')
            ->body('La informacion de tu perfel ha sido actualizada de forma exitosa.');
    }

}