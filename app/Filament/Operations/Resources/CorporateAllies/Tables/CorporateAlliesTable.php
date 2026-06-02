<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\CorporateAllies\Tables;

use App\Models\City;
use App\Models\CorporateAlly;
use App\Models\State;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CorporateAlliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Aliados corporativos')
            ->description('Listado de aliados corporativos con ubicación, convenio, contacto y datos de pago. Use columnas ocultas y filtros para afinar la búsqueda.')
            ->defaultSort('company_name', 'asc')
            ->defaultSortOptionLabel('Razón social (A–Z)')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->icon('heroicon-m-finger-print')
                    ->sortable()
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('company_name')
                    ->label('Razón social')
                    ->icon('heroicon-o-building-office-2')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (CorporateAlly $record): string => trim((string) ($record->company_name ?? '')) ?: '—')
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
                TextColumn::make('country.name')
                    ->label('País')
                    ->icon('heroicon-o-globe-americas')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->icon('heroicon-o-map')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('supplier_category')
                    ->label('Categoría proveedor')
                    ->icon('heroicon-o-tag')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('type_agreement')
                    ->label('Tipo de convenio')
                    ->icon('heroicon-o-document-text')
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
                TextColumn::make('status_agreement')
                    ->label('Estatus convenio')
                    ->icon('heroicon-o-document-check')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'AFILIADO', 'ACTIVO' => 'success',
                        'EN PROCESO' => 'warning',
                        'INACTIVO', 'SUSPENDIDO' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Teléfono principal')
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('people_contact')
                    ->label('Teléfono secundario')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->searchable()
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon('heroicon-o-envelope')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('social_networks')
                    ->label('Redes sociales')
                    ->icon('heroicon-o-share')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon('heroicon-o-home')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('services')
                    ->label('Servicios')
                    ->icon('heroicon-o-briefcase')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_term')
                    ->label('Plazo de pago')
                    ->icon('heroicon-o-calendar-days')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_payment')
                    ->label('Forma de pago proveedor')
                    ->icon('heroicon-o-banknotes')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_account_bank')
                    ->label('Banco local')
                    ->icon('heroicon-o-building-library')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_account_bank')
                    ->label('Banco internacional')
                    ->icon('heroicon-o-globe-alt')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_zelle')
                    ->label('Zelle')
                    ->icon('heroicon-o-currency-dollar')
                    ->searchable()
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->icon('heroicon-o-signal')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'AFILIADO', 'ACTIVO' => 'success',
                        'EN PROCESO' => 'warning',
                        'INACTIVO', 'SUSPENDIDO' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
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
            ])
            ->filters([
                SelectFilter::make('supplier_category')
                    ->label('Categoría proveedor')
                    ->options(fn (): array => CorporateAlly::query()
                        ->whereNotNull('supplier_category')
                        ->where('supplier_category', '!=', '')
                        ->distinct()
                        ->orderBy('supplier_category')
                        ->pluck('supplier_category', 'supplier_category')
                        ->all()),
                SelectFilter::make('type_agreement')
                    ->label('Tipo de convenio')
                    ->options(fn (): array => CorporateAlly::query()
                        ->whereNotNull('type_agreement')
                        ->where('type_agreement', '!=', '')
                        ->distinct()
                        ->orderBy('type_agreement')
                        ->pluck('type_agreement', 'type_agreement')
                        ->all()),
                SelectFilter::make('status_agreement')
                    ->label('Estatus convenio')
                    ->options(fn (): array => CorporateAlly::query()
                        ->whereNotNull('status_agreement')
                        ->where('status_agreement', '!=', '')
                        ->distinct()
                        ->orderBy('status_agreement')
                        ->pluck('status_agreement', 'status_agreement')
                        ->all()),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(fn (): array => CorporateAlly::query()
                        ->whereNotNull('status')
                        ->where('status', '!=', '')
                        ->distinct()
                        ->orderBy('status')
                        ->pluck('status', 'status')
                        ->all()),
                SelectFilter::make('country_id')
                    ->label('País')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('state_city')
                    ->label('Estado / Ciudad')
                    ->form([
                        Select::make('state_id')
                            ->label('Estado')
                            ->options(State::query()->orderBy('definition')->pluck('definition', 'id'))
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('city_id')
                            ->label('Ciudad')
                            ->options(function (Get $get): array {
                                $stateId = $get('state_id');

                                if (blank($stateId)) {
                                    return [];
                                }

                                return City::query()
                                    ->where('state_id', $stateId)
                                    ->orderBy('definition')
                                    ->pluck('definition', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['state_id'])) {
                            $query->where('corporate_allies.state_id', $data['state_id']);
                        }

                        if (! empty($data['city_id'])) {
                            $query->where('corporate_allies.city_id', $data['city_id']);
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
            ->emptyStateHeading('Sin aliados corporativos')
            ->emptyStateDescription('Cree un registro desde «Crear aliado corporativo» o relaje los filtros y la búsqueda.')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
