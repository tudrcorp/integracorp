<?php

namespace App\Filament\Business\Resources\ProspectAgents\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProspectAgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make()
                    ->heading('Informacion General')
                    ->icon('heroicon-o-shield-check')
                    ->description('Detallado de gestion del prospecto para Agente de TuDrGroup!')
                    ->schema([
                        Fieldset::make('Informacion General')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Nombre y Apellido:'),
                            TextEntry::make('type')
                                ->label('Tipo:'),
                            TextEntry::make('phone_1')
                                ->label('Telefono Principal:'),
                            TextEntry::make('phone_2')
                                ->label('Telefono Alternativo:'),
                            TextEntry::make('email')
                                ->label('Correo Electronico:'),
                            TextEntry::make('country.name')
                                ->label('Pais:'),
                            TextEntry::make('state.definition')
                                ->label('Estado:'),
                            TextEntry::make('city.definition')
                                ->label('Ciudad:'),
                            TextEntry::make('status')
                                ->label('Estado:'),
                            TextEntry::make('created_by')
                                ->label('Creado por:'),
                            TextEntry::make('updated_by')
                                ->label('Actualizado por:'),
                            TextEntry::make('reference_by')
                                ->label('Referido por:'),
                            TextEntry::make('created_at')
                                ->dateTime()
                                ->placeholder('-'),
                            TextEntry::make('updated_at')
                                ->dateTime()
                                ->placeholder('-'),
                        ])
                        ->columns(4),
                    ])->columnSpanFull(),

                Section::make()
                    ->heading('Tareas')
                    ->icon('heroicon-o-pencil-square')
                    ->description('Detallado de tareas de gestion')
                    ->schema([
                        Fieldset::make('Tareas')
                            ->schema([
                                RepeatableEntry::make('prospect_agent_tasks')
                                    ->table([
                                        TableColumn::make('ID')->width('5%'),
                                        TableColumn::make('Tarea')->width('20%'),
                                        TableColumn::make('Creado por')->width('10%'),
                                        TableColumn::make('Responsable')->width('10%'),
                                        TableColumn::make('Estatus')->width('10%'),
                                        TableColumn::make('Resuelto por')->width('15%'),
                                        TableColumn::make('Fecha de creacion')->width('10%'),
                                        TableColumn::make('Duracion')->width('10%'),
                                        TableColumn::make('Fecha de actualizacion')->width('10%'),
                                    ])
                                    ->schema([
                                        TextEntry::make('id')->prefix('#00'),
                                        TextEntry::make('task'),
                                        TextEntry::make('created_by'),
                                        TextEntry::make('rrhh_colaborador.fullName'),
                                        TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'PENDIENTE' => 'warning',
                                                'RESUELTA' => 'success',
                                            })
                                            ->icon(fn (string $state): string => match ($state) {
                                                'PENDIENTE' => 'heroicon-o-clock',
                                                'RESUELTA' => 'heroicon-o-check-circle',
                                            }),
                                        TextEntry::make('resolved_by')
                                            ->badge()
                                            ->color('success')
                                            ->icon('heroicon-o-check-circle'),
                                        TextEntry::make('created_at')
                                            ->dateTime()
                                            ->placeholder('-'),
                                        TextEntry::make('duration')
                                            ->label('Duracion')
                                            ->default(fn($record) => $record->created_at->diffForHumans()),
                                        TextEntry::make('updated_at')
                                            ->dateTime()
                                            ->placeholder('-'),
                                    ])->columnSpanFull(),
                                    
                            ])->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make()
                    ->heading('Observaciones')
                    ->icon('heroicon-o-pencil-square')
                    ->description('Detallado de observaciones de gestion')
                    ->schema([
                        Fieldset::make('Observaciones')
                            ->schema([
                                RepeatableEntry::make('prospect_agent_observations')
                                    ->table([
                                        TableColumn::make('ID Tarea')->width('5%'),
                                        TableColumn::make('Nota'),
                                        TableColumn::make('Creado por'),
                                        TableColumn::make('Fecha de creacion'),
                                    ])
                                    ->schema([
                                        TextEntry::make('prospect_agent_task_id')->prefix('#00'),
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
