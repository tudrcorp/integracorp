<?php

namespace App\Filament\Business\Resources\DressTylorQuotes\Schemas;

use App\Models\DressTylorQuote;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DressTylorQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cotización Dress Tylor')
                    ->description(fn (DressTylorQuote $record) => $record->quote_structure
                        ? 'Estructura guardada el '.($record->updated_at?->format('d/m/Y H:i') ?? $record->created_at->format('d/m/Y H:i'))
                        : 'Sin estructura de cotización guardada.')
                    ->icon(Heroicon::DocumentText)
                    ->columnSpanFull()
                    ->schema([
                        Fieldset::make('Datos del cliente')
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre o Razón Social'),
                                TextEntry::make('rifCi')
                                    ->label('RIF / Cédula'),
                                TextEntry::make('email')
                                    ->label('Correo electrónico'),
                                TextEntry::make('planName')
                                    ->label('Nombre del plan'),
                                TextEntry::make('quote_structure.plan_name')
                                    ->label('Plan (estructura)')
                                    ->default('—')
                                    ->visible(fn (DressTylorQuote $record) => ! empty($record->quote_structure)),
                                TextEntry::make('quote_structure.date')
                                    ->label('Fecha de cotización')
                                    ->visible(fn (DressTylorQuote $record) => ! empty($record->quote_structure)),
                                TextEntry::make('quote_structure.user_name')
                                    ->label('Cotizado por')
                                    ->visible(fn (DressTylorQuote $record) => ! empty($record->quote_structure)),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        Fieldset::make('Beneficios del plan')
                            ->schema([
                                TextEntry::make('quote_structure.benefits_processed')
                                    ->label('Beneficios incluidos')
                                    ->formatStateUsing(function ($state) {
                                        if (! is_array($state) || empty($state)) {
                                            return '—';
                                        }
                                        $lines = array_map(fn ($b) => $b['name'] ?? '', $state);

                                        return implode("\n", array_filter($lines)) ?: '—';
                                    })
                                    ->markdown()
                                    ->columnSpanFull(),
                                TextEntry::make('quote_structure.total_benefits_per_person')
                                    ->label('Total beneficios por persona (US$)')
                                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, ',', '.') : '—'),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn (DressTylorQuote $record) => ! empty($record->quote_structure)),

                        Fieldset::make('Coberturas')
                            ->schema([
                                TextEntry::make('quote_structure.all_coverages')
                                    ->label('Coberturas')
                                    ->formatStateUsing(function ($state) {
                                        if (! is_array($state)) {
                                            return 'Tarifa base';
                                        }
                                        $count = count($state);
                                        if ($count === 0) {
                                            return 'Tarifa base';
                                        }

                                        return $count === 1 ? '1 cobertura' : "{$count} coberturas";
                                    }),
                            ])
                            ->columnSpanFull()
                            ->visible(fn (DressTylorQuote $record) => ! empty($record->quote_structure)),

                        Fieldset::make('Análisis por edad y población')
                            ->schema([
                                TextEntry::make('quote_structure.age_analysis')
                                    ->label('Desglose por rango de edad')
                                    ->formatStateUsing(function ($state) {
                                        if (! is_array($state) || empty($state)) {
                                            return '—';
                                        }
                                        $lines = [];
                                        foreach ($state as $row) {
                                            $range = $row['age_range'] ?? 'N/A';
                                            $costs = $row['costs_by_coverage'] ?? [];
                                            $total = array_sum(array_column($costs, 'total'));
                                            $lines[] = "{$range}: US$ ".number_format($total, 2, ',', '.');
                                        }

                                        return implode("\n", $lines) ?: '—';
                                    })
                                    ->markdown()
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn (DressTylorQuote $record) => ! empty($record->quote_structure)),

                        Fieldset::make('Beneficios upgrade')
                            ->schema([
                                TextEntry::make('quote_structure.upgrade_benefits')
                                    ->label('Ítems upgrade')
                                    ->formatStateUsing(function ($state) {
                                        if (! is_array($state) || empty($state)) {
                                            return 'Ninguno';
                                        }
                                        $lines = array_map(function ($ub) {
                                            $name = $ub['name'] ?? 'N/A';
                                            $pvp = isset($ub['pvp']) ? number_format((float) $ub['pvp'], 2, ',', '.') : '';

                                            return $pvp ? "{$name} — US$ {$pvp}" : $name;
                                        }, $state);

                                        return implode("\n", $lines) ?: 'Ninguno';
                                    })
                                    ->markdown()
                                    ->columnSpanFull(),
                                TextEntry::make('quote_structure.total_upgrade')
                                    ->label('Total upgrade (US$)')
                                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, ',', '.') : '0,00'),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn (DressTylorQuote $record) => ! empty($record->quote_structure)),

                        Fieldset::make('Totales')
                            ->schema([
                                TextEntry::make('quote_structure.grand_total')
                                    ->label('Total cotización (US$)')
                                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, ',', '.') : '—')
                                    ->weight('bold')
                                    ->size('lg'),
                            ])
                            ->columnSpanFull()
                            ->visible(fn (DressTylorQuote $record) => ! empty($record->quote_structure)),

                        Fieldset::make('Metadatos del registro')
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->placeholder('—'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
