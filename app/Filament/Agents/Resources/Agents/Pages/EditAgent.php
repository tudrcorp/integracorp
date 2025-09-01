<?php

namespace App\Filament\Agents\Resources\Agents\Pages;

use App\Models\Agent;
use App\Models\Agency;
use Livewire\Component;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Agents\Resources\Agents\AgentResource;
use App\Filament\Agents\Resources\IndividualQuotes\IndividualQuoteResource;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    public function getTitle(): string | Htmlable
    {
        $name = $this->record->name;
        return 'Agente: ' . $name;
    }

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
                                ->default(fn() => Agent::where('id', Auth::user()->agent_id)->first()->type_chart),

                        ])->columns(1),
                ])
                ->action(function (array $data, Component $livewire) {

                    $user = Agent::where('id', Auth::user()->agent_id)->first();

                    if (isset($user)) {
                        $user->type_chart = $data['value'];
                        $user->save();

                        return redirect()->route('filament.agents.pages.dashboard');
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
                                ->default(fn() => Agent::where('id', Auth::user()->agent_id)->first()->conf_position_menu == true ? true : false),

                        ])->columns(1),
                ])
                ->action(function (array $data, Component $livewire) {

                    $user = Agent::where('id', Auth::user()->agent_id)->first();

                    if (isset($user)) {
                        $user->conf_position_menu = $data['value'];
                        $user->save();

                        return redirect()->route('filament.agents.pages.dashboard');
                    }
                }),
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                //redirect to dashboard general
                ->url(route('filament.agents.pages.dashboard')),
                
        ];
    }

}