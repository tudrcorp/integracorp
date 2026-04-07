<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Schemas;

use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\Fee;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private static function planStatusColor(?string $state): string
    {
        return match (strtolower((string) $state)) {
            'active', 'activo', 'publicado' => 'success',
            'draft', 'borrador' => 'gray',
            'inactivo', 'cancelado' => 'danger',
            default => 'primary',
        };
    }

    private static function ageRangeLabel(?AgeRange $ageRange): string
    {
        if ($ageRange === null) {
            return '—';
        }

        if (filled($ageRange->age_init) || filled($ageRange->age_end)) {
            return ($ageRange->age_init ?? '—').' – '.($ageRange->age_end ?? '—').' años';
        }

        return filled($ageRange->range) ? (string) $ageRange->range : '—';
    }

    /**
     * Filas para el infolist: primero `age_ranges` del plan/cobertura; si no hay, `fees` + `age_range`.
     *
     * @return list<array{range: ?string, ages_label: string, fee: float|null}>
     */
    private static function coverageAgeRowsForInfolist(Coverage $coverage): array
    {
        $query = AgeRange::query()
            ->where('coverage_id', $coverage->getKey());

        if (filled($coverage->plan_id)) {
            $query->where('plan_id', $coverage->plan_id);
        }

        $ageRanges = $query
            ->orderBy('age_init')
            ->orderBy('id')
            ->get();

        if ($ageRanges->isEmpty() && filled($coverage->plan_id)) {
            $ageRanges = AgeRange::query()
                ->where('coverage_id', $coverage->getKey())
                ->orderBy('age_init')
                ->orderBy('id')
                ->get();
        }

        if ($ageRanges->isNotEmpty()) {
            return $ageRanges
                ->map(fn (AgeRange $ar): array => [
                    'range' => $ar->range,
                    'ages_label' => self::ageRangeLabel($ar),
                    'fee' => $ar->fee !== null && $ar->fee !== '' ? (float) $ar->fee : null,
                ])
                ->values()
                ->all();
        }

        return Fee::query()
            ->where('coverage_id', $coverage->getKey())
            ->with('ageRange')
            ->orderBy('age_range_id')
            ->get()
            ->map(function (Fee $fee): array {
                $ar = $fee->ageRange;

                return [
                    'range' => $fee->range ?? $ar?->range,
                    'ages_label' => self::ageRangeLabel($ar),
                    'fee' => $fee->price !== null && $fee->price !== '' ? (float) $fee->price : null,
                ];
            })
            ->values()
            ->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Resumen del plan')
                    ->description('Identificación y estado del plan.')
                    ->icon('heroicon-o-identification')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('description')
                                    ->label('Nombre del plan')
                                    ->size('lg')
                                    ->weight('semibold')
                                    ->color('gray')
                                    ->placeholder('Sin descripción'),
                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                    ->schema([
                                        TextEntry::make('code')
                                            ->label('Código')
                                            ->placeholder('—'),
                                        TextEntry::make('type')
                                            ->label('Tipo')
                                            ->badge()
                                            ->color('gray')
                                            ->placeholder('—'),
                                        TextEntry::make('status')
                                            ->label('Estatus')
                                            ->badge()
                                            ->color(fn (?string $state): string => self::planStatusColor($state))
                                            ->placeholder('—'),
                                        TextEntry::make('businessUnit.name')
                                            ->label('Unidad de negocio')
                                            ->placeholder('—'),
                                        TextEntry::make('created_by')
                                            ->label('Creado por')
                                            ->placeholder('—'),
                                        TextEntry::make('created_at')
                                            ->label('Fecha de creación')
                                            ->dateTime()
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Beneficios incluidos')
                    ->description('Servicios o beneficios vinculados a este plan (por ejemplo en modo paquete).')
                    ->icon('heroicon-o-queue-list')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        RepeatableEntry::make('benefitPlans')
                            ->label('')
                            ->placeholder('No hay beneficios asociados a este plan.')
                            ->extraEntryWrapperAttributes([
                                'class' => 'rounded-2xl border border-slate-200/70 bg-white/70 px-3 py-3 dark:border-white/10 dark:bg-white/5 sm:px-4 sm:py-4',
                            ])
                            ->table([
                                TableColumn::make('Beneficio'),
                                TableColumn::make('Detalle en plan'),
                            ])
                            ->schema([
                                TextEntry::make('description')
                                    ->label('Beneficio')
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('pivot.description')
                                    ->label('Detalle en plan')
                                    ->placeholder('—'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Coberturas y tarifas anuales')
                    ->description('Cada cobertura con sus rangos de edad y prima anual configurada.')
                    ->icon('heroicon-o-shield-check')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        RepeatableEntry::make('coverages')
                            ->label('')
                            ->placeholder('No hay coberturas registradas para este plan.')
                            ->extraEntryWrapperAttributes([
                                'class' => 'rounded-[1.25rem] border border-slate-200/85 bg-gradient-to-b from-white/95 to-slate-50/90 p-4 shadow-[0_8px_24px_-8px_rgba(15,23,42,0.15)] dark:border-white/10 dark:from-gray-900/85 dark:to-slate-950/90 mb-4 last:mb-0',
                            ])
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Grid::make(['default' => 1, 'md' => 3])
                                            ->schema([
                                                TextEntry::make('id')
                                                    ->label('Referencia')
                                                    ->badge()
                                                    ->color('primary'),
                                                TextEntry::make('price')
                                                    ->label('Valor cobertura (lista)')
                                                    ->money('USD')
                                                    ->placeholder('—'),
                                                TextEntry::make('status')
                                                    ->label('Estado')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::planStatusColor($state))
                                                    ->placeholder('—'),
                                            ]),
                                        RepeatableEntry::make('coverage_age_rows')
                                            ->label('Rangos de edad y tarifa anual')
                                            ->placeholder('Sin rangos de edad ni tarifas registrados para esta cobertura.')
                                            ->state(function (mixed $record): array {
                                                if (! $record instanceof Coverage) {
                                                    return [];
                                                }

                                                return self::coverageAgeRowsForInfolist($record);
                                            })
                                            ->table([
                                                TableColumn::make('Rango'),
                                                TableColumn::make('Edades'),
                                                TableColumn::make('Tarifa anual (US$)'),
                                            ])
                                            ->schema([
                                                TextEntry::make('range')
                                                    ->label('Rango')
                                                    ->placeholder('—'),
                                                TextEntry::make('ages_label')
                                                    ->label('Edades')
                                                    ->placeholder('—'),
                                                TextEntry::make('fee')
                                                    ->label('Tarifa anual')
                                                    ->money('USD')
                                                    ->placeholder('—'),
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Última actualización del registro.')
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('updated_at')
                                    ->label('Última modificación')
                                    ->dateTime()
                                    ->placeholder('—'),
                                TextEntry::make('business_unit_id')
                                    ->label('ID unidad de negocio')
                                    ->numeric()
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
