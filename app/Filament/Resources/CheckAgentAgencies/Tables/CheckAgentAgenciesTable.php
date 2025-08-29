<?php

namespace App\Filament\Resources\CheckAgentAgencies\Tables;

use App\Models\Agency;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Controllers\MigrationHistoricalController;
use Filament\Forms\Components\Select;

class CheckAgentAgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codificacion_agente')
                    ->searchable(),
                TextColumn::make('codigo_agente')
                    ->searchable(),
                TextColumn::make('tipo_agente')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('status_migration')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'POR MIGRAR' => 'warning',
                            'MIGRADO' => 'success',
                        };
                    })
                    ->searchable(),
                TextColumn::make('nombre_agencia_agente')
                    ->searchable(),
                TextColumn::make('nombre_representante')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->searchable(),
                TextColumn::make('fecha_nacimiento')
                    ->searchable(),
                TextColumn::make('fecha_ingreso')
                    ->searchable(),
                TextColumn::make('estatus')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('telefono')
                    ->searchable(),
                TextColumn::make('usuario_instagram')
                    ->searchable(),
                TextColumn::make('pais')
                    ->searchable(),
                TextColumn::make('estado')
                    ->searchable(),
                TextColumn::make('ciudad')
                    ->searchable(),
                TextColumn::make('tdec')
                    ->searchable(),
                TextColumn::make('tdev')
                    ->searchable(),
                
                TextColumn::make('agente_supervisor')
                    ->searchable(),
                TextColumn::make('agencia_master')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('migrate_agency')
                        ->label('Migrar Agencia(s)')
                        ->icon('heroicon-s-arrow-right-on-rectangle')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            MigrationHistoricalController::migrate_agency($records);
                        }),
                    BulkAction::make('migrate_agent')
                        ->label('Migrar Agente(s)')
                        ->icon('heroicon-s-arrow-right-on-rectangle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Select::make('agency_id')
                                ->label('Agencia')
                                ->options(function () {
                                    return Agency::all()->pluck('name_corporative', 'id');
                                })
                                ->required()
                        ])
                        ->action(function (Collection $records, array $data) {
                            MigrationHistoricalController::migrate_agent($records, $data['agency_id']);
                        }),
                ]),
            ]);
    }
}