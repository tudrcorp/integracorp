<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Schemas;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\OperationInventoryUbication;
use App\Models\Supplier;
use App\Models\TelemedicinePriority;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\ManageQuoteSupplierCreator;
use App\Support\Operations\MedicalAppointmentManager;
use App\Support\Operations\MedicationInvoiceWizard;
use App\Support\Operations\OperationServiceOrderCoveredPricingFormFields;
use App\Support\Operations\OperationServiceOrderProviderContacts;
use App\Support\Operations\OperationServiceOrderProviderFormFields;
use App\Support\Operations\OperationServiceOrderUnregisteredProviderFormFields;
use App\Support\Operations\QuoteInvoiceFormFields;
use App\Support\Operations\QuoteLineProfitField;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
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
    public const QUOTE_STEP_LABEL = 'Cotización';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make(self::steps())
                    ->startOnStep(fn (Wizard $component, ManageCoordinationServiceItems $livewire): int => $livewire->startsOnQuoteStep
                        ? self::visibleQuoteStepNumber($component)
                        : 1)
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
     * Posición (desde 1) del paso «Cotización» entre los pasos visibles: el
     * navegador cuenta solo los visibles. Si no está visible, arranca en el primero.
     */
    public static function visibleQuoteStepNumber(Wizard $wizard): int
    {
        foreach (array_values($wizard->getChildSchema()->getComponents()) as $index => $step) {
            if ($step instanceof Step && $step->getLabel() === self::QUOTE_STEP_LABEL) {
                return $index + 1;
            }
        }

        return 1;
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
                            ->afterStateUpdated(function (ManageCoordinationServiceItems $livewire, Get $get, Set $set): void {
                                CoordinationServiceItemsManager::syncMedicationInventoryState($livewire->getRecord(), $get, $set);
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
                ->description(fn (ManageCoordinationServiceItems $livewire, Get $get): string => CoordinationServiceItemsManager::manageQuoteInventoryInvoiceKeys($livewire->getRecord(), $get) !== [] && MedicationInvoiceWizard::isServiceKind(MedicationInvoiceWizard::kind($livewire->getRecord(), $get('managed_service_item_keys')))
                    ? 'Para ítems cubiertos y los no cubiertos que se facturan al paciente'
                    : 'Creación automática para ítems cubiertos')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get) !== [])
                ->afterValidation(function (ManageCoordinationServiceItems $livewire, Get $get): void {
                    // Lo facturado al paciente necesita su orden: sin proveedor no se puede seguir.
                    $message = CoordinationServiceItemsManager::invoicedServiceOrderProviderError($livewire->getRecord(), $get);

                    if ($message !== null) {
                        \Filament\Notifications\Notification::make()
                            ->title('Falta el proveedor de la orden')
                            ->body($message)
                            ->warning()
                            ->send();

                        throw new \Filament\Support\Exceptions\Halt;
                    }
                })
                ->schema([
                    Placeholder::make('manage_service_order_context')
                        ->hiddenLabel()
                        ->content(fn (ManageCoordinationServiceItems $livewire): HtmlString => CoordinationServiceItemsManager::manageServiceItemsContextHeader($livewire->getRecord()))
                        ->columnSpanFull(),
                    Placeholder::make('manage_service_covered_items_notice')
                        ->hiddenLabel()
                        ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceOrderStepNotice($livewire->getRecord(), $get))
                        ->columnSpanFull(),
                    Placeholder::make('manage_service_mixed_types_warning')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => CoordinationServiceItemsManager::manageServiceMixedTypesWarning())
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                            CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get)
                        ) === null && CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get) !== [])
                        ->columnSpanFull(),
                    Section::make('Datos de la orden de servicio')
                        ->description('Complete la información operativa. Solo se incluirán ítems con cobertura confirmada.')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->iconColor('primary')
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                            CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get)
                        ) !== null)
                        ->schema([
                            Placeholder::make('manage_service_order_type_badge')
                                ->label('Tipo de orden detectado')
                                ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceOrderTypeBadge(
                                    CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                                        CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get)
                                    )
                                )),
                            Section::make('Ítems incluidos en la orden')
                                ->icon(Heroicon::OutlinedShieldCheck)
                                ->iconColor('success')
                                ->schema([
                                    Placeholder::make('manage_service_covered_items_table')
                                        ->hiddenLabel()
                                        ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceSelectedItemsTable(
                                            $livewire->getRecord(),
                                            CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get)
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
                                    ...OperationServiceOrderProviderFormFields::components(
                                        function (ManageCoordinationServiceItems $livewire, Get $get, Set $set): void {
                                            CoordinationServiceItemsManager::rebuildManageQuoteLineItems($livewire->getRecord(), $get, $set);
                                        }
                                    ),
                                    Grid::make(2)
                                        ->schema([
                                            Grid::make(['default' => 1, 'md' => 3])
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
                                                    DateTimePicker::make('appointment_at')
                                                        ->label('Fecha y hora de la cita')
                                                        ->seconds(false)
                                                        ->native(false)
                                                        ->required(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => MedicalAppointmentManager::serviceTypeRequiresAppointment(
                                                            CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                                                                CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get)
                                                            )
                                                        ))
                                                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => MedicalAppointmentManager::serviceTypeRequiresAppointment(
                                                            CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                                                                CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get)
                                                            )
                                                        ))
                                                        ->helperText('Obligatoria para servicios con asistencia presencial del paciente.')
                                                        ->prefixIcon(Heroicon::OutlinedCalendarDays),
                                                ])
                                                ->columnSpanFull(),
                                            TextInput::make('supplier_notify_email')
                                                ->label('Correo del proveedor (cita)')
                                                ->email()
                                                ->maxLength(255)
                                                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => OperationServiceOrderProviderContacts::hasCatalogSelection($get)
                                                    && MedicalAppointmentManager::serviceTypeRequiresAppointment(
                                                        CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                                                            CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get)
                                                        )
                                                    )
                                                    && OperationServiceOrderProviderContacts::missingNotifyContacts($get)['email'])
                                                ->helperText('La ficha del proveedor no tiene correo; indíquelo aquí para avisarle de la cita.'),
                                            TextInput::make('supplier_notify_phone')
                                                ->label('Teléfono WhatsApp del proveedor (cita)')
                                                ->tel()
                                                ->maxLength(30)
                                                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => OperationServiceOrderProviderContacts::hasCatalogSelection($get)
                                                    && MedicalAppointmentManager::serviceTypeRequiresAppointment(
                                                        CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                                                            CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get)
                                                        )
                                                    )
                                                    && OperationServiceOrderProviderContacts::missingNotifyContacts($get)['phone'])
                                                ->helperText('La ficha del proveedor no tiene teléfono; indíquelo aquí para avisarle de la cita.'),
                                            TextInput::make('supplier_notify_address')
                                                ->label('Dirección del proveedor')
                                                ->readOnly()
                                                ->dehydrated(false)
                                                ->visible(fn (Get $get): bool => OperationServiceOrderProviderContacts::hasCatalogSelection($get))
                                                ->helperText('Se toma de la ficha del proveedor. Si falta, solicítelo al equipo de Proveedores.')
                                                ->prefixIcon(Heroicon::OutlinedMapPin)
                                                ->columnSpanFull(),
                                            Select::make('operation_inventory_ubication_id')
                                                ->label('Ubicación inventario (medicamentos)')
                                                ->options(OperationInventoryUbication::query()->where('is_active', true)->orderBy('name', 'asc')->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->prefixIcon(Heroicon::OutlinedMapPin)
                                                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys(
                                                    CoordinationServiceItemsManager::serviceOrderStepKeys($livewire->getRecord(), $get)
                                                ) === 'MEDICAMENTOS')
                                                ->native(false)
                                                ->columnSpanFull(),
                                            Textarea::make('service_order_observations')
                                                ->label('Observaciones de la orden')
                                                ->rows(3)
                                                ->maxLength(2000)
                                                ->columnSpanFull(),
                                        ]),
                                    ...OperationServiceOrderCoveredPricingFormFields::components(),
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
                ->schema(OperationServiceOrderUnregisteredProviderFormFields::wizardStepSchema(
                    function (ManageCoordinationServiceItems $livewire, Get $get, Set $set): void {
                        CoordinationServiceItemsManager::rebuildManageQuoteLineItems($livewire->getRecord(), $get, $set);
                    }
                )),
            Step::make('Cotización')
                ->description(fn (ManageCoordinationServiceItems $livewire, Get $get): string => MedicationInvoiceWizard::applies($livewire->getRecord(), $get('managed_service_item_keys'))
                    ? 'Factura desde inventario o cotización de los medicamentos no cubiertos'
                    : 'Obligatoria para ítems no cubiertos y cubiertos con proveedor no convenido')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::shouldShowManageQuoteStep(
                    $livewire->getRecord(),
                    $get
                ))
                ->schema([
                    Placeholder::make('manage_service_quote_context')
                        ->hiddenLabel()
                        ->content(fn (ManageCoordinationServiceItems $livewire): HtmlString => CoordinationServiceItemsManager::manageServiceItemsContextHeader($livewire->getRecord()))
                        ->columnSpanFull(),
                    Placeholder::make('manage_service_non_covered_items_notice')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => CoordinationServiceItemsManager::manageServiceNonCoveredItemsNotice())
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => ! MedicationInvoiceWizard::applies($livewire->getRecord(), $get('managed_service_item_keys')))
                        ->columnSpanFull(),
                    Placeholder::make('manage_medication_inventory_notice')
                        ->hiddenLabel()
                        ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => MedicationInvoiceWizard::isServiceKind(MedicationInvoiceWizard::kind($livewire->getRecord(), $get('managed_service_item_keys')))
                            ? new HtmlString('<div class="fi-manage-service-notice fi-manage-service-notice--amber px-5">'
                                .'<p class="font-semibold">No cubierto: se puede facturar al paciente</p>'
                                .'<p class="mt-1 leading-relaxed opacity-90">Cada ítem toma su precio del catálogo y se factura al paciente; la orden al proveedor que lo realiza se arma en el paso «Orden de servicio». Si prefiere cotizarlo con un proveedor, marque «Generar cotización».</p>'
                                .'</div>')
                            : new HtmlString('<div class="fi-manage-service-notice fi-manage-service-notice--amber px-5">'
                                .'<p class="font-semibold">Medicamentos no cubiertos: primero el inventario</p>'
                                .'<p class="mt-1 leading-relaxed opacity-90">Elija en el inventario el producto que corresponde a cada medicamento y se facturará al paciente. Si no está, no corresponde o no alcanza, marque «Generar cotización»: se registra como falla y pasa a la cotización.</p>'
                                .'</div>'))
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => MedicationInvoiceWizard::applies($livewire->getRecord(), $get('managed_service_item_keys')))
                        ->columnSpanFull(),
                    Placeholder::make('manage_service_mixed_quote_types_warning')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => CoordinationServiceItemsManager::manageServiceMixedQuoteTypesWarning())
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::manageQuoteStepResolvedType($livewire->getRecord(), $get) === null
                            && CoordinationServiceItemsManager::shouldShowManageQuoteStep($livewire->getRecord(), $get))
                        ->columnSpanFull(),
                    Section::make('Datos de la cotización')
                        ->description('Registre costos y utilidad para los ítems a cotizar.')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->iconColor('warning')
                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::manageQuoteStepResolvedType($livewire->getRecord(), $get) !== null)
                        ->schema([
                            Placeholder::make('manage_service_quote_type_badge')
                                ->label('Tipo de servicio detectado')
                                ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceOrderTypeBadge(
                                    CoordinationServiceItemsManager::manageQuoteStepResolvedType($livewire->getRecord(), $get)
                                )),
                            Section::make('Ítems incluidos en la cotización')
                                ->icon(Heroicon::OutlinedExclamationTriangle)
                                ->iconColor('danger')
                                ->schema([
                                    Placeholder::make('manage_service_non_covered_items_table')
                                        ->hiddenLabel()
                                        ->content(fn (ManageCoordinationServiceItems $livewire, Get $get): HtmlString => CoordinationServiceItemsManager::manageServiceQuoteItemsTable(
                                            $livewire->getRecord(),
                                            $get
                                        ))
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                            MedicationInventoryInvoiceFormSection::make(),
                            Section::make('Parámetros de cotización')
                                ->description('Ingrese el precio del proveedor en USD y el % de utilidad de cada ítem. El sistema calcula el precio de venta, los bolívares con la tasa BCV y la ganancia total.')
                                ->icon(Heroicon::OutlinedCalculator)
                                ->iconColor('warning')
                                ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::shouldShowManageQuoteParameters($livewire->getRecord(), $get))
                                ->extraAttributes(['class' => 'fi-manage-quote-params-section'])
                                ->schema([
                                    Grid::make(1)
                                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::shouldShowManageQuoteSupplierSelect($livewire->getRecord(), $get))
                                        ->schema([
                                            ManageQuoteSupplierCreator::configureSelect(
                                                Select::make('manage_quote_supplier_id')
                                                    ->label('Proveedor')
                                                    ->options(fn (): array => Supplier::query()
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id')
                                                        ->all())
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::shouldShowManageQuoteSupplierSelect($livewire->getRecord(), $get))
                                                    ->live()
                                                    ->native(false)
                                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                                        $set(
                                                            'manage_quote_supplier_address',
                                                            CoordinationServiceItemsManager::resolveManageQuoteSupplierAddress($state)
                                                        );
                                                        $set(
                                                            'manage_quote_supplier_phone',
                                                            CoordinationServiceItemsManager::resolveManageQuoteSupplierPhone($state)
                                                        );
                                                    })
                                                    ->helperText('Seleccione el proveedor que cotiza los ítems no cubiertos. Si no aparece en la lista, créelo con la acción del campo.'),
                                            ),
                                            Grid::make(['default' => 1, 'md' => 2])
                                                ->schema([
                                                    TextInput::make('manage_quote_supplier_address')
                                                        ->label('Dirección del proveedor')
                                                        ->readOnly()
                                                        ->dehydrated()
                                                        ->placeholder('Se completa al seleccionar el proveedor')
                                                        ->prefixIcon(Heroicon::OutlinedMapPin),
                                                    TextInput::make('manage_quote_supplier_phone')
                                                        ->label('Teléfono del proveedor')
                                                        ->tel()
                                                        ->readOnly()
                                                        ->dehydrated()
                                                        ->placeholder('Se completa al seleccionar el proveedor')
                                                        ->prefixIcon(Heroicon::OutlinedPhone),
                                                ]),
                                        ])
                                        ->columnSpanFull(),
                                    Placeholder::make('manage_quote_unregistered_provider_notice')
                                        ->hiddenLabel()
                                        ->visible(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => ! CoordinationServiceItemsManager::shouldShowManageQuoteSupplierSelect($livewire->getRecord(), $get))
                                        ->content(new HtmlString('<div class="fi-manage-service-notice fi-manage-service-notice--rose px-5"><p class="font-semibold">Proveedor no convenido</p><p class="mt-1 leading-relaxed opacity-90">La cotización se generará con el proveedor no convenido registrado en el paso anterior.</p></div>'))
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
                                        ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
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
                                                ->label('Precio del proveedor (USD)')
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
                                    Hidden::make('manage_quote_costo_dolares')
                                        ->dehydrated(),
                                    Hidden::make('manage_quote_costo_bolivares')
                                        ->dehydrated(),
                                    Placeholder::make('manage_quote_summary_panel')
                                        ->hiddenLabel()
                                        ->content(fn (Get $get): HtmlString => CoordinationServiceItemsManager::manageQuoteSummaryPanel($get))
                                        ->columnSpanFull(),
                                    QuoteInvoiceFormFields::make(
                                        'manage_quote_',
                                        fn (Get $get, mixed $livewire): bool => CoordinationServiceItemsManager::manageQuoteCanGenerateInvoice($livewire->getRecord(), $get),
                                    ),
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
