<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\BusinessAppointments\Tables;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentLabels;
use App\Models\BusinessAppointments;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BusinessAppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Citas')
            ->description('Citas del portal TuDrGroup: cambia el estado desde la columna o usa filtros y acciones.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'country',
                'state',
                'city',
            ]))
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->recordTitleAttribute('legal_name')
            ->emptyStateHeading('Sin citas')
            ->emptyStateDescription('No hay citas registradas o no coinciden con los filtros.')
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->columns([
                ColumnGroup::make('Contacto', [
                    TextColumn::make('legal_name')
                        ->label('Nombre')
                        ->icon(Heroicon::OutlinedUser)
                        ->weight('medium')
                        ->searchable()
                        ->sortable()
                        ->limit(36)
                        ->tooltip(fn (BusinessAppointments $record): string => $record->legal_name),
                    TextColumn::make('phone')
                        ->label('Teléfono')
                        ->icon(Heroicon::OutlinedPhone)
                        ->searchable()
                        ->copyable()
                        ->copyMessage('Teléfono copiado')
                        ->copyMessageDuration(1500)
                        ->placeholder('—'),
                    TextColumn::make('email')
                        ->label('Correo')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->searchable()
                        ->copyable()
                        ->copyMessage('Correo copiado')
                        ->copyMessageDuration(1500)
                        ->placeholder('—'),
                ]),
                ColumnGroup::make('Ubicación', [
                    TextColumn::make('location')
                        ->label('Ubicación')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->getStateUsing(function (BusinessAppointments $record): string {
                            $parts = array_filter([
                                $record->city?->definition,
                                $record->state?->definition,
                                $record->country?->name,
                            ]);

                            return $parts !== [] ? implode(' · ', $parts) : '—';
                        })
                        ->placeholder('—')
                        ->toggleable(),
                ]),
                ColumnGroup::make('Gestión', [
                    SelectColumn::make('status')
                        ->label('Estado')
                        ->options(BusinessAppointmentLabels::statusOptions())
                        ->searchableOptions()
                        ->afterStateUpdated(function (BusinessAppointments $record, mixed $state): void {
                            $record->update([
                                'status' => $state,
                                'updated_by' => Auth::user()?->name,
                            ]);

                            Log::info('NEGOCIOS: Cita actualizada', [
                                'record' => $record->id,
                                'state' => $state,
                                'user' => Auth::user()?->name,
                                'date' => now(),
                            ]);

                            Notification::make()
                                ->title('Estado actualizado')
                                ->body('La cita quedó como: '.BusinessAppointmentLabels::statusLabel(is_string($state) ? $state : null))
                                ->success()
                                ->send();
                        }),
                ]),
                ColumnGroup::make('Auditoría', [
                    TextColumn::make('created_by')
                        ->label('Creado por')
                        ->searchable()
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_by')
                        ->label('Actualizado por')
                        ->searchable()
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('created_at')
                        ->label('Alta')
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (BusinessAppointments $record): string => $record->created_at->diffForHumans())
                        ->sortable(),
                    TextColumn::make('updated_at')
                        ->label('Última actualización')
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (BusinessAppointments $record): string => $record->updated_at->diffForHumans())
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(BusinessAppointmentLabels::statusOptions())
                    ->placeholder('Todos')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->label('Eliminar seleccionadas')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->action(function (Collection $records): void {
                            $records->each->delete();

                            Log::info('NEGOCIOS: Citas eliminadas', [
                                'record' => $records->pluck('id'),
                                'user' => Auth::user()?->name,
                                'date' => now(),
                            ]);

                            Notification::make()
                                ->title('Registros eliminados')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
