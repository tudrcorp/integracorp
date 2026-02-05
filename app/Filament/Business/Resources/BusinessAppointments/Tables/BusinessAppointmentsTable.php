<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BusinessAppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Citas')
            ->description('Listado de citas agendadas desde el portal de TuDrGroup. Este modulo tambien permite gestionar las citas.')
            ->columns([
                TextColumn::make('legal_name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electronico')
                    ->searchable(),
                TextColumn::make('country.name')
                    ->label('Pais')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable(),
                SelectColumn::make('status')
                    ->options([
                        'PENDIENTE' => 'PENDIENTE',
                        'ATENDIDA' => 'ATENDIDA',
                        'CANCELADA' => 'CANCELADA',
                        'REAGENDADA' => 'REAGENDADA',
                    ])
                    ->searchableOptions()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->update([
                            'status' => $state,
                            'updated_by' => Auth::user()->name,
                        ]);
                        Log::info('NEGOCIOS: Cita actualizada', [
                            'record' => $record->id,
                            'state' => $state,
                            'user' => Auth::user()->name,
                            'date' => now(),
                        ]);
                    }),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha de Creacion')
                    ->description(fn($record) => $record->created_at->diffForHumans())
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualizacion')
                    ->description(fn($record) => $record->updated_at->diffForHumans())
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $records->each->delete();
                            Log::info('NEGOCIOS: Cita eliminada', [
                                'record' => $records->pluck('id'),
                                'user' => Auth::user()->name,
                                'date' => now(),
                            ]);
                        }),
                ]),
            ]);
    }
}
