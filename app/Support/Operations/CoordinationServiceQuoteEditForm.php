<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\OperationQuoteGenerator;
use App\Models\Supplier;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class CoordinationServiceQuoteEditForm
{
    /**
     * @return array<int, mixed>
     */
    public static function schema(): array
    {
        return [
            Section::make('Parámetros de cotización')
                ->description('Ajuste proveedor, precios y observaciones. Los cambios se reflejan en el PDF al guardar.')
                ->icon(Heroicon::OutlinedCalculator)
                ->iconColor('warning')
                ->schema([
                    Grid::make(1)
                        ->schema([
                            Select::make('edit_quote_supplier_id')
                                ->label('Proveedor')
                                ->options(fn (): array => Supplier::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->native(false)
                                ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    $set(
                                        'edit_quote_supplier_address',
                                        CoordinationServiceItemsManager::resolveManageQuoteSupplierAddress($state)
                                    );
                                }),
                            TextInput::make('edit_quote_supplier_address')
                                ->label('Dirección del proveedor')
                                ->readOnly()
                                ->dehydrated()
                                ->placeholder('Se completa al seleccionar el proveedor')
                                ->prefixIcon(Heroicon::OutlinedMapPin),
                        ])
                        ->columnSpanFull(),
                    TextInput::make('edit_quote_bcv_rate')
                        ->label('Tasa BCV del día')
                        ->prefix('Bs.')
                        ->numeric()
                        ->readOnly()
                        ->dehydrated()
                        ->helperText('Referencia usada al crear la cotización.')
                        ->extraAttributes(['class' => 'fi-manage-quote-readonly-field'])
                        ->columnSpanFull(),
                    Repeater::make('edit_quote_line_items')
                        ->label('Precios unitarios por ítem')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            Hidden::make('key')->dehydrated(),
                            Hidden::make('category')->dehydrated(),
                            TextInput::make('label')
                                ->label('Ítem')
                                ->disabled()
                                ->dehydrated(),
                            TextInput::make('unit_price_usd')
                                ->label('Precio unitario (USD)')
                                ->prefix('US$')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->live(debounce: 400)
                                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                    $rate = OperationCoordinationServicesTable::decimalOrNull($get('../../edit_quote_bcv_rate'));
                                    $usd = OperationCoordinationServicesTable::decimalOrNull($state);
                                    $set(
                                        'unit_price_ves',
                                        ($rate !== null && $usd !== null)
                                            ? round($usd * $rate, 2)
                                            : null
                                    );
                                    self::syncEditQuoteAggregates($get, $set);
                                }),
                            TextInput::make('unit_price_ves')
                                ->label('Equivalente (Bs.)')
                                ->prefix('Bs.')
                                ->numeric()
                                ->readOnly()
                                ->dehydrated()
                                ->extraAttributes(['class' => 'fi-manage-quote-readonly-field']),
                        ])
                        ->columnSpanFull(),
                    Grid::make(['default' => 1, 'lg' => 5])
                        ->schema([
                            Grid::make(1)
                                ->columnSpan(['lg' => 3])
                                ->schema([
                                    Grid::make(['default' => 1, 'md' => 2])
                                        ->schema([
                                            TextInput::make('edit_quote_costo_dolares')
                                                ->label('Costo base (USD)')
                                                ->prefix('US$')
                                                ->numeric()
                                                ->required()
                                                ->minValue(0.01)
                                                ->live(debounce: 400)
                                                ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::syncEditQuoteBaseCostBolivares($get, $set))
                                                ->helperText('Se calcula automáticamente al cambiar precios unitarios. Puede ajustarlo manualmente.'),
                                            TextInput::make('edit_quote_costo_bolivares')
                                                ->label('Costo base (Bs.)')
                                                ->prefix('Bs.')
                                                ->numeric()
                                                ->readOnly()
                                                ->dehydrated()
                                                ->extraAttributes(['class' => 'fi-manage-quote-readonly-field']),
                                        ]),
                                    TextInput::make('edit_quote_porcentaje_ganancia')
                                        ->label('Porcentaje de ganancia')
                                        ->prefix('%')
                                        ->numeric()
                                        ->minValue(0)
                                        ->live(debounce: 400)
                                        ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::syncEditQuoteAggregates($get, $set))
                                        ->helperText('Utilidad aplicada sobre el costo base en USD.'),
                                ]),
                            Placeholder::make('edit_quote_summary_panel')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => self::summaryPanel($get))
                                ->columnSpan(['lg' => 2]),
                        ]),
                    Textarea::make('edit_quote_observations')
                        ->label('Observaciones de la cotización')
                        ->rows(4)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(OperationQuoteGenerator $quote): array
    {
        $items = is_array($quote->items) ? $quote->items : [];

        return [
            'edit_quote_bcv_rate' => CoordinationServiceQuoteManager::resolveBcvRateFromQuote($quote),
            'edit_quote_supplier_id' => $quote->supplier_id,
            'edit_quote_supplier_address' => $quote->supplier_address
                ?? CoordinationServiceItemsManager::resolveManageQuoteSupplierAddress($quote->supplier_id),
            'edit_quote_line_items' => collect($items)
                ->map(fn (array $item): array => [
                    'key' => $item['key'] ?? null,
                    'category' => $item['category'] ?? null,
                    'label' => $item['label'] ?? '—',
                    'unit_price_usd' => OperationCoordinationServicesTable::decimalOrNull($item['unit_price_usd'] ?? null),
                    'unit_price_ves' => OperationCoordinationServicesTable::decimalOrNull($item['unit_price_ves'] ?? null),
                ])
                ->values()
                ->all(),
            'edit_quote_costo_dolares' => (float) ($quote->costo_dolares ?? $quote->subtotal ?? 0),
            'edit_quote_costo_bolivares' => self::resolveEditQuoteBaseCostBolivares($quote),
            'edit_quote_porcentaje_ganancia' => (float) ($quote->porcentaje_ganancia ?? 0),
            'edit_quote_observations' => $quote->observations,
        ];
    }

    public static function syncEditQuoteAggregates(Get $get, Set $set): void
    {
        $lineItems = is_array($get('edit_quote_line_items')) ? $get('edit_quote_line_items') : [];
        $subtotal = CoordinationServiceItemsManager::manageQuoteSubtotalFromLineItems($lineItems);

        $set('edit_quote_costo_dolares', $subtotal);
        self::syncEditQuoteBaseCostBolivares($get, $set);
    }

    public static function syncEditQuoteBaseCostBolivares(Get $get, Set $set): void
    {
        $costo = OperationCoordinationServicesTable::decimalOrNull($get('edit_quote_costo_dolares'));
        $rate = OperationCoordinationServicesTable::decimalOrNull($get('edit_quote_bcv_rate'));

        $set(
            'edit_quote_costo_bolivares',
            ($costo !== null && $rate !== null) ? round($costo * $rate, 2) : null
        );
    }

    public static function resolveEditQuoteBaseCostBolivares(OperationQuoteGenerator $quote): ?float
    {
        $costo = OperationCoordinationServicesTable::decimalOrNull($quote->costo_dolares ?? $quote->subtotal);
        $rate = CoordinationServiceQuoteManager::resolveBcvRateFromQuote($quote);

        if ($costo === null || $rate === null) {
            return null;
        }

        return round($costo * $rate, 2);
    }

    public static function summaryPanel(Get $get): HtmlString
    {
        $subtotal = OperationCoordinationServicesTable::decimalOrNull($get('edit_quote_costo_dolares'))
            ?? CoordinationServiceItemsManager::manageQuoteSubtotalFromLineItems(
                is_array($get('edit_quote_line_items')) ? $get('edit_quote_line_items') : []
            );
        $porcentaje = OperationCoordinationServicesTable::decimalOrNull($get('edit_quote_porcentaje_ganancia')) ?? 0.0;
        $total = CoordinationServiceItemsManager::manageQuoteTotal($subtotal, $porcentaje);
        $bcvRate = OperationCoordinationServicesTable::decimalOrNull($get('edit_quote_bcv_rate'));
        $ganancia = ($subtotal !== null && $total !== null) ? round($total - $subtotal, 2) : null;
        $totalBs = ($total !== null && $bcvRate !== null) ? round($total * $bcvRate, 2) : null;

        $rows = [
            [
                'label' => 'Costo base',
                'value' => CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($subtotal),
                'tone' => 'slate',
            ],
            [
                'label' => 'Ganancia ('.number_format($porcentaje, 2, '.', '').'%)',
                'value' => CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($ganancia),
                'tone' => 'amber',
            ],
        ];

        $html = '<div class="fi-manage-quote-summary h-full rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/95 via-white to-white p-4 shadow-sm dark:border-amber-500/25 dark:from-amber-950/30 dark:via-zinc-900/95 dark:to-zinc-900/90">'
            .'<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-800/80 dark:text-amber-200/70">Resumen de cotización</p>'
            .'<div class="mt-3 space-y-2.5">';

        foreach ($rows as $row) {
            $html .= CoordinationServiceQuoteManager::manageQuoteSummaryRow($row['label'], $row['value'], $row['tone']);
        }

        $html .= '</div>'
            .'<div class="mt-4 rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/95 to-white px-4 py-3.5 dark:border-emerald-500/30 dark:from-emerald-950/35 dark:to-zinc-900/90">'
            .'<p class="text-xs font-medium text-emerald-800/80 dark:text-emerald-200/75">Total cotización</p>'
            .'<p class="mt-1 text-2xl font-bold tracking-tight text-emerald-950 dark:text-emerald-50">'.e(CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($total)).'</p>'
            .'<p class="mt-1 text-sm font-medium text-emerald-800/70 dark:text-emerald-200/65">'.e(CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($totalBs, 'VES')).'</p>'
            .'</div>'
            .'</div>';

        return new HtmlString($html);
    }
}
