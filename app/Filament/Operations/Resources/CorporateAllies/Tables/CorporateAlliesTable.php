<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\CorporateAllies\Tables;

use App\Models\City;
use App\Models\CorporateAlly;
use App\Models\State;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
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
use Illuminate\Support\HtmlString;

class CorporateAlliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Aliados corporativos')
            ->description('Use filtros y columnas ocultas para afinar por ubicación, convenio, contacto o datos de pago.')
            ->defaultSort('company_name', 'asc')
            ->defaultSortOptionLabel('Razón social (A–Z)')
            ->columns([
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
                        'class' => 'min-w-52 sm:min-w-64 lg:min-w-72 max-w-[28rem] align-top',
                    ]),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->searchable()
                    ->sortable()
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('RIF copiado')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('location')
                    ->label('Ubicación')
                    ->icon('heroicon-o-map-pin')
                    ->getStateUsing(fn (CorporateAlly $record): string => self::formatLocation($record))
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (CorporateAlly $record): string => self::formatLocation($record, includeCountryAlways: true))
                    ->placeholder('—')
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-40 sm:min-w-48 max-w-xs align-top',
                    ]),
                TextColumn::make('country.name')
                    ->label('País')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_category')
                    ->label('Categoría')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('type_agreement')
                    ->label('Convenio')
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
                    ->badge()
                    ->color(fn (?string $state): string => self::statusBadgeColor($state))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state): string => self::statusBadgeColor($state))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('contact')
                    ->label('Contacto')
                    ->icon('heroicon-o-phone')
                    ->getStateUsing(fn (CorporateAlly $record): HtmlString|string => self::formatContactHtml($record))
                    ->html()
                    ->tooltip(fn (CorporateAlly $record): ?string => self::formatContactPlain($record))
                    ->placeholder('—')
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-44 sm:min-w-56 max-w-xs align-top',
                    ]),
                TextColumn::make('phone')
                    ->label('Teléfono principal')
                    ->searchable()
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('people_contact')
                    ->label('Teléfono secundario')
                    ->searchable()
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(32)
                    ->tooltip(fn (CorporateAlly $record): ?string => strlen((string) ($record->email ?? '')) > 32 ? $record->email : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('social_networks')
                    ->label('Redes sociales')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('services')
                    ->label('Servicios')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_term')
                    ->label('Plazo de pago')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_payment')
                    ->label('Forma de pago proveedor')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('local_beneficiary_account_bank')
                    ->label('Banco local')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_account_bank')
                    ->label('Banco internacional')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_beneficiary_zelle')
                    ->label('Zelle')
                    ->searchable()
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('Zelle copiado')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
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
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver')
                        ->icon('heroicon-o-eye'),
                    EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square'),
                ])
                    ->label('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->emptyStateHeading('Sin aliados corporativos')
            ->emptyStateDescription('Cree un aliado o relaje los filtros y la búsqueda para ver resultados.')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Crear aliado corporativo')
                    ->icon('heroicon-o-plus'),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    private static function statusBadgeColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'AFILIADO', 'ACTIVO' => 'success',
            'EN PROCESO' => 'warning',
            'INACTIVO', 'SUSPENDIDO' => 'danger',
            default => 'gray',
        };
    }

    private static function formatLocation(CorporateAlly $record, bool $includeCountryAlways = false): string
    {
        $state = trim((string) ($record->state?->definition ?? ''));
        $city = trim((string) ($record->city?->definition ?? ''));
        $country = trim((string) ($record->country?->name ?? ''));

        $parts = array_values(array_filter([$state, $city], filled(...)));

        $location = $parts === [] ? '' : implode(' · ', $parts);

        $shouldShowCountry = $includeCountryAlways
            || (filled($country) && ! str_contains(mb_strtoupper($country), 'VENEZUELA'));

        if ($shouldShowCountry && filled($country)) {
            $location = filled($location) ? "{$location} · {$country}" : $country;
        }

        return filled($location) ? $location : '—';
    }

    private static function formatContactPlain(CorporateAlly $record): ?string
    {
        $lines = array_values(array_filter([
            trim((string) ($record->phone ?? '')),
            trim((string) ($record->email ?? '')),
        ], filled(...)));

        return $lines === [] ? null : implode(' · ', $lines);
    }

    private static function formatContactHtml(CorporateAlly $record): HtmlString|string
    {
        $phone = trim((string) ($record->phone ?? ''));
        $email = trim((string) ($record->email ?? ''));

        if ($phone === '' && $email === '') {
            return '—';
        }

        $html = '<div class="space-y-0.5 text-xs leading-snug">';

        if ($phone !== '') {
            $html .= '<div class="font-mono text-gray-900 dark:text-gray-100">'.e($phone).'</div>';
        }

        if ($email !== '') {
            $html .= '<div class="truncate text-gray-600 dark:text-gray-400" title="'.e($email).'">'.e($email).'</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
