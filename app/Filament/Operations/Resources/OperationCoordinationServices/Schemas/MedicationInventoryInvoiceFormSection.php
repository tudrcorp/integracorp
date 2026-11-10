<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Schemas;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Models\OperationMedicationFailure;
use App\Support\Operations\ClinicalServiceCatalog;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\MedicationInventoryCatalog;
use App\Support\Operations\MedicationInvoiceIssuer;
use App\Support\Operations\MedicationInvoiceWizard;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Bloque «Medicamentos desde inventario» del paso Cotización de «Gestionar servicio».
 *
 * Una fila por medicamento NO CUBIERTO seleccionado: buscar en el inventario (categoría
 * Medicamentos) y elegir uno o varios productos con almacén y cantidad, o marcar
 * «Generar cotización» con su motivo. Las reglas viven en {@see MedicationInvoiceWizard}.
 */
final class MedicationInventoryInvoiceFormSection
{
    public static function make(): Section
    {
        $isMedication = fn (Get $get): bool => ($get('kind') ?? MedicationInvoiceWizard::KIND_MEDICATION) === MedicationInvoiceWizard::KIND_MEDICATION;

        return Section::make(fn (ManageCoordinationServiceItems $livewire, Get $get): string => MedicationInvoiceWizard::sectionTitle(MedicationInvoiceWizard::kind($livewire->getRecord(), $get('managed_service_item_keys'))))
            ->description(fn (ManageCoordinationServiceItems $livewire, Get $get): string => MedicationInvoiceWizard::isServiceKind(MedicationInvoiceWizard::kind($livewire->getRecord(), $get('managed_service_item_keys')))
                ? 'Cada ítem toma su precio del catálogo (costo + % de utilidad) y se factura al paciente. Queda EN GESTION con su orden al proveedor que lo realiza.'
                : 'Busque cada medicamento en el inventario Diagnomóvil y elija lo que se entrega. Lo elegido se factura al paciente.')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->iconColor('success')
            ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => MedicationInvoiceWizard::applies($livewire->getRecord(), $get('managed_service_item_keys')))
            ->schema([
                Repeater::make(MedicationInvoiceWizard::ROWS_FIELD)
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->collapsible()
                    ->columns(['default' => 1, 'md' => 12])
                    ->itemLabel(fn (array $state): string => trim((string) ($state['prescribed_name'] ?? 'Ítem')))
                    ->afterStateHydrated(function (ManageCoordinationServiceItems $livewire, Get $get, Set $set, mixed $state): void {
                        if (! is_array($state) || $state === []) {
                            CoordinationServiceItemsManager::syncMedicationInventoryState($livewire->getRecord(), $get, $set);
                        }
                    })
                    ->schema([
                        Hidden::make('key')->dehydrated(),
                        Hidden::make('kind')->dehydrated(),
                        Hidden::make('prescribed_name')->dehydrated(),
                        Hidden::make('prescribed_detail')->dehydrated(),
                        Placeholder::make('prescribed_info')
                            ->label('Recetado')
                            ->content(fn (Get $get): HtmlString => new HtmlString(
                                '<span class="font-semibold text-gray-950 dark:text-white">'.e((string) $get('prescribed_name')).'</span>'
                                .(filled($get('prescribed_detail')) ? '<span class="block text-sm text-gray-500 dark:text-gray-400">'.e((string) $get('prescribed_detail')).'</span>' : '')
                            ))
                            ->columnSpanFull(),
                        Checkbox::make('generate_quote')
                            ->label('Generar cotización')
                            ->helperText(fn (Get $get): string => $isMedication($get)
                                ? 'Márquelo si el medicamento no está en el inventario, lo que aparece no corresponde o no alcanza. Queda registrado en Fallas de medicamentos y pasa a la cotización.'
                                : 'Márquelo si este ítem no se factura al paciente desde el catálogo y debe cotizarse con un proveedor. Sale de la factura y de la orden.')
                            ->live()
                            ->afterStateUpdated(function (ManageCoordinationServiceItems $livewire, Get $get, Set $set): void {
                                CoordinationServiceItemsManager::syncMedicationInventoryState($livewire->getRecord(), $get, $set, '../../', rebuildRows: false);
                            })
                            ->columnSpanFull(),
                        Radio::make('failure_reason')
                            ->label('¿Por qué no se entrega desde el inventario?')
                            ->options(OperationMedicationFailure::reasonOptions())
                            ->required(fn (Get $get): bool => (bool) $get('generate_quote') && $isMedication($get))
                            ->visible(fn (Get $get): bool => (bool) $get('generate_quote') && $isMedication($get))
                            ->validationMessages(['required' => 'Indique el motivo: se registra en Fallas de medicamentos.'])
                            ->columnSpanFull(),
                        Textarea::make('failure_notes')
                            ->label('Nota para compras (opcional)')
                            ->placeholder('Ej.: presentación requerida, cantidad, laboratorio preferido…')
                            ->rows(2)
                            ->maxLength(2000)
                            ->visible(fn (Get $get): bool => (bool) $get('generate_quote') && $isMedication($get))
                            ->columnSpanFull(),
                        ...self::serviceFields($isMedication),
                        TextInput::make('search_term')
                            ->label('Buscar en el inventario')
                            ->prefixIcon(Heroicon::OutlinedMagnifyingGlass)
                            ->placeholder('Nombre comercial, principio activo o código')
                            ->helperText('Arranca con el nombre recetado. Ajústelo si el inventario lo nombra distinto (por ejemplo, solo la marca).')
                            ->maxLength(120)
                            ->live(debounce: 500)
                            ->visible(fn (Get $get): bool => ! (bool) $get('generate_quote') && $isMedication($get))
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Placeholder::make('search_results_hint')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => ! (bool) $get('generate_quote') && $isMedication($get)
                                && MedicationInventoryCatalog::search((string) $get('search_term'))->isEmpty())
                            ->content(new HtmlString('<div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">'
                                .'No hay medicamentos con ese nombre en el inventario. Pruebe con otro término o marque «Generar cotización».</div>'))
                            ->columnSpanFull(),
                        Repeater::make('products')
                            ->label('Productos a entregar')
                            ->visible(fn (Get $get): bool => ! (bool) $get('generate_quote') && $isMedication($get))
                            ->minItems(1)
                            ->maxItems(MedicationInvoiceIssuer::MAX_PRODUCTS_PER_MEDICATION)
                            ->addActionLabel('Agregar otro producto')
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->columns(['default' => 1, 'md' => 12])
                            ->validationMessages(['min' => 'Elija al menos un producto o marque «Generar cotización».'])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto del inventario')
                                    ->native(false)
                                    ->searchable()
                                    ->searchDebounce(400)
                                    ->options(fn (Get $get): array => MedicationInvoiceWizard::productOptions(
                                        (string) ($get('../../search_term') ?: $get('../../prescribed_name')),
                                        $get('product_id'),
                                    ))
                                    ->getSearchResultsUsing(fn (string $search): array => MedicationInvoiceWizard::productOptions($search))
                                    ->getOptionLabelUsing(fn (mixed $value): ?string => MedicationInvoiceWizard::productOptions(null, $value)[(int) $value] ?? null)
                                    ->disableOptionWhen(fn (string $label): bool => MedicationInventoryCatalog::labelIsUnavailable($label))
                                    ->noSearchResultsMessage('No hay medicamentos con ese nombre en el inventario. Marque «Generar cotización».')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                        $set('ubication_id', MedicationInvoiceWizard::singleUbicationId($state));
                                        $set('new_cost', null);
                                    })
                                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                        if (filled($value) && ! MedicationInvoiceWizard::productIsSelectable($value)) {
                                            $fail('Este producto no puede facturarse: no tiene existencia o su categoría no tiene % de utilidad.');
                                        }
                                    })
                                    ->validationMessages(['required' => 'Elija el producto del inventario.'])
                                    ->columnSpan(['md' => 6]),
                                Select::make('ubication_id')
                                    ->label('Almacén')
                                    ->native(false)
                                    ->options(fn (Get $get): array => MedicationInvoiceWizard::ubicationOptions($get('product_id')))
                                    ->disabled(fn (Get $get): bool => ! filled($get('product_id')))
                                    ->required()
                                    ->live()
                                    ->validationMessages(['required' => 'Elija el almacén del que sale.'])
                                    ->columnSpan(['md' => 4]),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(fn (Get $get): int => max(1, MedicationInvoiceWizard::availableAt($get('product_id'), $get('ubication_id'))))
                                    ->default(1)
                                    ->required()
                                    ->live(debounce: 400)
                                    ->validationMessages([
                                        'max' => 'Supera la existencia disponible en ese almacén.',
                                        'min' => 'La cantidad mínima es 1.',
                                    ])
                                    ->columnSpan(['md' => 2]),
                                TextInput::make('new_cost')
                                    ->label('Costo del producto (US$)')
                                    ->prefix('US$')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(MedicationInvoiceIssuer::MAX_LOADED_COST)
                                    ->step(0.01)
                                    ->visible(fn (Get $get): bool => filled($get('product_id')) && MedicationInvoiceWizard::productNeedsCost($get('product_id')))
                                    ->required(fn (Get $get): bool => filled($get('product_id')) && MedicationInvoiceWizard::productNeedsCost($get('product_id')))
                                    ->live(debounce: 500)
                                    ->helperText(fn (Get $get): string => 'Este producto no tiene costo. Al confirmar se guarda en el producto y el precio se calcula con el '
                                        .number_format((float) MedicationInvoiceWizard::categoryPercentageFor($get('product_id')), 2, ',', '.').' % de la categoría.')
                                    ->validationMessages([
                                        'required' => 'Cargue el costo del producto para poder facturarlo.',
                                        'min' => 'El costo debe ser mayor que cero.',
                                        'max' => 'Revise el costo: parece demasiado alto.',
                                    ])
                                    ->columnSpanFull(),
                                Placeholder::make('line_summary')
                                    ->hiddenLabel()
                                    ->content(fn (Get $get): HtmlString => MedicationInvoiceWizard::lineSummary($get('product_id'), $get('quantity'), $get('new_cost')))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Placeholder::make('manage_medication_invoice_summary')
                    ->hiddenLabel()
                    ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => MedicationInvoiceWizard::invoiceSummary(
                        $livewire->getRecord(),
                        $get('managed_service_item_keys'),
                        $get(MedicationInvoiceWizard::ROWS_FIELD),
                    ))
                    ->columnSpanFull(),
            ])
            ->columnSpanFull();
    }

    /**
     * Campos de un laboratorio, estudio o especialista: la fila del catálogo (por defecto la del
     * mismo nombre) y, si le falta, el costo o el % que se guardan en el catálogo al facturar.
     *
     * @param  Closure(Get): bool  $isMedication
     * @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private static function serviceFields(Closure $isMedication): array
    {
        $isServiceLine = fn (Get $get): bool => ! (bool) $get('generate_quote') && ! $isMedication($get);
        $entry = fn (Get $get) => ClinicalServiceCatalog::find((string) $get('kind'), $get('catalog_id'));

        return [
            Select::make('catalog_id')
                ->label('Ítem del catálogo')
                ->native(false)
                ->searchable()
                ->options(fn (Get $get): array => ClinicalServiceCatalog::options((string) $get('kind')))
                ->helperText('Se propone el del mismo nombre que lo recetado. Puede elegir otro si corresponde.')
                ->visible($isServiceLine)
                ->required($isServiceLine)
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('new_cost', null);
                    $set('new_percentage', null);
                })
                ->validationMessages(['required' => 'Elija el ítem del catálogo o marque «Generar cotización».'])
                ->columnSpanFull(),
            TextInput::make('new_cost')
                ->label('Costo (US$)')
                ->prefix('US$')
                ->numeric()
                ->minValue(0.01)
                ->maxValue(MedicationInvoiceIssuer::MAX_LOADED_COST)
                ->step(0.01)
                ->visible(fn (Get $get): bool => $isServiceLine($get) && $entry($get) !== null && ClinicalServiceCatalog::needsCost($entry($get)))
                ->required(fn (Get $get): bool => $isServiceLine($get) && $entry($get) !== null && ClinicalServiceCatalog::needsCost($entry($get)))
                ->live(debounce: 500)
                ->helperText('Este ítem no tiene costo en el catálogo. Al confirmar, queda guardado en él.')
                ->validationMessages(['required' => 'Cargue el costo para poder facturarlo.', 'min' => 'El costo debe ser mayor que cero.'])
                ->columnSpan(['md' => 6]),
            TextInput::make('new_percentage')
                ->label('% de utilidad')
                ->suffix('%')
                ->numeric()
                ->minValue(0)
                ->maxValue(ClinicalServiceCatalog::MAX_PROFIT_PERCENTAGE)
                ->step(0.01)
                ->visible(fn (Get $get): bool => $isServiceLine($get) && $entry($get) !== null && ClinicalServiceCatalog::needsPercentage($entry($get)))
                ->required(fn (Get $get): bool => $isServiceLine($get) && $entry($get) !== null && ClinicalServiceCatalog::needsPercentage($entry($get)))
                ->live(debounce: 500)
                ->helperText('Este ítem no tiene % de utilidad en el catálogo. Al confirmar, queda guardado en él.')
                ->validationMessages(['required' => 'Cargue el % de utilidad para poder facturarlo.'])
                ->columnSpan(['md' => 6]),
            Placeholder::make('service_line_summary')
                ->hiddenLabel()
                ->visible($isServiceLine)
                ->content(fn (Get $get): HtmlString => MedicationInvoiceWizard::serviceLineSummary($get('kind'), $get('catalog_id'), $get('new_cost'), $get('new_percentage')))
                ->columnSpanFull(),
        ];
    }
}
