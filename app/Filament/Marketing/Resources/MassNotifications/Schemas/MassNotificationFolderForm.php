<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Validation\Rule;

class MassNotificationFolderForm
{
    /**
     * Esquema del modal para crear una carpeta de notificaciones masivas.
     *
     * @return array<int, Section>
     */
    public static function createComponents(): array
    {
        return [
            Section::make('Datos de la carpeta')
                ->description('El nombre debe ser único. Las notificaciones nuevas se siguen guardando por defecto en «Sin organizar» hasta que las muevas.')
                ->icon('heroicon-o-folder')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre de la carpeta')
                        ->placeholder('Ej.: Campañas verano, Recordatorios cobranza…')
                        ->required()
                        ->maxLength(255)
                        ->autocomplete(false)
                        ->rule(Rule::unique('mass_notification_folders', 'name'))
                        ->validationMessages([
                            'required' => 'Indica un nombre para la carpeta.',
                            'unique' => 'Ya existe una carpeta con ese nombre.',
                        ]),
                ])
                ->columns(1),
        ];
    }
}
