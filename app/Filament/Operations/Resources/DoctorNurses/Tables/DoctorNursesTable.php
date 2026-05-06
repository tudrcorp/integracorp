<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\DoctorNurses\Tables;

use App\Http\Controllers\DoctorNurseExportCsvController;
use App\Models\DoctorNurse;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DoctorNursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Proveedores naturales')
            ->description('Listado de médicos y enfermería con ubicación, convenio y datos de contacto. Use columnas ocultas y filtros para afinar la búsqueda.')
            ->defaultSort('name', 'asc')
            ->defaultSortOptionLabel('Nombre (A–Z)')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre / contacto principal')
                    ->icon('heroicon-o-user')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (DoctorNurse $record): string => trim((string) ($record->name ?? '')) ?: '—')
                    ->placeholder('—')
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-44 sm:min-w-56 max-w-xs align-top',
                    ]),
                TextColumn::make('speciality')
                    ->label('Especialidad')
                    ->icon('heroicon-o-academic-cap')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('razon_social')
                    ->label('Razón social')
                    ->icon('heroicon-o-building-office-2')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (DoctorNurse $record): string => trim((string) ($record->razon_social ?? '')) ?: '—')
                    ->placeholder('—')
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-44 sm:min-w-56 max-w-sm align-top',
                    ]),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->icon('heroicon-o-identification')
                    ->searchable()
                    ->sortable()
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status_convenio')
                    ->label('Convenio')
                    ->icon('heroicon-o-document-check')
                    ->badge()
                    ->color(fn (?string $state): string => match (true) {
                        str_contains(strtoupper((string) $state), 'PREFERENCIAL') => 'success',
                        str_contains(strtoupper((string) $state), 'GENERAL') => 'info',
                        filled($state) => 'gray',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('status_sistema')
                    ->label('Estado en sistema')
                    ->icon('heroicon-o-signal')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'AFILIADO' => 'success',
                        'EN PROCESO' => 'warning',
                        'INACTIVO', 'SUSPENDIDO' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('supplierClasificacion.description')
                    ->label('Clasificación')
                    ->icon('heroicon-o-tag')
                    ->badge()
                    ->color('info')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('supplierClasificacion', function (Builder $q) use ($search): void {
                            $q->where('description', 'like', '%'.$search.'%');
                        });
                    })
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('state')
                    ->label('Estado')
                    ->icon('heroicon-o-map')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('city')
                    ->label('Ciudad')
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('coverage_zone')
                    ->label('Zona de cobertura')
                    ->icon('heroicon-o-globe-americas')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tipo_clinica')
                    ->label('Tipo de clínica')
                    ->icon('heroicon-o-building-library')
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('horario')
                    ->label('Horario')
                    ->icon('heroicon-o-clock')
                    ->searchable()
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('personal_phone')
                    ->label('Tel. personal')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('local_phone')
                    ->label('Tel. local')
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('correo_principal')
                    ->label('Correo')
                    ->icon('heroicon-o-envelope')
                    ->searchable()
                    ->copyable()
                    ->limit(28)
                    ->tooltip(fn (DoctorNurse $record): ?string => strlen((string) ($record->correo_principal ?? '')) > 28 ? (string) $record->correo_principal : null)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('ubicacion_principal')
                    ->label('Ubicación principal')
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->limit(36)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('convenio_pago')
                    ->label('Convenio de pago')
                    ->icon('heroicon-o-banknotes')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tiempo_credito')
                    ->label('Tiempo de crédito')
                    ->icon('heroicon-o-calendar')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('afiliacion_proveedor')
                    ->label('Afiliación proveedor')
                    ->icon('heroicon-o-calendar-days')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->icon('heroicon-o-clock')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->icon('heroicon-o-arrow-path')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->icon('heroicon-o-user-plus')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->icon('heroicon-o-pencil')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('supplier_clasificacion_id')
                    ->label('Clasificación')
                    ->relationship('supplierClasificacion', 'description')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status_convenio')
                    ->label('Convenio')
                    ->options(fn (): array => DoctorNurse::query()
                        ->whereNotNull('status_convenio')
                        ->where('status_convenio', '!=', '')
                        ->distinct()
                        ->orderBy('status_convenio')
                        ->pluck('status_convenio', 'status_convenio')
                        ->all()),
                SelectFilter::make('status_sistema')
                    ->label('Estado en sistema')
                    ->options(fn (): array => DoctorNurse::query()
                        ->whereNotNull('status_sistema')
                        ->where('status_sistema', '!=', '')
                        ->distinct()
                        ->orderBy('status_sistema')
                        ->pluck('status_sistema', 'status_sistema')
                        ->all()),
                SelectFilter::make('tipo_clinica')
                    ->label('Tipo de clínica')
                    ->options(fn (): array => DoctorNurse::query()
                        ->whereNotNull('tipo_clinica')
                        ->where('tipo_clinica', '!=', '')
                        ->distinct()
                        ->orderBy('tipo_clinica')
                        ->pluck('tipo_clinica', 'tipo_clinica')
                        ->all()),
                Filter::make('ubicacion')
                    ->label('Ubicación (texto)')
                    ->form([
                        TextInput::make('state')
                            ->label('Estado (contiene)'),
                        TextInput::make('city')
                            ->label('Ciudad (contiene)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['state'] ?? null)) {
                            $query->where('state', 'like', '%'.$data['state'].'%');
                        }
                        if (filled($data['city'] ?? null)) {
                            $query->where('city', 'like', '%'.$data['city'].'%');
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if (filled($data['state'] ?? null)) {
                            $i['state'] = 'Estado: '.$data['state'];
                        }
                        if (filled($data['city'] ?? null)) {
                            $i['city'] = 'Ciudad: '.$data['city'];
                        }

                        return $i;
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
                    ->label('Ver')
                    ->icon('heroicon-o-eye'),
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportCsvController')
                        ->label('Exportar CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos un proveedor natural')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $ids = $records->pluck('id')->all();
                            $token = DoctorNurseExportCsvController::storeIdsAndGetToken($ids);

                            return redirect()->route('operations.doctor-nurses.export-csv', ['token' => $token]);
                        }),
                    DeleteBulkAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-s-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar proveedores naturales')
                        ->modalDescription('¿Confirma eliminar los registros seleccionados? Esta acción no se puede deshacer.')
                        ->modalSubmitActionLabel('Eliminar')
                        ->color('danger'),
                ]),
            ])
            ->emptyStateHeading('Sin proveedores naturales')
            ->emptyStateDescription('Cree un registro desde «Crear» o relaje los filtros y la búsqueda.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
