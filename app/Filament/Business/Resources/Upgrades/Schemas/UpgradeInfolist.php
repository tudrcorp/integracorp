<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Upgrades\Schemas;

use App\Models\Upgrade;
use App\Models\UpgradeCoverage;
use App\Models\UpgradeCoverageAgeRange;
use App\Models\UpgradeService;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UpgradeInfolist
{
    private const ENTRY_WRAPPER = 'rounded-2xl border border-slate-200/70 bg-white/70 px-3 py-3 dark:border-white/10 dark:bg-white/5 sm:px-4 sm:py-4';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del upgrade')
                    ->icon(Heroicon::Sparkles)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código')
                            ->badge()
                            ->color('primary')
                            ->copyable(),
                        TextEntry::make('name')
                            ->label('Nombre')
                            ->weight('semibold')
                            ->columnSpan(2),
                        TextEntry::make('status')
                            ->label('Estatus')
                            ->badge()
                            ->color(fn (?string $state): string => $state === Upgrade::STATUS_ACTIVE ? 'success' : 'danger'),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción.')
                            ->columnSpanFull(),
                        TextEntry::make('active_corporate_assignments_count')
                            ->label('Afiliados corporativos con el upgrade')
                            ->numeric()
                            ->icon(Heroicon::BuildingOffice2),
                        TextEntry::make('active_individual_assignments_count')
                            ->label('Afiliados individuales con el upgrade')
                            ->numeric()
                            ->icon(Heroicon::UserGroup),
                        TextEntry::make('updated_by')
                            ->label('Última modificación')
                            ->placeholder('—')
                            ->helperText(fn (Upgrade $record): ?string => $record->updated_at?->format('d/m/Y h:i a')),
                    ]),

                Section::make('Servicios incluidos')
                    ->icon(Heroicon::ListBullet)
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('upgrade_services')
                            ->hiddenLabel()
                            ->placeholder('Este upgrade no detalla servicios.')
                            ->state(fn (Upgrade $record): array => $record->activeServices
                                ->map(fn (UpgradeService $service): array => [
                                    'name' => $service->name,
                                    'description' => $service->description,
                                ])
                                ->all())
                            ->extraEntryWrapperAttributes(['class' => self::ENTRY_WRAPPER])
                            ->table([
                                TableColumn::make('Servicio'),
                                TableColumn::make('Detalle'),
                            ])
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('description')->placeholder('—'),
                            ]),
                    ]),

                Section::make('Coberturas y precios por edad')
                    ->description('Precio anual en US$ que paga cada afiliado según su edad.')
                    ->icon(Heroicon::CurrencyDollar)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('upgrade_price_rows')
                            ->hiddenLabel()
                            ->placeholder('Este upgrade no tiene coberturas activas: no se puede asignar.')
                            ->state(fn (Upgrade $record): array => $record->activeCoverages
                                ->flatMap(fn (UpgradeCoverage $coverage): array => $coverage->activeAgeRanges
                                    ->map(fn (UpgradeCoverageAgeRange $range): array => [
                                        'coverage' => $coverage->label(),
                                        'range' => $range->label(),
                                        'price' => (float) $range->price,
                                    ])
                                    ->all())
                                ->values()
                                ->all())
                            ->extraEntryWrapperAttributes(['class' => self::ENTRY_WRAPPER])
                            ->table([
                                TableColumn::make('Cobertura'),
                                TableColumn::make('Rango de edad'),
                                TableColumn::make('Precio anual'),
                            ])
                            ->schema([
                                TextEntry::make('coverage'),
                                TextEntry::make('range'),
                                TextEntry::make('price')->money('USD', locale: 'es'),
                            ]),
                    ]),
            ]);
    }
}
