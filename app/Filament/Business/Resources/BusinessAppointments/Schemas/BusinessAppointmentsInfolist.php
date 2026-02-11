<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Schemas;

use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BusinessAppointmentsInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make()
                    ->heading('Informacion General')
                    ->icon('heroicon-o-calendar')
                    ->description('Detallado de la cita')
                    ->schema([
                        Fieldset::make('Informacion General')
                            ->schema([
                                TextEntry::make('legal_name')
                                    ->label('Nombre Legal'),
                                TextEntry::make('phone')
                                    ->label('Telefono'),
                                TextEntry::make('email')
                                    ->label('Correo Electronico'),
                                TextEntry::make('country.name')
                                    ->label('Pais'),
                                TextEntry::make('state.definition')
                                    ->label('Estado'),
                                TextEntry::make('city.definition')
                                    ->label('Ciudad'),
                                TextEntry::make('status')
                                    ->label('Estado'),
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->placeholder('-')
                                    ->label('Fecha de Creacion'),
                                TextEntry::make('updated_at')
                                    ->dateTime()
                                    ->placeholder('-')
                                    ->label('Fecha de Actualizacion'),
                            ])
                            ->columns(4),
                    ])->columnSpanFull(),

                Section::make()
                    ->heading('Observaciones')
                    ->icon('heroicon-o-pencil-square')
                    ->description('Detallado de observaciones de gestion')
                    ->schema([
                        Fieldset::make('Observaciones')
                            ->schema([
                                RepeatableEntry::make('businessAppointmentObservations')
                                    ->table([
                                        TableColumn::make('Nota/Observacion'),
                                        TableColumn::make('Creado por'),
                                        TableColumn::make('Fecha de creacion'),
                                    ])
                                    ->schema([
                                        TextEntry::make('observation'),
                                        TextEntry::make('created_by'),
                                        TextEntry::make('created_at')
                                            ->dateTime()
                                            ->placeholder('-'),
                                    ])->columnSpanFull(),
                            ])->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }
}
