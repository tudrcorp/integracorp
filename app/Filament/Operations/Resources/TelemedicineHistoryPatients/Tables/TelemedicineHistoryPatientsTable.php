<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Tables;

use App\Models\TelemedicineHistoryPatient;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TelemedicineHistoryPatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Historias clínicas')
            ->description('Resumen por registro: código, fechas y médico/paciente. Use «Ver» para el detalle completo o los filtros para acotar la lista.')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->icon('heroicon-m-clipboard-document-list')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->copyable()
                    ->copyMessage('Código copiado al portapapeles')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                TextColumn::make('history_date')
                    ->label('Fecha historia')
                    ->icon('heroicon-o-calendar-days')
                    ->placeholder('—')
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }
                        try {
                            return Carbon::parse($state)->translatedFormat('d/m/Y');
                        } catch (\Throwable) {
                            return $state;
                        }
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Médico')
                    ->icon('heroicon-o-user-circle')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->icon('heroicon-o-heart')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->icon('heroicon-o-pencil-square')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Alta en sistema')
                    ->icon('heroicon-o-clock')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (TelemedicineHistoryPatient $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->toggleable(),
                TextColumn::make('updated_by')
                    ->label('Última edición por')
                    ->icon('heroicon-o-arrow-path')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->icon('heroicon-o-calendar')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (TelemedicineHistoryPatient $record): string => $record->updated_at?->diffForHumans() ?? '')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('telemedicine_doctor_id')
                    ->label('Médico')
                    ->relationship('telemedicineDoctor', 'full_name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Filter::make('created_at')
                    ->label('Fecha de registro')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['desde'])) {
                            $indicators['desde'] = 'Desde '.Carbon::parse($data['desde'])->translatedFormat('d/m/Y');
                        }
                        if (! empty($data['hasta'])) {
                            $indicators['hasta'] = 'Hasta '.Carbon::parse($data['hasta'])->translatedFormat('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon('heroicon-o-funnel'),
            )
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle')
                    ->icon('heroicon-o-eye')
                    ->color('success'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar historias')
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar historias clínicas')
                        ->modalDescription('¿Confirma la eliminación de las historias seleccionadas? Esta acción no se puede deshacer.')
                        ->modalIcon('heroicon-o-trash')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->successNotificationTitle('Historias eliminadas')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $patientName = $record->telemedicinePatient?->full_name ?? 'N/D';
                                Log::info('OPERACIONES: El usuario '.Auth::user()->name.' eliminó la historia clínica del paciente: '.$patientName);
                                $record->delete();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No hay historias clínicas')
            ->emptyStateDescription('Cuando se registre una historia, aparecerá aquí. Puede crear una nueva con el botón superior.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
