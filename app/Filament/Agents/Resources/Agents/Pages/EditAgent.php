<?php

namespace App\Filament\Agents\Resources\Agents\Pages;

use App\Models\Agent;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Fieldset;
use Livewire\Component;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Agents\Resources\Agents\AgentResource;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    public function getTitle(): string | Htmlable
    {
        $name = $this->record->name;
        return 'Agente: ' . $name;
    }

    protected function getActions(): array
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
                                ->default(fn() => Agent::where('id', Auth::user()->agent_id)->first()->conf_position_menu == true ? true : false),

                        ])->columns(1),
                ])
                ->action(function (array $data, Component $livewire) {

                    $user = Agent::where('id', Auth::user()->agent_id)->first();

                    if (isset($user)) {
                        $user->conf_position_menu = $data['value'];
                        $user->save();

                        
                        return redirect('/agents');
                    }
                }),
        ];
    }

}