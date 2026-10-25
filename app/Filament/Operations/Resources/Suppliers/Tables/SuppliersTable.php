<?php

namespace App\Filament\Operations\Resources\Suppliers\Tables;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Http\Controllers\SupplierExportCsvController;
use App\Models\City;
use App\Models\State;
use App\Models\Supplier;
use App\Models\SupplierClasificacion;
use App\Models\User;
use App\Support\Filament\Operations\OutsourcingMedicalDepartmentFields;
use App\Support\Operations\ProviderDeletion;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Proveedores')
            ->description('Las pestañas superiores filtran por tipo de convenio (GENERAL, PREFERENCIAL, etc.). Para ver razón social, cobertura, teléfono local o equipamiento, actívelos en el botón «Columnas».')
            ->modifyQueryUsing(function (Builder $query, bool $isResolvingRecord): Builder {
                if ($isResolvingRecord) {
                    return $query;
                }

                return $query
                    ->leftJoin('states', 'suppliers.state_id', '=', 'states.id')
                    ->leftJoin('cities', 'suppliers.city_id', '=', 'cities.id')
                    ->select([
                        'suppliers.*',
                        'states.definition as supplier_sort_state_definition',
                        'cities.definition as supplier_sort_city_definition',
                    ]);
            })
            ->defaultSort(function (Builder $query, string $direction, HasTable $livewire): Builder {
                if (filled($livewire->getTableSortColumn())) {
                    return $query;
                }

                return $query
                    ->orderBy('suppliers.name', 'asc')
                    ->orderBy('supplier_sort_state_definition', 'asc')
                    ->orderBy('supplier_sort_city_definition', 'asc');
            })
            ->defaultSortOptionLabel('Nombre → Estado sede → Ciudad sede (A–Z)')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre comercial')
                    ->icon('heroicon-o-building-storefront')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Supplier $record): string => trim((string) $record->name))
                    ->sortable()
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-52 sm:min-w-64 lg:min-w-72 max-w-[28rem] align-top',
                    ]),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->icon('heroicon-o-identification')
                    ->searchable()
                    ->sortable()
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(),
                TextColumn::make('razon_social')
                    ->label('Razón social')
                    ->icon('heroicon-o-document-text')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Supplier $record): string => trim((string) $record->razon_social))
                    ->sortable()
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-52 sm:min-w-64 lg:min-w-72 max-w-[28rem] align-top',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status_convenio')
                    ->label('Convenio')
                    ->icon('heroicon-o-document-check')
                    ->badge()
                    ->color(fn (?string $state): string => match (true) {
                        str_contains(strtoupper((string) $state), 'PREFERENCIAL') => 'success',
                        str_contains(strtoupper((string) $state), 'GENERAL') => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status_sistema')
                    ->label('Estado en sistema')
                    ->icon('heroicon-o-signal')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'AFILIADO' => 'success',
                        'EN PROCESO' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                OutsourcingMedicalDepartmentFields::tableColumn(),
                TextColumn::make('SupplierClasificacion.description')
                    ->label('Clasificación')
                    ->icon('heroicon-o-tag')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo_clinica')
                    ->label('Tipo de clínica')
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type_service')
                    ->label('Tipo de servicio')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->getStateUsing(fn (Supplier $record): ?array => self::normalizeJsonListField($record->type_service))
                    ->badge()
                    ->color('gray')
                    ->listWithLineBreaks()
                    ->limitList(8)
                    ->expandableLimitedList()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state.definition')
                    ->label('Estado (sede)')
                    ->icon('heroicon-o-map')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad (sede)')
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo_servicio')
                    ->label('Zona de cobertura')
                    ->icon('heroicon-o-globe-americas')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'A-NIVEL-NACIONAL' => 'success',
                        'MULTI-ESTADO' => 'warning',
                        'LOCAL' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state_services')
                    ->label('Estados (cobertura)')
                    ->icon('heroicon-o-map')
                    ->getStateUsing(fn (Supplier $record): ?array => self::normalizeJsonListField($record->state_services))
                    ->badge()
                    ->color('info')
                    ->listWithLineBreaks()
                    ->limitList(5)
                    ->expandableLimitedList()
                    ->placeholder('—')
                    ->tooltip(function (Supplier $record): ?string {
                        $items = self::normalizeJsonListField($record->state_services);
                        if ($items === null || $items === []) {
                            return null;
                        }

                        return count($items).' estado(s): '.implode(', ', $items);
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('personal_phone')
                    ->label('Teléfono celular')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('local_phone')
                    ->label('Teléfono local')
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('correo_principal')
                    ->label('Correo principal')
                    ->icon('heroicon-o-envelope')
                    ->searchable()
                    ->copyable()
                    ->limit(32)
                    ->tooltip(fn (Supplier $record): ?string => strlen((string) ($record->correo_principal ?? '')) > 32 ? $record->correo_principal : null)
                    ->toggleable(),
                TextColumn::make('afiliacion_proveedor')
                    ->label('Afiliación')
                    ->icon('heroicon-o-calendar-days')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ubicacion_principal')
                    ->label('Ubicación principal')
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->limit(40)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('convenio_pago')
                    ->label('Convenio de pago')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tiempo_credito')
                    ->label('Tiempo de crédito')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('promedio_costo_proveedor')
                    ->label('Promedio costo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('densitometria_osea')
                    ->boolean()
                    ->label('Densitómetro')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('dialisis')
                    ->boolean()
                    ->label('Diálisis')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('electrocardiograma_centro')
                    ->boolean()
                    ->label('Electrocardiógrafo')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('equipos_especiales_oftalmologia')
                    ->boolean()
                    ->label('Oftalmología')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('mamografia')
                    ->boolean()
                    ->label('Mamógrafo')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('quirofanos')
                    ->boolean()
                    ->label('Quirófanos')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('radioterapia_intraoperatoria')
                    ->boolean()
                    ->label('Radioterapia')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('resonancia')
                    ->boolean()
                    ->label('Resonancia')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('tomografo')
                    ->boolean()
                    ->label('Tomógrafo')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('uci_pediatrica')
                    ->boolean()
                    ->label('UCI pediátrica')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('uci_adulto')
                    ->boolean()
                    ->label('UCI adulto')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('estacionamiento_propio')
                    ->boolean()
                    ->label('Estacionamiento')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('ascensor')
                    ->boolean()
                    ->label('Ascensor')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('robotica')
                    ->boolean()
                    ->label('Cirugía robótica')
                    ->alignCenter()
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
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ProviderDeletion::makeDeletedFilter(),
                Filter::make('state_city')
                    ->label('Estado / Ciudad (sede)')
                    ->form([
                        Select::make('state_id')
                            ->label('Estado')
                            ->options(State::query()->orderBy('definition')->pluck('definition', 'id'))
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('city_id')
                            ->label('Ciudad')
                            ->options(function (Get $get) {
                                $stateId = $get('state_id');

                                if (blank($stateId)) {
                                    return [];
                                }

                                return City::query()
                                    ->where('state_id', $stateId)
                                    ->orderBy('definition')
                                    ->pluck('definition', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['state_id'])) {
                            $query->where('suppliers.state_id', $data['state_id']);
                        }

                        if (! empty($data['city_id'])) {
                            $query->where('suppliers.city_id', $data['city_id']);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (! empty($data['state_id'])) {
                            $indicators['state'] = 'Estado: '.State::find($data['state_id'])?->definition;
                        }

                        if (! empty($data['city_id'])) {
                            $indicators['city'] = 'Ciudad: '.City::find($data['city_id'])?->definition;
                        }

                        return $indicators;
                    }),

                OutsourcingMedicalDepartmentFields::tableFilter(),
                SelectFilter::make('tipo_servicio')
                    ->label('Zona de cobertura')
                    ->options([
                        'A-NIVEL-NACIONAL' => 'A nivel nacional',
                        'MULTI-ESTADO' => 'Multi-estado',
                        'LOCAL' => 'Local',
                    ]),
                SelectFilter::make('clasificacion')
                    ->label('Clasificación del proveedor')
                    ->attribute('supplier_clasificacion_id')
                    ->options(SupplierClasificacion::query()->orderBy('description')->pluck('description', 'id')),
                SelectFilter::make('created_by')
                    ->label('Coordinador encargado:')
                    ->options(function (): array {
                        return User::query()
                            ->select(['name', 'departament'])
                            ->where('departament', 'like', '%OPERACIONES%')
                            ->orderBy('name')
                            ->get()
                            ->filter(function (User $user): bool {
                                $departaments = is_array($user->departament)
                                    ? $user->departament
                                    : (filled($user->departament) ? [(string) $user->departament] : []);

                                return in_array('OPERACIONES', $departaments, true);
                            })
                            ->pluck('name', 'name')
                            ->toArray();
                    }),
                Filter::make('afiliacion_proveedor')
                    ->label('Afiliación proveedor')
                    ->form([
                        DatePicker::make('desde')
                            ->format('d/m/Y')
                            ->label('Desde (Afiliación Proveedor)'),
                        DatePicker::make('hasta')
                            ->format('d/m/Y')
                            ->label('Hasta (Afiliación Proveedor)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['desde'])) {
                            $desde = Carbon::parse($data['desde'])->format('Y-m-d');
                            $query->whereRaw('STR_TO_DATE(afiliacion_proveedor, "%d/%m/%Y") >= ?', [$desde]);
                        }
                        if (! empty($data['hasta'])) {
                            $hasta = Carbon::parse($data['hasta'])->format('Y-m-d');
                            $query->whereRaw('STR_TO_DATE(afiliacion_proveedor, "%d/%m/%Y") <= ?', [$hasta]);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Afiliación desde '.Carbon::parse($data['desde'])->format('d/m/Y');
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Afiliación hasta '.Carbon::parse($data['hasta'])->format('d/m/Y');
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
            /** Un proveedor eliminado no tiene ficha: en esa vista la fila no navega. */
            ->recordUrl(fn (Model $record, mixed $livewire): ?string => ProviderDeletion::viewingDeleted($livewire)
                ? null
                : SupplierResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->hidden(fn (mixed $livewire): bool => ProviderDeletion::viewingDeleted($livewire)),
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->hidden(fn (mixed $livewire): bool => ProviderDeletion::viewingDeleted($livewire)),
                ProviderDeletion::makeDeleteAction(ProviderDeletion::TYPE_SUPPLIER),
                ProviderDeletion::makeRestoreAction(ProviderDeletion::TYPE_SUPPLIER),
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
                                    ->title('Selecciona al menos un proveedor')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $ids = $records->pluck('id')->all();
                            $token = SupplierExportCsvController::storeIdsAndGetToken($ids);

                            return redirect()->route('operations.suppliers.export-csv', ['token' => $token]);
                        }),
                    ProviderDeletion::makeDeleteBulkAction(ProviderDeletion::TYPE_SUPPLIER),
                    ProviderDeletion::makeRestoreBulkAction(ProviderDeletion::TYPE_SUPPLIER),
                ]),
            ])
            ->emptyStateHeading('No hay proveedores')
            ->emptyStateDescription('Ajuste la búsqueda o los filtros, o cree un nuevo proveedor desde el formulario de alta.')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /**
     * Normaliza campos JSON/array (p. ej. type_service, state_services) para la tabla:
     * decodifica cadenas JSON, aplana un nivel y devuelve etiquetas legibles sin corchetes.
     *
     * @return array<int, string>|null
     */
    public static function normalizeJsonListField(mixed $state): ?array
    {
        if ($state === null) {
            return null;
        }

        if ($state === '' || $state === []) {
            return null;
        }

        if (is_string($state)) {
            $trimmed = trim($state);
            if ($trimmed === '' || $trimmed === '[]' || $trimmed === 'null') {
                return null;
            }

            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $state = $decoded;
                } else {
                    return [$trimmed];
                }
            } else {
                return [$trimmed];
            }
        }

        if (! is_array($state)) {
            return null;
        }

        $items = collect($state)
            ->flatten()
            ->map(function ($v): ?string {
                if ($v === null || $v === '' || $v === []) {
                    return null;
                }
                if (is_scalar($v) || $v instanceof \Stringable) {
                    return trim((string) $v);
                }

                return null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }
}
