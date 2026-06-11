<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Schemas;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\OperationInventoryUbication;
use App\Models\Supplier;
use App\Models\TelemedicinePriority;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\OperationServiceOrderProviderFormFields;
use App\Support\Operations\OperationServiceOrderUnregisteredProviderFormFields;
use Filament\Forms\Components\CheckboxList;
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
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class ManageCoordinationServiceItemsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make(self::steps())
                    ->extraAttributes([
                        'class' => implode(' ', [
                            'fi-coordination-manage-items-wizard',
                            'fi-coordination-service-wizard',
                            'w-full',
                            'min-h-0',
                        ]),
                    ], merge: true)
                    ->nextAction(
                        fn (\Filament\Actions\Action $action): \Filament\Actions\Action => $action
                            ->label('Continuar')
                            ->icon(Heroicon::OutlinedArrowRight)
                            ->iconPosition(\Filament\Support\Enums\IconPosition::After)
                            ->extraAttributes([
                                'class' => \App\Support\Filament\FilamentIosButton::extraClassForFilamentColor('primary'),
                            ])
                    )
                    ->previousAction(
                        fn (\Filament\Actions\Action $action): \Filament\Actions\Action => $action
                            ->label('Anterior')
                            ->icon(Heroicon::OutlinedArrowLeft)
                            ->extraAttributes([
                                'class' => \App\Support\Filament\FilamentIosButton::extraClassForFilamentColor('gray'),
                            ])
                    ),
            ]);
    }

    /**
     * @return array<Step>
     */
    private static function steps(): array
    {
        return [Step::make('Selección de ítems')
            ->description('Revise cobertura y seleccione ítems')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->schema([
                Placeholder::make('manage_service_items_context')
                    ->hiddenLabel()
                    ->content(fn (ManageCoordinationServiceItems $livewire): HtmlString => CoordinationServiceItemsManager::manageServiceItemsContextHeader($livewire->getRecord()))
                    ->columnSpanFull(),
                Section::make('Inventario de ítems asociados')
                    ->description('Consulte el detalle completo antes de seleccionar qué ítems desea gestionar.')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->iconColor('gray')
                    ->schema([
                        Placeholder::make('manage_service_items_overview')
                            ->hiddenLabel()
                            ->content(fn (ManageCoordinationServiceItems $livewire): HtmlString => CoordinationServiceItemsManager::associatedServiceItemsOverviewTable($livewire->getRecord()))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Selección para gestión')
                    ->description('Marque uno o más ítems pendientes. Los ítems en gestión o finalizados aparecen deshabilitados.')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->iconColor('success')
                    ->visible(fn (ManageCoordinationServiceItems $livewire): bool => CoordinationServiceItemsManager::hasManageServiceItems($livewire->getRecord()))
                    ->schema([
                        CheckboxList::make('managed_service_item_keys')
                            ->label('Ítems disponibles')
                            ->options(fn (ManageCoordinationServiceItems $livewire): array => CoordinationServiceItemsManager::manageServiceItemOptions($livewire->getRecord()))
                            ->descriptions(fn (ManageCoordinationServiceItems $livewire): array => CoordinationServiceItemsManager::manageServiceItemDescriptions($livewire->getRecord()))
                            ->disableOptionWhen(fn (string $value, ManageCoordinationServiceItems $livewire): bool => ! CoordinationServiceItemsManager::isManagementItemKeySelectable($livewire->getRecord(), $value))
                            ->bulkToggleable()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (ManageCoordinationServiceItems $livewire, Get $get, Set $set, mixed $state): void {
                                $set(
                                    'manage_quote_line_items',
                                    CoordinationServiceItemsManager::buildManageQuoteLineItemsDefault(
                                        $livewire->getRecord(),
                                        $state,
                                        (array) ($get('manage_quote_line_items') ?? [])
                                    )
                                );
                                CoordinationServiceItemsManager::syncManageQuoteAggregates($get, $set);
                            })
                            ->columns(1)
                            ->required()
                            ->helperText('Use la búsqueda para filtrar por nombre. Puede seleccionar varios ítems a la vez.')
                            ->extraAttributes([
                                'class' => 'fi-manage-service-items-checkbox-list',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Placeholder::make('manage_service_items_empty')
                    ->label('Sin ítems pendientes')
                    ->content(fn (): HtmlString => CoordinationServiceItemsManager::manageServiceEmptyState())
                    ->visible(fn (ManageCoordinationServiceItems $livewire): bool => ! CoordinationServiceItemsManager::hasManageServiceItems($livewire->getRecord()))
                    ->columnSpanFull(),
                Section::make('Resumen de selección')
                    ->description('Vista previa en tiempo real de los ítems que gestionará al confirmar.')
                    ->icon(Heroicon::OutlinedEye)
                    ->iconColor('info')
                    ->visible(fn (ManageCoordinationServiceItems $livewire): bool => CoordinationServiceItemsManager::hasManageServiceItems($livewire->getRecord()))
                    ->schema([
                        Placeholder::make('manage_service_items_preview')
                            ->hiddenLabel()
                            ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceSelectedItemsTable(
                                $livewire->getRecord(),
                                $get('managed_service_item_keys')
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]),
            Step::make('Orden de servicio')
                ->description('Creación automática para ítems cubiertos')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::coveredSelectedManagementItemKeys(
                    $livewire->getRecord(),
                    $get('managed_service_item_keys')
                ) !== [])
                ->schema([
                    Placeholder::make('manage_service_order_context')
                        ->hiddenLabel()
                        ->content(fn (ManageCoordinationServiceItems $livewire): HtmlString => CoordinationServiceItemsManager::manageServiceItemsContextHeader($livewire->getRecord()))
                        ->columnSpanFull(),
                    Placeholder::make('manage_service_covered_items_notice')
                        ->hiddenLabel()
                        ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceCoveredItemsNotice($livewire->getRecord(), $get('managed_service_item_keys')))
                        ->columnSpanFull(),
                    Placeholder::make('manage_service_mixed_types_warning')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => CoordinationServiceItemsManager::manageServiceMixedTypesWarning())
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                            CoordinationServiceItemsManager::coveredSelectedManagementItemKeys($livewire->getRecord(), $get('managed_service_item_keys'))
                        ) === null && CoordinationServiceItemsManager::coveredSelectedManagementItemKeys($livewire->getRecord(), $get('managed_service_item_keys')) !== [])
                        ->columnSpanFull(),
                    Section::make('Datos de la orden de servicio')
                        ->description('Complete la información operativa. Solo se incluirán ítems con cobertura confirmada.')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->iconColor('primary')
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                            CoordinationServiceItemsManager::coveredSelectedManagementItemKeys($livewire->getRecord(), $get('managed_service_item_keys'))
                        ) !== null)
                        ->schema([
                            Placeholder::make('manage_service_order_type_badge')
                                ->label('Tipo de orden detectado')
                                ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceOrderTypeBadge(
                                    CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                                        CoordinationServiceItemsManager::coveredSelectedManagementItemKeys($livewire->getRecord(), $get('managed_service_item_keys'))
                                    )
                                )),
                            Section::make('Ítems incluidos en la orden')
                                ->icon(Heroicon::OutlinedShieldCheck)
                                ->iconColor('success')
                                ->schema([
                                    Placeholder::make('manage_service_covered_items_table')
                                        ->hiddenLabel()
                                        ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceCoveredItemsTable(
                                            $livewire->getRecord(),
                                            $get('managed_service_item_keys')
                                        ))
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                            Section::make('Historial de órdenes')
                                ->description('Órdenes recientes vinculadas a esta coordinación.')
                                ->icon(Heroicon::OutlinedClock)
                                ->iconColor('gray')
                                ->collapsed()
                                ->schema([
                                    Placeholder::make('manage_service_existing_orders')
                                        ->hiddenLabel()
                                        ->content(fn (ManageCoordinationServiceItems $livewire): HtmlString => CoordinationServiceItemsManager::existingServiceOrdersTable($livewire->getRecord()))
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                            Section::make('Información operativa')
                                ->icon(Heroicon::OutlinedBuildingStorefront)
                                ->iconColor('warning')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('order_number')
                                                ->label('Número de orden')
                                                ->required()
                                                ->prefixIcon(Heroicon::OutlinedHashtag)
                                                ->helperText('Se genera automáticamente; puede ajustarlo si su proceso lo requiere.')
                                                ->maxLength(255),
                                            Select::make('telemedicine_priority_id')
                                                ->label('Prioridad')
                                                ->options(TelemedicinePriority::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                                ->required()
                                                ->prefixIcon(Heroicon::OutlinedBolt)
                                                ->native(false),
                                            Select::make('operation_inventory_ubication_id')
                                                ->label('Ubicación inventario (medicamentos)')
                                                ->options(OperationInventoryUbication::query()->where('is_active', true)->orderBy('name', 'asc')->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->prefixIcon(Heroicon::OutlinedMapPin)
                                                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                                                    CoordinationServiceItemsManager::coveredSelectedManagementItemKeys($livewire->getRecord(), $get('managed_service_item_keys'))
                                                ) === 'MEDICAMENTOS')
                                                ->native(false)
                                                ->columnSpanFull(),
                                            TextInput::make('service_order_description')
                                                ->label('Descripción de la orden')
                                                ->required()
                                                ->prefixIcon(Heroicon::OutlinedDocumentText)
                                                ->maxLength(500)
                                                ->columnSpanFull(),
                                            Textarea::make('service_order_observations')
                                                ->label('Observaciones de la orden')
                                                ->rows(3)
                                                ->maxLength(2000)
                                                ->columnSpanFull(),
                                        ]),
                                    ...OperationServiceOrderProviderFormFields::components(),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),
                ]),
            Step::make('Proveedor no convenido')
                ->description('Registro del nuevo proveedor en el sistema')
                ->icon(Heroicon::OutlinedUserPlus)
                ->extraAttributes([
                    'class' => OperationServiceOrderUnregisteredProviderFormFields::WIZARD_STEP_CLASS,
                ])
                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::shouldShowUnregisteredProviderWizardStep($livewire->getRecord(), $get))
                ->schema(OperationServiceOrderUnregisteredProviderFormFields::wizardStepSchema()),
            Step::make('Cotización')
                ->description('Obligatoria para ítems no cubiertos')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys(
                    $livewire->getRecord(),
                    $get('managed_service_item_keys')
                ) !== [])
                ->schema([
                    Placeholder::make('manage_service_quote_context')
                        ->hiddenLabel()
                        ->content(fn (ManageCoordinationServiceItems $livewire): HtmlString => CoordinationServiceItemsManager::manageServiceItemsContextHeader($livewire->getRecord()))
                        ->columnSpanFull(),
                    Placeholder::make('manage_service_non_covered_items_notice')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => CoordinationServiceItemsManager::manageServiceNonCoveredItemsNotice())
                        ->columnSpanFull(),
                    Placeholder::make('manage_service_mixed_quote_types_warning')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => CoordinationServiceItemsManager::manageServiceMixedQuoteTypesWarning())
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                            CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys($livewire->getRecord(), $get('managed_service_item_keys'))
                        ) === null && CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys($livewire->getRecord(), $get('managed_service_item_keys')) !== [])
                        ->columnSpanFull(),
                    Section::make('Datos de la cotización')
                        ->description('Registre costos y utilidad para los ítems no cubiertos seleccionados.')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->iconColor('warning')
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                            CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys($livewire->getRecord(), $get('managed_service_item_keys'))
                        ) !== null)
                        ->schema([
                            Placeholder::make('manage_service_quote_type_badge')
                                ->label('Tipo de servicio detectado')
                                ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceOrderTypeBadge(
                                    CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                                        CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys($livewire->getRecord(), $get('managed_service_item_keys'))
                                    )
                                )),
                            Section::make('Ítems no cubiertos incluidos')
                                ->icon(Heroicon::OutlinedExclamationTriangle)
                                ->iconColor('danger')
                                ->schema([
                                    Placeholder::make('manage_service_non_covered_items_table')
                                        ->hiddenLabel()
                                        ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceNonCoveredItemsTable(
                                            $livewire->getRecord(),
                                            $get('managed_service_item_keys')
                                        ))
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                            Section::make('Parámetros de cotización')
                                ->description('Ingrese el precio unitario en USD de cada ítem. El sistema calcula bolívares con la tasa BCV y el total con la ganancia.')
                                ->icon(Heroicon::OutlinedCalculator)
                                ->iconColor('warning')
                                ->extraAttributes(['class' => 'fi-manage-quote-params-section'])
                                ->schema([
                                    Grid::make(1)
                                        ->schema([
                                            Select::make('manage_quote_supplier_id')
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
                                                        'manage_quote_supplier_address',
                                                        CoordinationServiceItemsManager::resolveManageQuoteSupplierAddress($state)
                                                    );
                                                })
                                                ->helperText('Seleccione el proveedor que cotiza los ítems no cubiertos.'),
                                            TextInput::make('manage_quote_supplier_address')
                                                ->label('Dirección del proveedor')
                                                ->readOnly()
                                                ->dehydrated()
                                                ->placeholder('Se completa al seleccionar el proveedor')
                                                ->prefixIcon(Heroicon::OutlinedMapPin),
                                        ])
                                        ->columnSpanFull(),
                                    TextInput::make('manage_quote_bcv_rate')
                                        ->label('Tasa BCV del día')
                                        ->prefix('Bs.')
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated()
                                        ->default(fn (): ?float => OperationCoordinationServicesTable::referenciaTasaBcvDesdeApi())
                                        ->helperText('Referencia automática desde API BCV.')
                                        ->extraAttributes(['class' => 'fi-manage-quote-readonly-field'])
                                        ->columnSpanFull(),
                                    Repeater::make('manage_quote_line_items')
                                        ->label('Precios unitarios por ítem')
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->columns(['default' => 1, 'md' => 3])
                                        ->afterStateHydrated(function (
                                            mixed $state,
                                            ManageCoordinationServiceItems $livewire,
                                            Get $get,
                                            Set $set
                                        ): void {
                                            CoordinationServiceItemsManager::ensureManageQuoteLineItemsPopulated($livewire->getRecord(), $get, $set, $state);
                                        })
                                        ->schema([
                                            Hidden::make('key')
                                                ->dehydrated(),
                                            Hidden::make('category')
                                                ->dehydrated(),
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
                                                    $rate = OperationCoordinationServicesTable::decimalOrNull($get('../../manage_quote_bcv_rate'));
                                                    $usd = OperationCoordinationServicesTable::decimalOrNull($state);
                                                    $set(
                                                        'unit_price_ves',
                                                        ($rate !== null && $usd !== null)
                                                            ? round($usd * $rate, 2)
                                                            : null
                                                    );
                                                    CoordinationServiceItemsManager::syncManageQuoteAggregates($get, $set);
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
                                                    Hidden::make('manage_quote_costo_dolares')
                                                        ->dehydrated(),
                                                    Hidden::make('manage_quote_costo_bolivares')
                                                        ->dehydrated(),
                                                    TextInput::make('manage_quote_porcentaje_ganancia')
                                                        ->label('Porcentaje de ganancia')
                                                        ->prefix('%')
                                                        ->numeric()
                                                        ->default(0)
                                                        ->minValue(0)
                                                        ->live(debounce: 400)
                                                        ->afterStateUpdated(fn (Get $get, Set $set): mixed => CoordinationServiceItemsManager::syncManageQuoteAggregates($get, $set))
                                                        ->helperText('Utilidad aplicada sobre la suma de precios unitarios en USD.'),
                                                ]),
                                            Placeholder::make('manage_quote_summary_panel')
                                                ->hiddenLabel()
                                                ->content(fn (Get $get): HtmlString => CoordinationServiceItemsManager::manageQuoteSummaryPanel($get))
                                                ->columnSpan(['lg' => 2]),
                                        ]),
                                    Textarea::make('manage_quote_observations')
                                        ->label('Observaciones de la cotización')
                                        ->placeholder('Indique notas relevantes para la gestión: condiciones del proveedor, plazos, requisitos del paciente, etc.')
                                        ->rows(4)
                                        ->maxLength(2000)
                                        ->helperText('Opcional. Esta información quedará registrada en la cotización y en el PDF generado.')
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),
                ]),

        ];
    }
}
