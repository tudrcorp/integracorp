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
                ->description('Ajuste proveedor, precios, % de utilidad por ítem, factura y observaciones. Los cambios se reflejan en el PDF al guardar.')
                ->icon(Heroicon::OutlinedCalculator)
                ->iconColor('warning')
                ->schema([
                    Grid::make(1)
                        ->schema([
                            ManageQuoteSupplierCreator::configureSelect(
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
                                        $set(
                                            'edit_quote_supplier_phone',
                                            CoordinationServiceItemsManager::resolveManageQuoteSupplierPhone($state)
                                        );
                                    })
                                    ->helperText('Seleccione el proveedor. Si no aparece en la lista, créelo con la acción del campo.'),
                            ),
                            Grid::make(['default' => 1, 'md' => 2])
                                ->schema([
                                    TextInput::make('edit_quote_supplier_address')
                                        ->label('Dirección del proveedor')
                                        ->readOnly()
                                        ->dehydrated()
                                        ->placeholder('Se completa al seleccionar el proveedor')
                                        ->prefixIcon(Heroicon::OutlinedMapPin),
                                    TextInput::make('edit_quote_supplier_phone')
                                        ->label('Teléfono del proveedor')
                                        ->tel()
                                        ->readOnly()
                                        ->dehydrated()
                                        ->placeholder('Se completa al seleccionar el proveedor')
                                        ->prefixIcon(Heroicon::OutlinedPhone),
                                ]),
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
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                        ->schema([
                            Hidden::make('key')->dehydrated(),
                            Hidden::make('category')->dehydrated(),
                            TextInput::make('label')
                                ->label('Ítem')
                                ->disabled()
                                ->dehydrated(),
                            TextInput::make('unit_price_usd')
                                ->label('Precio del proveedor (USD)')
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
                                }),
                            QuoteLineProfitField::make(),
                            TextInput::make('unit_price_ves')
                                ->label('Precio del proveedor (Bs.)')
                                ->prefix('Bs.')
                                ->numeric()
                                ->readOnly()
                                ->dehydrated()
                                ->extraAttributes(['class' => 'fi-manage-quote-readonly-field']),
                        ])
                        ->columnSpanFull(),
                    Hidden::make('edit_quote_is_courtesy')->dehydrated(false),
                    Placeholder::make('edit_quote_summary_panel')
                        ->hiddenLabel()
                        ->content(fn (Get $get): HtmlString => self::summaryPanel($get))
                        ->columnSpanFull(),
                    QuoteInvoiceFormFields::make(
                        'edit_quote_',
                        fn (Get $get): bool => ! (bool) $get('edit_quote_is_courtesy')
                            && QuoteInvoiceFormFields::lineItemsAreInvoiceable(is_array($get('edit_quote_line_items')) ? $get('edit_quote_line_items') : []),
                    ),
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
        $usesItemPricing = QuoteLinePricing::usesItemPricing($items);
        $legacyPercentage = (float) ($quote->porcentaje_ganancia ?? 0) > 0 ? $quote->porcentaje_ganancia : null;

        return [
            'edit_quote_bcv_rate' => CoordinationServiceQuoteManager::resolveBcvRateFromQuote($quote),
            'edit_quote_supplier_id' => $quote->supplier_id,
            'edit_quote_supplier_address' => $quote->supplier_address
                ?? CoordinationServiceItemsManager::resolveManageQuoteSupplierAddress($quote->supplier_id),
            'edit_quote_supplier_phone' => CoordinationServiceItemsManager::resolveManageQuoteSupplierPhone($quote->supplier_id),
            'edit_quote_line_items' => collect($items)
                ->map(fn (array $item): array => [
                    'key' => $item['key'] ?? null,
                    'category' => $item['category'] ?? null,
                    'label' => $item['label'] ?? '—',
                    'unit_price_usd' => OperationCoordinationServicesTable::decimalOrNull($item['unit_price_usd'] ?? null),
                    'unit_price_ves' => OperationCoordinationServicesTable::decimalOrNull($item['unit_price_ves'] ?? null),
                    // Una cotización anterior al % por ítem aplicaba un % global: se reparte igual en
                    // cada ítem, así el total no cambia al abrirla.
                    'profit_percentage' => $usesItemPricing
                        ? QuoteLinePricing::percentage($item['profit_percentage'] ?? null)
                        : QuoteLinePricing::percentage($legacyPercentage),
                ])
                ->values()
                ->all(),
            'edit_quote_is_courtesy' => (bool) $quote->is_courtesy,
            'edit_quote_generates_invoice' => (bool) $quote->generates_invoice,
            'edit_quote_invoice_payer_type' => $quote->invoice_payer_type,
            'edit_quote_invoice_payer_address' => $quote->invoice_payer_address,
            'edit_quote_observations' => $quote->observations,
        ];
    }

    public static function summaryPanel(Get $get): HtmlString
    {
        return QuoteSummaryPanel::render(
            is_array($get('edit_quote_line_items')) ? $get('edit_quote_line_items') : [],
            OperationCoordinationServicesTable::decimalOrNull($get('edit_quote_bcv_rate')),
        );
    }
}
