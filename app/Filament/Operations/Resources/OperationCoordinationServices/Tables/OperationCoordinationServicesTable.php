<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Tables;

use App\Http\Controllers\ApiBcvController;
use App\Http\Controllers\OperationServiceOrderController;
use App\Models\OperationCoordinationService;
use App\Models\OperationInventoryUbication;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\OperationTypeNegotiation;
use App\Models\OperationTypeService;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Models\TelemedicinePriority;
use App\Services\OperationQuoteGeneratorPdfService;
use App\Services\OperationServiceOrderPdfService;
use App\Services\OperationServiceOrderQuotePdfService;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class OperationCoordinationServicesTable
{
    private static function referenciaTasaBcvDesdeApi(): ?float
    {
        static $resolved = false;
        static $tasa = null;

        if (! $resolved) {
            $resolved = true;
            $fetchedRate = ApiBcvController::getTasaBcv();
            $tasa = is_numeric($fetchedRate) ? (float) $fetchedRate : null;
        }

        return $tasa;
    }

    private static function selectedServiceLabel(OperationCoordinationService $record, Get $get): string
    {
        $selectedItemNames = self::selectedServiceItemNames(
            $record,
            is_array($get('service_order_item_ids')) ? $get('service_order_item_ids') : []
        );

        if ($selectedItemNames !== []) {
            return implode(' · ', $selectedItemNames);
        }

        $service = $get('type_service');
        if (filled($service)) {
            return (string) $service;
        }

        if (filled($record->specific_service)) {
            return (string) $record->specific_service;
        }

        return (string) ($record->type_service ?: 'Servicio no especificado');
    }

    /**
     * @param  array<int, mixed>  $selectedIds
     * @return array<int, string>
     */
    private static function selectedServiceItemNames(OperationCoordinationService $record, array $selectedIds): array
    {
        $normalizedIds = array_values(array_unique(array_map('intval', $selectedIds)));
        if ($normalizedIds === []) {
            return [];
        }

        $options = self::serviceOrderSelectableOptions($record);

        return collect($normalizedIds)
            ->map(fn (int $id): ?string => $options[$id] ?? null)
            ->filter(fn (?string $label): bool => filled($label))
            ->values()
            ->all();
    }

    private static function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 4);
    }

    private static function quoteTotalProfitOrNull(mixed $quotePriceUsd, mixed $profitPercentage): ?float
    {
        $quotePrice = self::decimalOrNull($quotePriceUsd);
        $percentage = self::decimalOrNull($profitPercentage);

        if ($quotePrice === null || $percentage === null) {
            return null;
        }

        return round($quotePrice * ($percentage / 100), 4);
    }

    private static function quotePayloadFromWizardData(array $data): array
    {
        $quoteUsd = self::decimalOrNull($data['quote_price_usd'] ?? null);
        $quoteVes = self::decimalOrNull($data['quote_price_ves'] ?? null);
        $bcvRate = self::decimalOrNull($data['quote_bcv_rate'] ?? self::referenciaTasaBcvDesdeApi());

        return [
            'quote_price_usd' => $quoteUsd,
            'quote_price_ves' => $quoteVes,
            'quote_bcv_rate' => $bcvRate,
        ];
    }

    public static function configure(Table $table): Table
    {
        $selectTdgDoctorForAmbulanceAction = Action::make('selectTdgDoctorForAmbulanceFollowUp')
            ->modalHeading('Seleccionar Doctor TDG para seguimiento de caso')
            ->modalDescription(function (OperationCoordinationService $record): Htmlable {
                $current = $record->relationLoaded('telemedicineDoctor')
                    ? $record->telemedicineDoctor
                    : $record->telemedicineDoctor()->first();

                $currentDoctorHtml = ($current !== null && filled($current->full_name))
                    ? e($current->full_name)
                    : '<span class="opacity-70">Sin médico TDG asignado aún.</span>';

                $caseNote = filled($record->telemedicine_case_id)
                    ? 'Caso telemedicina <span class="font-mono font-medium text-gray-800 dark:text-gray-200">#'.e((string) $record->telemedicine_case_id).'</span> · el médico del caso se actualizará en la misma operación.'
                    : 'Esta orden no tiene caso de telemedicina vinculado; solo se actualizará la coordinación.';

                return new HtmlString(
                    '<div class="space-y-4 text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                    .'<div class="grid gap-3 sm:grid-cols-2">'
                    .'<div class="rounded-xl border border-gray-200/90 bg-gradient-to-b from-white to-gray-50/90 p-4 shadow-sm dark:border-white/10 dark:from-gray-900 dark:to-gray-950/80">'
                    .'<p class="text-[0.65rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Paciente</p>'
                    .'<p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">'.e($record->patient ?? '—').'</p>'
                    .'</div>'
                    .'<div class="rounded-xl border border-gray-200/90 bg-gradient-to-b from-white to-gray-50/90 p-4 shadow-sm dark:border-white/10 dark:from-gray-900 dark:to-gray-950/80">'
                    .'<p class="text-[0.65rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">N.º referencia</p>'
                    .'<p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">'.e($record->reference_number ?? '—').'</p>'
                    .'</div>'
                    .'</div>'
                    .'<div class="rounded-xl border border-rose-200/80 bg-gradient-to-br from-rose-50/95 to-white p-4 shadow-inner dark:border-rose-500/25 dark:from-rose-950/40 dark:to-gray-950/90">'
                    .'<p class="flex flex-wrap items-center gap-2 text-rose-950 dark:text-rose-50">'
                    .'<span class="inline-flex items-center rounded-full bg-rose-600/10 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-rose-800 dark:bg-rose-400/15 dark:text-rose-100">'
                    .'Traslado en ambulancia</span>'
                    .'<span class="text-sm font-medium">Médico TDG actual: '.$currentDoctorHtml.'</span>'
                    .'</p>'
                    .'<p class="mt-2 text-xs text-rose-900/80 dark:text-rose-100/80">'.$caseNote.'</p>'
                    .'</div>'
                    .'</div>'
                );
            })
            ->modalIcon(Heroicon::OutlinedUserGroup)
            ->modalIconColor('danger')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Asignar médico TDG')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->color('danger')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->extraModalWindowAttributes([
                'class' => 'fi-ambulance-tdg-doctor-modal overflow-hidden rounded-2xl ring-1 ring-gray-950/5 dark:ring-white/10',
            ], merge: true)
            ->closeModalByClickingAway(false)
            // FORMULARIO PARA ASIGNAR EL MEDICO TDG
            ->form([
                Grid::make(1)
                    ->schema([
                        Section::make('Médico TDG responsable')
                            ->description('Profesionales activos con pertenencia TDG. Use la búsqueda si la lista es larga.')
                            ->icon(Heroicon::OutlinedUser)
                            ->iconColor('primary')
                            ->schema([
                                Select::make('telemedicine_doctor_id')
                                    ->label('Seleccione al médico')
                                    ->placeholder('Escriba para filtrar por nombre o especialidad…')
                                    ->helperText('Se guarda en la orden y, si aplica, en el caso de telemedicina vinculado.')
                                    ->options(fn (): array => TelemedicineDoctor::query()
                                        ->where('status', 'ACTIVO')
                                        ->where('managed_by', 'TDG')
                                        ->orderBy('full_name', 'asc')
                                        ->get()
                                        ->mapWithKeys(fn (TelemedicineDoctor $doctor): array => [
                                            $doctor->id => $doctor->full_name.' — '.($doctor->specialty ?? '—').' — '.$doctor->managed_by,
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->fillForm(fn (OperationCoordinationService $record): array => [
                'telemedicine_doctor_id' => $record->telemedicine_doctor_id,
            ])
            ->action(function (OperationCoordinationService $record, array $data): void {
                $doctorId = (int) $data['telemedicine_doctor_id'];

                DB::transaction(function () use ($record, $doctorId): void {
                    $record->telemedicine_doctor_id = $doctorId;
                    $record->updated_by = Auth::user()?->name;
                    $record->save();

                    if (filled($record->telemedicine_case_id)) {
                        TelemedicineCase::query()
                            ->whereKey($record->telemedicine_case_id)
                            ->update(['telemedicine_doctor_id' => $doctorId]);
                    }
                });

                Notification::make()
                    ->title('Doctor TDG asignado')
                    ->body(
                        filled($record->telemedicine_case_id)
                            ? 'La orden y el caso de telemedicina vinculado quedaron asignados al médico seleccionado.'
                            : 'La orden quedó vinculada al médico seleccionado para seguimiento.'
                    )
                    ->success()
                    ->send();
            })
            ->visible(fn (OperationCoordinationService $record): bool => TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia($record->specific_service));

        $clinicCoordinationDocumentsAction = Action::make('clinicCoordinationDocuments')
            ->label(fn (OperationCoordinationService $record): string => $record->status === 'FINALIZADO'
                ? 'Documentos clínica'
                : 'Doc. ingreso / egreso clínica')
            ->icon(Heroicon::OutlinedDocumentArrowUp)
            ->color('info')
            ->modalHeading('Documentos de ingreso y egreso a clínica')
            ->modalDescription(function (OperationCoordinationService $record): Htmlable {
                return new HtmlString(
                    '<p class="text-sm text-gray-600 dark:text-gray-300">'
                    .'Gestione los soportes del servicio derivado <span class="font-semibold">Ingreso a clínica</span> para la referencia '
                    .e($record->reference_number ?? '—').'. Las cargas y eliminaciones quedan auditadas.</p>'
                );
            })
            ->modalIcon(Heroicon::OutlinedDocumentArrowUp)
            ->modalIconColor('info')
            ->modalWidth(Width::SixExtraLarge)
            ->stickyModalHeader()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->closeModalByClickingAway(false)
            ->modalContent(fn (OperationCoordinationService $record): Htmlable => new HtmlString(
                view('filament.operations.coordination.clinic-documents-modal', [
                    'serviceId' => $record->id,
                    'readOnly' => $record->status === 'FINALIZADO',
                ])->render()
            ))
            ->visible(fn (OperationCoordinationService $record): bool => TelemedicineDerivedServiceBadge::specificServiceIsIngresoAClinica($record->specific_service)
                && ! in_array((string) $record->status, ['CANCELADA', 'CANCELADO'], true));

        $editNegotiationAndPricingAction = Action::make('editNegotiationAndPricing')
            ->label('Negociación y precios')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->color('primary')
            ->modalHeading('Negociación, cotización y facturación')
            ->modalDescription(function (OperationCoordinationService $record): Htmlable {
                return new HtmlString(
                    '<div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">'
                    .'<p><span class="font-medium text-gray-900 dark:text-white">Paciente:</span> '.e($record->patient ?? '—').'</p>'
                    .'<p><span class="font-medium text-gray-900 dark:text-white">Referencia:</span> '.e($record->reference_number ?? '—').' · <span class="font-medium text-gray-900 dark:text-white">ID:</span> '.e((string) $record->id).'</p>'
                    .'</div>'
                );
            })
            ->modalIcon(Heroicon::OutlinedCurrencyDollar)
            ->modalIconColor('primary')
            ->modalWidth(Width::SevenExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalSubmitActionLabel('Guardar cambios')
            ->modalCancelActionLabel('Cerrar')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->color('primary')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->extraModalWindowAttributes([
                'class' => implode(' ', [
                    'fi-coordination-pricing-modal',
                    'max-h-[min(92vh,56rem)]',
                    'rounded-2xl',
                    'ring-1',
                    'ring-gray-950/5',
                    'dark:ring-white/10',
                    '[&_.fi-modal-content]:min-h-0',
                    '[&_.fi-modal-content]:max-h-[min(calc(92vh-10rem),48rem)]',
                    '[&_.fi-modal-content]:overflow-y-auto',
                    '[&_.fi-modal-content]:overscroll-contain',
                ]),
            ], merge: true)
            ->closeModalByClickingAway(false)
            ->fillForm(fn (OperationCoordinationService $record): array => [
                'type_service' => $record->type_service,
                'supplier_service' => $record->supplier_service,
                'farmadoc' => $record->farmadoc,
                'service_order_item_ids' => [],
                'create_service_order' => false,
                'create_associated_quote' => false,
                'quote_bcv_rate' => self::referenciaTasaBcvDesdeApi(),
                'quote_price_usd' => null,
                'quote_price_ves' => null,
                'quote_profit_percentage' => null,
                'quote_total_profit_usd' => null,
                'order_number' => 'ORD-'.str_pad((string) (((int) (OperationServiceOrder::max('id') ?? 0)) + 1), 4, '0', STR_PAD_LEFT),
                'telemedicine_priority_id' => $record->telemedicine_priority_id,
                'supplier_id' => null,
                'supplier_external' => null,
                'operation_inventory_ubication_id' => null,
                'service_order_description' => null,
                'service_order_observations' => null,
                'type_negotiation' => $record->type_negotiation,
                'status_negotiation' => $record->status_negotiation,
                'neto' => $record->neto,
                'porcen_tdec' => $record->porcen_tdec,
                'negotiation' => $record->negotiation,
                'porcen_discount' => $record->porcen_discount,
                'price_discount' => $record->price_discount,
                'quote_number' => $record->quote_number,
                'approved_number' => $record->approved_number,
                'service_order_number' => $record->service_order_number,
                'bill_number' => $record->bill_number,
                'bill_price' => $record->bill_price,
                'bill_date' => $record->bill_date,
                'incidence' => $record->incidence,
                'negotiation_description' => $record->negotiation_description,
                'qc_description' => $record->qc_description,
            ])
            ->modifyWizardUsing(function (Wizard $wizard): Wizard {
                return $wizard
                    ->extraAttributes([
                        'class' => implode(' ', [
                            'fi-coordination-service-wizard',
                            'w-full',
                            'min-h-0',
                            '[&_.fi-sc-wizard-step]:max-h-[min(58vh,32rem)]',
                            '[&_.fi-sc-wizard-step]:overflow-y-auto',
                            '[&_.fi-sc-wizard-step]:overscroll-contain',
                            '[&_.fi-sc-wizard-step]:pe-1',
                            '[&_.fi-sc-wizard-header]:sticky',
                            '[&_.fi-sc-wizard-header]:top-0',
                            '[&_.fi-sc-wizard-header]:z-10',
                            '[&_.fi-sc-wizard-header]:bg-white/95',
                            '[&_.fi-sc-wizard-header]:pb-2',
                            'dark:[&_.fi-sc-wizard-header]:bg-gray-950/95',
                        ]),
                    ], merge: true)
                    ->nextAction(
                        fn (Action $action): Action => $action
                            ->label('Siguiente')
                            ->icon(Heroicon::OutlinedArrowRight)
                            ->iconPosition(IconPosition::After)
                    )
                    ->previousAction(
                        fn (Action $action): Action => $action
                            ->label('Anterior')
                            ->icon(Heroicon::OutlinedArrowLeft)
                    );
            })
            ->steps([
                Step::make('Servicio')
                    ->description('Tipo, proveedor, Farmadoc')
                    ->icon(Heroicon::OutlinedCube)
                    ->completedIcon(Heroicon::OutlinedCheckCircle)
                    ->schema([
                        Section::make('Clasificación del servicio')
                            ->description('Seleccione el tipo de servicio y el proveedor. Puede buscar por nombre o RIF.')
                            ->icon(Heroicon::OutlinedCube)
                            ->iconColor('primary')
                            ->schema([
                                Select::make('type_service')
                                    ->label('Tipo de Servicio')
                                    ->placeholder('Seleccione…')
                                    ->options(
                                        OperationTypeService::query()
                                            ->orderBy('description', 'asc')
                                            ->pluck('description', 'description')
                                            ->all()
                                    )
                                    ->searchable()
                                    ->native(false),
                                Select::make('supplier_service')
                                    ->label('Proveedor de Servicio')
                                    ->placeholder('Busque por nombre o RIF…')
                                    ->searchable()
                                    ->getSearchResultsUsing(
                                        fn (string $search): array => Supplier::query()
                                            ->where(function ($query) use ($search): void {
                                                $query->where('name', 'like', "%{$search}%")
                                                    ->orWhere('rif', 'like', "%{$search}%");
                                            })
                                            ->orderBy('name', 'asc')
                                            ->limit(50)
                                            ->pluck('name', 'name')
                                            ->all()
                                    )
                                    ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? (string) $value : null)
                                    ->native(false),
                                TextInput::make('farmadoc')
                                    ->label('Farmadoc')
                                    ->maxLength(255),
                            ])->columnSpanFull()->columns(3),
                    ]),
                Step::make('Orden de Servicio')
                    ->description('Ítems y creación de orden')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->completedIcon(Heroicon::OutlinedCheckCircle)
                    ->schema([
                        Section::make('Ítems pendientes por gestionar')
                            ->description('Seleccione los ítems a incluir en la orden. Se muestran según el tipo de servicio derivado del caso.')
                            ->icon(Heroicon::OutlinedListBullet)
                            ->iconColor('info')
                            ->schema([
                                Placeholder::make('service_order_type_badge')
                                    ->label('Tipo detectado')
                                    ->content(fn (OperationCoordinationService $record): HtmlString => self::serviceOrderTypeBadge($record)),
                                Placeholder::make('service_order_existing_orders')
                                    ->label('Órdenes existentes')
                                    ->content(fn (OperationCoordinationService $record): HtmlString => self::existingServiceOrdersTable($record)),
                                CheckboxList::make('service_order_item_ids')
                                    ->label(fn (OperationCoordinationService $record): string => self::serviceOrderSelectableLabel($record))
                                    ->options(fn (OperationCoordinationService $record): array => self::serviceOrderSelectableOptions($record))
                                    ->descriptions(fn (OperationCoordinationService $record): array => self::serviceOrderSelectableDescriptions($record))
                                    ->bulkToggleable()
                                    ->searchable()
                                    ->live()
                                    ->columns(1)
                                    ->helperText('Solo se listan ítems que no estén en estatus EN GESTION.')
                                    ->visible(fn (OperationCoordinationService $record): bool => self::serviceOrderType($record) !== null)
                                    ->columnSpanFull(),
                                Placeholder::make('service_order_preview')
                                    ->label('Vista previa de ítems seleccionados')
                                    ->content(fn (OperationCoordinationService $record, Get $get): HtmlString => self::selectedServiceOrderItemsTable($record, $get('service_order_item_ids')))
                                    ->visible(fn (OperationCoordinationService $record): bool => self::serviceOrderType($record) !== null),
                            ])
                            ->columnSpanFull(),
                        Section::make('Crear orden desde esta modal')
                            ->description('Esta creación reemplaza la acción manual desde la vista de detalle, manteniendo la misma lógica de generación.')
                            ->icon(Heroicon::OutlinedPlusCircle)
                            ->iconColor('success')
                            ->visible(fn (OperationCoordinationService $record): bool => self::serviceOrderSelectableOptions($record) !== [])
                            ->schema([
                                Toggle::make('create_associated_quote')
                                    ->label('Crear cotización asociada')
                                    ->helperText('Actívelo para preparar y gestionar primero la cotización con tasa BCV.')
                                    ->default(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if (! $state) {
                                            $set('create_service_order', false);
                                            $set('quote_price_usd', null);
                                            $set('quote_price_ves', null);
                                            $set('quote_profit_percentage', null);
                                            $set('quote_total_profit_usd', null);
                                            $set('quote_bcv_rate', self::referenciaTasaBcvDesdeApi());
                                        }
                                    }),
                                Toggle::make('create_service_order')
                                    ->label('Crear orden al guardar este asistente')
                                    ->helperText('Active este paso solo cuando la cotización ya esté aprobada.')
                                    ->default(false)
                                    ->live()
                                    ->visible(fn (Get $get): bool => (bool) $get('create_associated_quote')),
                                Section::make('Cotización asociada')
                                    ->description('Define el precio en USD y el sistema calculará bolívares con la tasa BCV del día.')
                                    ->icon(Heroicon::OutlinedCurrencyDollar)
                                    ->iconColor('warning')
                                    ->visible(fn (Get $get): bool => (bool) $get('create_associated_quote'))
                                    ->schema([
                                        Placeholder::make('quote_selected_service_preview')
                                            ->label('Servicio seleccionado')
                                            ->content(fn (OperationCoordinationService $record, Get $get): string => self::selectedServiceLabel($record, $get)),
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('quote_bcv_rate')
                                                    ->label('Tasa BCV')
                                                    ->prefix('Bs.')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->dehydrated()
                                                    ->default(fn (): ?float => self::referenciaTasaBcvDesdeApi())
                                                    ->helperText('Valor obtenido automáticamente desde API BCV.'),
                                                TextInput::make('quote_price_usd')
                                                    ->label('Precio cotización en dólares')
                                                    ->prefix('US$')
                                                    ->numeric()
                                                    ->required(fn (Get $get): bool => (bool) $get('create_associated_quote'))
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                                        $rate = self::decimalOrNull($get('quote_bcv_rate'));
                                                        $usd = self::decimalOrNull($state);
                                                        if ($rate === null || $usd === null) {
                                                            $set('quote_price_ves', null);
                                                            $set(
                                                                'quote_total_profit_usd',
                                                                self::quoteTotalProfitOrNull(null, $get('quote_profit_percentage'))
                                                            );

                                                            return;
                                                        }

                                                        $set('quote_price_ves', round($usd * $rate, 4));
                                                        $set(
                                                            'quote_total_profit_usd',
                                                            self::quoteTotalProfitOrNull($usd, $get('quote_profit_percentage'))
                                                        );
                                                    }),
                                                TextInput::make('quote_price_ves')
                                                    ->label('Precio cotización en bolívares')
                                                    ->prefix('Bs.')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->dehydrated()
                                                    ->helperText('Calculado automáticamente por el sistema.'),
                                                TextInput::make('quote_profit_percentage')
                                                    ->label('Porcentaje de utilidad (%)')
                                                    ->prefix('%')
                                                    ->numeric()
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                                        $set(
                                                            'quote_total_profit_usd',
                                                            self::quoteTotalProfitOrNull($get('quote_price_usd'), $state)
                                                        );
                                                    }),
                                                TextInput::make('quote_total_profit_usd')
                                                    ->label('Ganancia total')
                                                    ->prefix('US$')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->dehydrated(false)
                                                    ->helperText('Cálculo automático: Precio cotización USD × % utilidad.'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('order_number')
                                            ->label('Número de orden')
                                            ->required(fn (Get $get): bool => (bool) $get('create_service_order'))
                                            ->visible(fn (Get $get): bool => (bool) $get('create_service_order'))
                                            ->maxLength(255),
                                        Select::make('telemedicine_priority_id')
                                            ->label('Prioridad')
                                            ->options(TelemedicinePriority::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                            ->required(fn (Get $get): bool => (bool) $get('create_service_order'))
                                            ->visible(fn (Get $get): bool => (bool) $get('create_service_order'))
                                            ->native(false),
                                        Select::make('supplier_id')
                                            ->label('Proveedor TDG')
                                            ->options(Supplier::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get): bool => (bool) $get('create_service_order'))
                                            ->native(false),
                                        TextInput::make('supplier_external')
                                            ->label('Proveedor externo')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => (bool) $get('create_service_order')),
                                        Select::make('operation_inventory_ubication_id')
                                            ->label('Ubicación inventario (medicamentos)')
                                            ->options(OperationInventoryUbication::query()->where('is_active', true)->orderBy('name', 'asc')->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (OperationCoordinationService $record, Get $get): bool => (bool) $get('create_service_order') && self::serviceOrderType($record) === 'MEDICAMENTOS')
                                            ->native(false)
                                            ->columnSpanFull(),
                                        TextInput::make('service_order_description')
                                            ->label('Descripción de la orden')
                                            ->required(fn (Get $get): bool => (bool) $get('create_service_order'))
                                            ->maxLength(500)
                                            ->visible(fn (Get $get): bool => (bool) $get('create_service_order'))
                                            ->columnSpanFull(),
                                        Textarea::make('service_order_observations')
                                            ->label('Observaciones de la orden')
                                            ->rows(3)
                                            ->maxLength(2000)
                                            ->visible(fn (Get $get): bool => (bool) $get('create_service_order'))
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columns(1)
                            ->columnSpanFull(),
                    ]),
                Step::make('Negociación')
                    ->description('Tipo y estatus')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->completedIcon(Heroicon::OutlinedCheckCircle)
                    ->schema([
                        Section::make('Parámetros de negociación')
                            ->description('Indicadores SI/NO y estatus (se guardará en mayúsculas).')
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->iconColor('warning')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('type_negotiation')
                                            ->label('Tipo de Negociación')
                                            ->placeholder('Seleccione…')
                                            ->options(
                                                OperationTypeNegotiation::query()
                                                    ->orderBy('description', 'asc')
                                                    ->pluck('description', 'description')
                                                    ->all()
                                            )
                                            ->searchable()
                                            ->native(false),
                                        TextInput::make('status_negotiation')
                                            ->label('Estatus de Negociación')
                                            ->maxLength(255)
                                            ->helperText('Se guardará en mayúsculas.'),
                                        Select::make('negotiation')
                                            ->label('Negociación')
                                            ->options(['SI' => 'SI', 'NO' => 'NO'])
                                            ->native(false),
                                        Select::make('incidence')
                                            ->label('Incidencia')
                                            ->options(['SI' => 'SI', 'NO' => 'NO'])
                                            ->native(false),
                                        Select::make('negotiation_description')
                                            ->label('Descripción de Negociación')
                                            ->options(['SI' => 'SI', 'NO' => 'NO'])
                                            ->native(false)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
                Step::make('Precios')
                    ->description('Neto, TDEC, descuentos')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->completedIcon(Heroicon::OutlinedCheckCircle)
                    ->schema([
                        Section::make('Precios y cotización')
                            ->description('Vista previa en vivo. Al guardar se persiste el precio de cotización con la fórmula indicada arriba.')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->iconColor('success')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('neto')
                                            ->label('Precio Neto')
                                            ->numeric()
                                            ->prefix('US$')
                                            ->live(onBlur: true),
                                        TextInput::make('porcen_tdec')
                                            ->label('% TDEC')
                                            ->numeric()
                                            ->prefix('%')
                                            ->live(onBlur: true),
                                        TextInput::make('porcen_discount')
                                            ->label('Porcentaje de Descuento')
                                            ->numeric()
                                            ->prefix('%'),
                                        TextInput::make('price_discount')
                                            ->label('Precio de Descuento')
                                            ->numeric()
                                            ->prefix('US$')
                                            ->helperText('Puede ajustar el importe manualmente si difiere del porcentaje.'),
                                        Placeholder::make('quote_price_preview')
                                            ->label('Precio de cotización (vista previa)')
                                            ->content(function (Get $get): HtmlString {
                                                $neto = (float) ($get('neto') ?? 0);
                                                $pct = (float) ($get('porcen_tdec') ?? 0);
                                                $quote = $neto + ($neto * $pct / 100);

                                                return new HtmlString(
                                                    '<div class="rounded-xl border border-gray-200/90 bg-gray-50/95 px-4 py-3 dark:border-white/10 dark:bg-white/5">'
                                                        .'<span class="text-lg font-bold tracking-tight text-gray-950 dark:text-white">US$ '
                                                        .e(number_format($quote, 2, '.', ','))
                                                        .'</span>'
                                                        .'<p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Vista previa; se confirma al guardar.</p>'
                                                        .'</div>'
                                                );
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
                Step::make('Documentos')
                    ->description('Cotización, OS, factura')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->completedIcon(Heroicon::OutlinedCheckCircle)
                    ->schema([
                        Section::make('Referencias y facturación')
                            ->description('Números administrativos y datos de factura.')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->iconColor('gray')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('quote_number')
                                            ->label('Número de Cotización')
                                            ->maxLength(255),
                                        TextInput::make('approved_number')
                                            ->label('Número de Aprobación')
                                            ->maxLength(255),
                                        TextInput::make('service_order_number')
                                            ->label('Número Orden de Servicio')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        // Section::make('Facturación')
                        //     ->description('Opcional: complete cuando exista factura.')
                        //     ->icon(Heroicon::OutlinedReceiptPercent)
                        //     ->iconColor('danger')
                        //     ->collapsed()
                        //     ->schema([
                        //         Grid::make(2)
                        //             ->schema([
                        //                 TextInput::make('bill_number')
                        //                     ->label('Número de Factura')
                        //                     ->maxLength(255),
                        //                 TextInput::make('bill_price')
                        //                     ->label('Precio de Factura')
                        //                     ->numeric()
                        //                     ->prefix('US$'),
                        //                 TextInput::make('bill_date')
                        //                     ->label('Fecha de Factura')
                        //                     ->maxLength(255)
                        //                     ->helperText('Texto o fecha tal como debe figurar en reportes.')
                        //                     ->columnSpanFull(),
                        //             ]),
                        //     ]),
                    ]),
                Step::make('Calidad')
                    ->description('QC — último paso')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->completedIcon(Heroicon::OutlinedCheckCircle)
                    ->schema([
                        Section::make('Control de calidad')
                            ->description('Revise el texto y pulse «Guardar cambios» en la barra inferior.')
                            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                            ->iconColor('info')
                            ->schema([
                                Textarea::make('qc_description')
                                    ->label('Descripción de QC')
                                    ->rows(6)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->action(function (OperationCoordinationService $record, array $data): void {
                $quotePayload = self::quotePayloadFromWizardData($data);

                if (($data['create_service_order'] ?? false) === true) {
                    self::createServiceOrderFromWizard($record, $data, $quotePayload);
                    $data['service_order_number'] = $data['order_number'] ?? $data['service_order_number'] ?? null;
                }

                $neto = (float) ($data['neto'] ?? 0);
                $porcenTdec = (float) ($data['porcen_tdec'] ?? 0);
                $quotePrice = $neto + ($neto * $porcenTdec / 100);

                $record->type_service = $data['type_service'] ?? null;
                $record->supplier_service = $data['supplier_service'] ?? null;
                $record->farmadoc = $data['farmadoc'] ?? null;
                $record->type_negotiation = $data['type_negotiation'] ?? null;
                $record->status_negotiation = isset($data['status_negotiation'])
                    ? mb_strtoupper((string) $data['status_negotiation'])
                    : null;
                $record->neto = $data['neto'] ?? null;
                $record->porcen_tdec = $data['porcen_tdec'] ?? null;
                $record->quote_price = $quotePrice;
                $record->negotiation = $data['negotiation'] ?? null;
                $record->porcen_discount = $data['porcen_discount'] ?? null;
                $record->price_discount = $data['price_discount'] ?? null;
                $record->quote_number = $data['quote_number'] ?? null;
                $record->approved_number = $data['approved_number'] ?? null;
                $record->service_order_number = $data['service_order_number'] ?? null;
                $record->bill_number = $data['bill_number'] ?? null;
                $record->bill_price = $data['bill_price'] ?? null;
                $record->bill_date = $data['bill_date'] ?? null;
                $record->incidence = $data['incidence'] ?? null;
                $record->negotiation_description = $data['negotiation_description'] ?? null;
                $record->qc_description = $data['qc_description'] ?? null;
                $record->updated_by = Auth::user()?->name;
                $record->save();

                Notification::make()
                    ->title('Coordinación actualizada')
                    ->body('Los datos de negociación, precios y facturación se guardaron correctamente.')
                    ->success()
                    ->send();
            });

        return $table

            ->heading('Cuadro de Control')
            ->description('Lista de servicios medicos coordinados en el sistema')
            ->defaultSort('date_solicitud', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['telemedicinePriority', 'telemedicineDoctor']))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('date_solicitud')
                    ->label('Fecha de Solicitud')
                    ->icon('heroicon-m-calendar-days')
                    ->datetime('d/m/Y')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('date_service')
                    ->label('Fecha de Servicio')
                    ->icon('heroicon-m-calendar-days')
                    ->datetime('d/m/Y')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('managed_by')
                    ->label('Gestionado por')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('servicie')
                    ->label('Servicio')
                    ->badge()
                    ->color(fn (?string $state): string => TelemedicineDerivedServiceBadge::driftNameIsCritical($state) ? 'danger' : 'info')
                    ->icon(fn (?string $state): string => TelemedicineDerivedServiceBadge::driftNameIsCritical($state)
                        ? 'heroicon-m-exclamation-triangle'
                        : 'heroicon-m-information-circle')
                    ->searchable(),
                TextColumn::make('specific_service')
                    ->label('Servicio Derivado')
                    ->badge()
                    ->color(fn (?string $state): string => TelemedicineDerivedServiceBadge::driftNameIsCritical($state) ? 'danger' : 'info')
                    ->icon(fn (?string $state): string => TelemedicineDerivedServiceBadge::driftNameIsCritical($state)
                        ? 'heroicon-m-exclamation-triangle'
                        : 'heroicon-m-information-circle')
                    ->action($selectTdgDoctorForAmbulanceAction)
                    ->tooltip(function (OperationCoordinationService $record): ?string {
                        if (! TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia($record->specific_service)) {
                            return null;
                        }

                        return 'Clic para asignar o cambiar el doctor TDG de seguimiento';
                    })
                    ->extraCellAttributes(fn (OperationCoordinationService $record): array => TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia($record->specific_service)
                        ? [
                            'class' => 'transition active:opacity-90',
                        ]
                        : [])
                    ->extraAttributes(fn (OperationCoordinationService $record): array => TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia($record->specific_service)
                        ? [
                            'class' => 'cursor-pointer underline decoration-dotted underline-offset-2 hover:opacity-90 active:opacity-75',
                        ]
                        : [])
                    ->searchable(),
                TextColumn::make('businessLine.definition')
                    ->label('Linea de Servicio')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('businessUnit.definition')
                    ->label('Unidad de Negocio')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('reference_number')
                    ->label('Número de Referencia')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus del Servicio')
                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'PENDIENTE' => 'warning',
                            'PENDIENTE POR RESULTADOS' => 'info',
                            'EN GESTION' => 'primary',
                            'CANCELADO' => 'gray',
                            'FINALIZADO' => 'success',
                            'NOVEDAD ADMON ESTUDIO' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->searchable(),
                TextColumn::make('telemedicinePriority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (string $state): string => TelemedicinePriorityFilamentBadge::color($state))
                    ->icon(fn (string $state): string => TelemedicinePriorityFilamentBadge::icon($state))
                    ->searchable(),
                TextColumn::make('holder')
                    ->label('Titular')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('ci_holder')
                    ->label('Cédula del Titular')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('patient')
                    ->label('Paciente')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('ci_patient')
                    ->label('Cédula del Paciente')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('birth_date_patient')
                    ->label('Fecha de Nacimiento del Paciente')
                    ->icon('heroicon-m-calendar-days')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('relationship_patient')
                    ->label('Relación del Paciente')
                    ->searchable(),
                TextColumn::make('age_patient')
                    ->label('Edad del Paciente')
                    ->searchable(),
                TextColumn::make('contractor')
                    ->label('Contratante')
                    ->searchable(),
                TextColumn::make('state_id')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('city_id')
                    ->label('Ciudad')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('phone_holder')
                    ->label('Teléfono del Titular')
                    ->searchable(),
                TextColumn::make('symptoms_diagnosis')
                    ->label('Síntomas y Diagnóstico')
                    ->searchable(),
                TextColumn::make('type_service')
                    ->label('Tipo de Servicio')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->tooltip('Edite en la acción «Negociación y precios».')
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('supplier_service')
                    ->label('Proveedor de Servicio')
                    ->searchable()
                    ->limit(28)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('farmadoc')
                    ->label('Farmadoc')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('type_negotiation')
                    ->label('Tipo de Negociación')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('status_negotiation')
                    ->label('Estatus de Negociación')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('neto')
                    ->label('Precio Neto')
                    ->money('USD')
                    ->sortable()
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('porcen_tdec')
                    ->label('% TDEC')
                    ->suffix('%')
                    ->sortable()
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('quote_price')
                    ->money()
                    ->badge()
                    ->color(fn ($record) => $record->quote_price > 0 ? 'success' : 'gray')
                    ->icon('heroicon-s-currency-dollar')
                    ->label('Precio de Cotización')
                    ->sortable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('negotiation')
                    ->label('Negociación')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'SI' ? 'success' : 'gray')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('porcen_discount')
                    ->label('% Descuento')
                    ->suffix('%')
                    ->sortable()
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('price_discount')
                    ->label('Precio de Descuento')
                    ->money('USD')
                    ->sortable()
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('quote_number')
                    ->label('Número de Cotización')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('approved_number')
                    ->label('Número de Aprobación')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('service_order_number')
                    ->label('Número Orden de Servicio')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('bill_number')
                    ->label('Número de Factura')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('bill_price')
                    ->money()
                    ->badge()
                    ->color(fn ($record) => $record->bill_price > 0 ? 'success' : 'gray')
                    ->icon('heroicon-s-currency-dollar')
                    ->prefix('US$')
                    ->label('Precio de Factura')
                    ->sortable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('bill_date')
                    ->label('Fecha de Factura')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('incidence')
                    ->label('Incidencia')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'SI' ? 'warning' : 'gray')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('negotiation_description')
                    ->label('Descripción de Negociación')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('qc_description')
                    ->label('Descripción de QC')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->searchable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('observations')
                    ->label('Observaciones')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado Por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado Por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado el')
                    ->description(fn ($record) => $record->created_at->diffForHumans())
                    ->icon('heroicon-m-calendar-days')
                    ->datetime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado el')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->icon('heroicon-m-calendar-days')
                    ->datetime('d/m/Y')
                    ->sortable(),
            ])
            ->recordClasses(fn ($record): array => [TelemedicinePriorityFilamentBadge::recordRowClasses($record->telemedicinePriority?->name)])
            ->modifyUngroupedRecordActionsUsing(function (Action $action): void {
                if ($action->getName() === 'selectTdgDoctorForAmbulanceFollowUp') {
                    $action->extraAttributes([
                        'class' => 'hidden',
                        'aria-hidden' => 'true',
                    ]);
                }
            })
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    $editNegotiationAndPricingAction,
                    $clinicCoordinationDocumentsAction,
                    $selectTdgDoctorForAmbulanceAction,
                    Action::make('manage_service_items')
                        ->label('Gestionar Servicio')
                        ->icon('heroicon-m-document-text')
                        ->color('success')
                        ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament ?? []))
                        ->modalHeading('Gestionar ítems del servicio')
                        ->modalDescription('Seleccione ítems pendientes y confirme la gestión del servicio.')
                        ->modalIcon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->modalIconColor('success')
                        ->modalSubmitActionLabel('Confirmar gestión')
                        ->modalCancelActionLabel('Cerrar')
                        ->modalWidth(Width::SevenExtraLarge)
                        ->closeModalByClickingAway(false)
                        ->modalSubmitAction(
                            fn (Action $action): Action => $action
                                ->color('success')
                                ->extraAttributes([
                                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                                ])
                        )
                        ->modalCancelAction(
                            fn (Action $action): Action => $action
                                ->extraAttributes([
                                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                                ])
                        )
                        ->extraModalWindowAttributes([
                            'class' => 'fi-coordination-manage-items-modal',
                        ], merge: true)
                        ->fillForm(fn (OperationCoordinationService $record): array => [
                            'managed_service_item_keys' => [],
                            'order_number' => 'ORD-'.str_pad((string) (((int) (OperationServiceOrder::max('id') ?? 0)) + 1), 4, '0', STR_PAD_LEFT),
                            'telemedicine_priority_id' => $record->telemedicine_priority_id,
                            'supplier_id' => null,
                            'supplier_external' => null,
                            'operation_inventory_ubication_id' => null,
                            'service_order_description' => null,
                            'service_order_observations' => null,
                            'manage_quote_bcv_rate' => self::referenciaTasaBcvDesdeApi(),
                            'manage_quote_costo_dolares' => null,
                            'manage_quote_costo_bolivares' => null,
                            'manage_quote_porcentaje_ganancia' => null,
                        ])
                        ->modifyWizardUsing(function (Wizard $wizard): Wizard {
                            return $wizard
                                ->extraAttributes([
                                    'class' => implode(' ', [
                                        'fi-coordination-manage-items-wizard',
                                        'fi-coordination-service-wizard',
                                        'w-full',
                                        'min-h-0',
                                    ]),
                                ], merge: true)
                                ->extraAlpineAttributes([
                                    'x-effect' => 'step; $nextTick(() => $el.closest(\'.fi-modal-content\')?.scrollTo({ top: 0, behavior: \'instant\' }))',
                                ])
                                ->nextAction(
                                    fn (Action $action): Action => $action
                                        ->label('Continuar')
                                        ->icon(Heroicon::OutlinedArrowRight)
                                        ->iconPosition(IconPosition::After)
                                        ->extraAttributes([
                                            'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                                        ])
                                )
                                ->previousAction(
                                    fn (Action $action): Action => $action
                                        ->label('Anterior')
                                        ->icon(Heroicon::OutlinedArrowLeft)
                                        ->extraAttributes([
                                            'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                                        ])
                                );
                        })
                        ->steps([
                            Step::make('Selección de ítems')
                                ->description('Revise cobertura y seleccione ítems')
                                ->icon(Heroicon::OutlinedClipboardDocumentList)
                                ->schema([
                                    Placeholder::make('manage_service_items_context')
                                        ->hiddenLabel()
                                        ->content(fn (OperationCoordinationService $record): HtmlString => self::manageServiceItemsContextHeader($record))
                                        ->columnSpanFull(),
                                    Section::make('Inventario de ítems asociados')
                                        ->description('Consulte el detalle completo antes de seleccionar qué ítems desea gestionar.')
                                        ->icon(Heroicon::OutlinedQueueList)
                                        ->iconColor('gray')
                                        ->schema([
                                            Placeholder::make('manage_service_items_overview')
                                                ->hiddenLabel()
                                                ->content(fn (OperationCoordinationService $record): HtmlString => self::associatedServiceItemsOverviewTable($record))
                                                ->columnSpanFull(),
                                        ])
                                        ->columnSpanFull(),
                                    Section::make('Selección para gestión')
                                        ->description('Marque uno o más ítems pendientes. Los cubiertos habilitan el paso de orden de servicio.')
                                        ->icon(Heroicon::OutlinedCheckCircle)
                                        ->iconColor('success')
                                        ->visible(fn (OperationCoordinationService $record): bool => self::manageServiceSelectableOptions($record) !== [])
                                        ->schema([
                                            CheckboxList::make('managed_service_item_keys')
                                                ->label('Ítems disponibles')
                                                ->options(fn (OperationCoordinationService $record): array => self::manageServiceSelectableOptions($record))
                                                ->descriptions(fn (OperationCoordinationService $record): array => self::manageServiceSelectableDescriptions($record))
                                                ->bulkToggleable()
                                                ->searchable()
                                                ->live()
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
                                        ->content(fn (): HtmlString => self::manageServiceEmptyState())
                                        ->visible(fn (OperationCoordinationService $record): bool => self::manageServiceSelectableOptions($record) === [])
                                        ->columnSpanFull(),
                                    Section::make('Resumen de selección')
                                        ->description('Vista previa en tiempo real de los ítems que gestionará al confirmar.')
                                        ->icon(Heroicon::OutlinedEye)
                                        ->iconColor('info')
                                        ->visible(fn (OperationCoordinationService $record): bool => self::manageServiceSelectableOptions($record) !== [])
                                        ->schema([
                                            Placeholder::make('manage_service_items_preview')
                                                ->hiddenLabel()
                                                ->content(fn (OperationCoordinationService $record, Get $get): HtmlString => self::manageServiceSelectedItemsTable(
                                                    $record,
                                                    $get('managed_service_item_keys')
                                                ))
                                                ->columnSpanFull(),
                                        ])
                                        ->columnSpanFull(),
                                ]),
                            Step::make('Orden de servicio')
                                ->description('Creación automática para ítems cubiertos')
                                ->icon(Heroicon::OutlinedDocumentPlus)
                                ->visible(fn (OperationCoordinationService $record, Get $get): bool => self::coveredSelectedManagementItemKeys(
                                    $record,
                                    $get('managed_service_item_keys')
                                ) !== [])
                                ->schema([
                                    Placeholder::make('manage_service_order_context')
                                        ->hiddenLabel()
                                        ->content(fn (OperationCoordinationService $record): HtmlString => self::manageServiceItemsContextHeader($record))
                                        ->columnSpanFull(),
                                    Placeholder::make('manage_service_covered_items_notice')
                                        ->hiddenLabel()
                                        ->content(fn (OperationCoordinationService $record, Get $get): HtmlString => self::manageServiceCoveredItemsNotice($record, $get('managed_service_item_keys')))
                                        ->columnSpanFull(),
                                    Placeholder::make('manage_service_mixed_types_warning')
                                        ->hiddenLabel()
                                        ->content(fn (): HtmlString => self::manageServiceMixedTypesWarning())
                                        ->visible(fn (OperationCoordinationService $record, Get $get): bool => self::resolveServiceOrderTypeFromManagementKeys(
                                            self::coveredSelectedManagementItemKeys($record, $get('managed_service_item_keys'))
                                        ) === null && self::coveredSelectedManagementItemKeys($record, $get('managed_service_item_keys')) !== [])
                                        ->columnSpanFull(),
                                    Section::make('Datos de la orden de servicio')
                                        ->description('Complete la información operativa. Solo se incluirán ítems con cobertura confirmada.')
                                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                                        ->iconColor('primary')
                                        ->visible(fn (OperationCoordinationService $record, Get $get): bool => self::resolveServiceOrderTypeFromManagementKeys(
                                            self::coveredSelectedManagementItemKeys($record, $get('managed_service_item_keys'))
                                        ) !== null)
                                        ->schema([
                                            Placeholder::make('manage_service_order_type_badge')
                                                ->label('Tipo de orden detectado')
                                                ->content(fn (OperationCoordinationService $record, Get $get): HtmlString => self::manageServiceOrderTypeBadge(
                                                    self::resolveServiceOrderTypeFromManagementKeys(
                                                        self::coveredSelectedManagementItemKeys($record, $get('managed_service_item_keys'))
                                                    )
                                                )),
                                            Section::make('Ítems incluidos en la orden')
                                                ->icon(Heroicon::OutlinedShieldCheck)
                                                ->iconColor('success')
                                                ->schema([
                                                    Placeholder::make('manage_service_covered_items_table')
                                                        ->hiddenLabel()
                                                        ->content(fn (OperationCoordinationService $record, Get $get): HtmlString => self::manageServiceCoveredItemsTable(
                                                            $record,
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
                                                        ->content(fn (OperationCoordinationService $record): HtmlString => self::existingServiceOrdersTable($record))
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
                                                            Select::make('supplier_id')
                                                                ->label('Proveedor TDG')
                                                                ->options(Supplier::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                                                ->searchable()
                                                                ->preload()
                                                                ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                                                ->native(false),
                                                            TextInput::make('supplier_external')
                                                                ->label('Proveedor externo')
                                                                ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                                                                ->placeholder('Nombre si el suministro es externo')
                                                                ->maxLength(255),
                                                            Select::make('operation_inventory_ubication_id')
                                                                ->label('Ubicación inventario (medicamentos)')
                                                                ->options(OperationInventoryUbication::query()->where('is_active', true)->orderBy('name', 'asc')->pluck('name', 'id'))
                                                                ->searchable()
                                                                ->preload()
                                                                ->prefixIcon(Heroicon::OutlinedMapPin)
                                                                ->visible(fn (OperationCoordinationService $record, Get $get): bool => self::resolveServiceOrderTypeFromManagementKeys(
                                                                    self::coveredSelectedManagementItemKeys($record, $get('managed_service_item_keys'))
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
                                                ])
                                                ->columnSpanFull(),
                                        ])
                                        ->columnSpanFull(),
                                ]),
                            Step::make('Cotización')
                                ->description('Obligatoria para ítems no cubiertos')
                                ->icon(Heroicon::OutlinedCurrencyDollar)
                                ->visible(fn (OperationCoordinationService $record, Get $get): bool => self::nonCoveredSelectedManagementItemKeys(
                                    $record,
                                    $get('managed_service_item_keys')
                                ) !== [])
                                ->schema([
                                    Placeholder::make('manage_service_quote_context')
                                        ->hiddenLabel()
                                        ->content(fn (OperationCoordinationService $record): HtmlString => self::manageServiceItemsContextHeader($record))
                                        ->columnSpanFull(),
                                    Placeholder::make('manage_service_non_covered_items_notice')
                                        ->hiddenLabel()
                                        ->content(fn (): HtmlString => self::manageServiceNonCoveredItemsNotice())
                                        ->columnSpanFull(),
                                    Placeholder::make('manage_service_mixed_quote_types_warning')
                                        ->hiddenLabel()
                                        ->content(fn (): HtmlString => self::manageServiceMixedQuoteTypesWarning())
                                        ->visible(fn (OperationCoordinationService $record, Get $get): bool => self::resolveServiceOrderTypeFromManagementKeys(
                                            self::nonCoveredSelectedManagementItemKeys($record, $get('managed_service_item_keys'))
                                        ) === null && self::nonCoveredSelectedManagementItemKeys($record, $get('managed_service_item_keys')) !== [])
                                        ->columnSpanFull(),
                                    Section::make('Datos de la cotización')
                                        ->description('Registre costos y utilidad para los ítems no cubiertos seleccionados.')
                                        ->icon(Heroicon::OutlinedBanknotes)
                                        ->iconColor('warning')
                                        ->visible(fn (OperationCoordinationService $record, Get $get): bool => self::resolveServiceOrderTypeFromManagementKeys(
                                            self::nonCoveredSelectedManagementItemKeys($record, $get('managed_service_item_keys'))
                                        ) !== null)
                                        ->schema([
                                            Placeholder::make('manage_service_quote_type_badge')
                                                ->label('Tipo de servicio detectado')
                                                ->content(fn (OperationCoordinationService $record, Get $get): HtmlString => self::manageServiceOrderTypeBadge(
                                                    self::resolveServiceOrderTypeFromManagementKeys(
                                                        self::nonCoveredSelectedManagementItemKeys($record, $get('managed_service_item_keys'))
                                                    )
                                                )),
                                            Section::make('Ítems no cubiertos incluidos')
                                                ->icon(Heroicon::OutlinedExclamationTriangle)
                                                ->iconColor('danger')
                                                ->schema([
                                                    Placeholder::make('manage_service_non_covered_items_table')
                                                        ->hiddenLabel()
                                                        ->content(fn (OperationCoordinationService $record, Get $get): HtmlString => self::manageServiceNonCoveredItemsTable(
                                                            $record,
                                                            $get('managed_service_item_keys')
                                                        ))
                                                        ->columnSpanFull(),
                                                ])
                                                ->columnSpanFull(),
                                            Section::make('Parámetros de cotización')
                                                ->description('Ingrese el costo base y la utilidad. Los totales se calculan en tiempo real.')
                                                ->icon(Heroicon::OutlinedCalculator)
                                                ->iconColor('warning')
                                                ->extraAttributes(['class' => 'fi-manage-quote-params-section'])
                                                ->schema([
                                                    Grid::make(['default' => 1, 'lg' => 5])
                                                        ->schema([
                                                            Grid::make(1)
                                                                ->columnSpan(['lg' => 3])
                                                                ->schema([
                                                                    Grid::make(['default' => 1, 'sm' => 2])
                                                                        ->schema([
                                                                            TextInput::make('manage_quote_bcv_rate')
                                                                                ->label('Tasa BCV del día')
                                                                                ->prefix('Bs.')
                                                                                ->numeric()
                                                                                ->readOnly()
                                                                                ->dehydrated()
                                                                                ->default(fn (): ?float => self::referenciaTasaBcvDesdeApi())
                                                                                ->helperText('Referencia automática desde API BCV.')
                                                                                ->extraAttributes(['class' => 'fi-manage-quote-readonly-field']),
                                                                            TextInput::make('manage_quote_costo_bolivares')
                                                                                ->label('Equivalente en bolívares')
                                                                                ->prefix('Bs.')
                                                                                ->numeric()
                                                                                ->readOnly()
                                                                                ->dehydrated()
                                                                                ->helperText('Costo USD × tasa BCV.')
                                                                                ->extraAttributes(['class' => 'fi-manage-quote-readonly-field']),
                                                                        ]),
                                                                    Grid::make(['default' => 1, 'sm' => 2])
                                                                        ->schema([
                                                                            TextInput::make('manage_quote_costo_dolares')
                                                                                ->label('Costo base en dólares')
                                                                                ->prefix('US$')
                                                                                ->numeric()
                                                                                ->required()
                                                                                ->live(debounce: 400)
                                                                                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                                                                    $rate = self::decimalOrNull($get('manage_quote_bcv_rate'));
                                                                                    $usd = self::decimalOrNull($state);

                                                                                    if ($rate === null || $usd === null) {
                                                                                        $set('manage_quote_costo_bolivares', null);

                                                                                        return;
                                                                                    }

                                                                                    $set('manage_quote_costo_bolivares', round($usd * $rate, 2));
                                                                                })
                                                                                ->helperText('Monto de referencia del proveedor o costo operativo.'),
                                                                            TextInput::make('manage_quote_porcentaje_ganancia')
                                                                                ->label('Porcentaje de ganancia')
                                                                                ->prefix('%')
                                                                                ->numeric()
                                                                                ->default(0)
                                                                                ->minValue(0)
                                                                                ->live(debounce: 400)
                                                                                ->helperText('Utilidad aplicada sobre el costo base.'),
                                                                        ]),
                                                                ]),
                                                            Placeholder::make('manage_quote_summary_panel')
                                                                ->hiddenLabel()
                                                                ->content(fn (Get $get): HtmlString => self::manageQuoteSummaryPanel($get))
                                                                ->columnSpan(['lg' => 2]),
                                                        ]),
                                                ])
                                                ->columnSpanFull(),
                                        ])
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->action(fn (OperationCoordinationService $record, array $data): mixed => self::manageSelectedServiceItems($record, $data))
                        ->disabled(fn (OperationCoordinationService $record): bool => self::manageServiceSelectableOptions($record) === []),
                    Action::make('manage_service_quote')
                        ->label('Gestionar Cotización')
                        ->icon(Heroicon::OutlinedDocumentCurrencyDollar)
                        ->color('warning')
                        ->visible(fn (OperationCoordinationService $record): bool => self::coordinationQuotes($record)->isNotEmpty())
                        ->modalHeading('Gestionar cotizaciones del servicio')
                        ->modalDescription('Revise las cotizaciones generadas, actualice su estatus y cree la orden de servicio al aprobar.')
                        ->modalIcon(Heroicon::OutlinedBanknotes)
                        ->modalIconColor('warning')
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalSubmitActionLabel('Guardar cambios')
                        ->modalCancelActionLabel('Cerrar')
                        ->closeModalByClickingAway(false)
                        ->modalSubmitAction(
                            fn (Action $action): Action => $action
                                ->color('warning')
                                ->extraAttributes([
                                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                                ])
                        )
                        ->modalCancelAction(
                            fn (Action $action): Action => $action
                                ->extraAttributes([
                                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                                ])
                        )
                        ->extraModalWindowAttributes([
                            'class' => 'fi-coordination-manage-items-modal',
                        ], merge: true)
                        ->fillForm(fn (OperationCoordinationService $record): array => self::manageServiceQuoteFormDefaults($record))
                        ->form(fn (OperationCoordinationService $record): array => [
                            Placeholder::make('manage_service_quote_modal_context')
                                ->hiddenLabel()
                                ->content(fn (): HtmlString => self::manageServiceItemsContextHeader($record))
                                ->columnSpanFull(),
                            Placeholder::make('manage_service_quotes_summary')
                                ->label('Resumen de cotizaciones')
                                ->content(fn (): HtmlString => self::renderCoordinationQuotesSummary($record))
                                ->columnSpanFull(),
                            Repeater::make('quote_statuses')
                                ->label('Cotizaciones registradas')
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->schema([
                                    Hidden::make('quote_id'),
                                    Hidden::make('has_service_order'),
                                    Placeholder::make('quote_preview')
                                        ->hiddenLabel()
                                        ->content(function (Get $get): HtmlString {
                                            $quote = OperationQuoteGenerator::query()->find((int) $get('quote_id'));

                                            if (! $quote instanceof OperationQuoteGenerator) {
                                                return new HtmlString(
                                                    '<div class="rounded-xl border border-dashed border-gray-300/80 px-4 py-3 text-sm text-gray-600 dark:border-white/15 dark:text-gray-300">Cotización no disponible.</div>'
                                                );
                                            }

                                            return self::renderOperationQuotePreview($quote);
                                        })
                                        ->columnSpanFull(),
                                    Select::make('status')
                                        ->label('Estatus')
                                        ->options(OperationQuoteGenerator::statusOptions())
                                        ->required()
                                        ->native(false)
                                        ->live()
                                        ->disabled(fn (Get $get): bool => (bool) $get('has_service_order'))
                                        ->helperText(fn (Get $get): ?string => (bool) $get('has_service_order')
                                            ? 'Esta cotización ya tiene una orden de servicio vinculada.'
                                            : 'Al aprobar se habilitará el formulario de orden de servicio.')
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) use ($record): void {
                                            if ($state !== OperationQuoteGenerator::STATUS_APPROVED) {
                                                return;
                                            }

                                            self::prefillServiceOrderFormFromQuote($set, $record, (int) $get('quote_id'));
                                        }),
                                ])
                                ->columnSpanFull(),
                            Section::make('Orden de servicio')
                                ->description('Complete los datos operativos para la cotización aprobada.')
                                ->icon(Heroicon::OutlinedDocumentPlus)
                                ->iconColor('success')
                                ->visible(fn (Get $get): bool => self::hasApprovedQuotePendingOrderInForm($get('quote_statuses')))
                                ->schema([
                                    Hidden::make('approved_quote_id'),
                                    Placeholder::make('approved_quote_notice')
                                        ->hiddenLabel()
                                        ->content(fn (Get $get): HtmlString => self::approvedQuoteOrderNotice((int) $get('approved_quote_id')))
                                        ->columnSpanFull(),
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('order_number')
                                                ->label('Número de orden')
                                                ->required(fn (Get $get): bool => self::hasApprovedQuotePendingOrderInForm($get('quote_statuses')))
                                                ->prefixIcon(Heroicon::OutlinedHashtag)
                                                ->maxLength(255),
                                            Select::make('telemedicine_priority_id')
                                                ->label('Prioridad')
                                                ->options(TelemedicinePriority::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                                ->required(fn (Get $get): bool => self::hasApprovedQuotePendingOrderInForm($get('quote_statuses')))
                                                ->prefixIcon(Heroicon::OutlinedBolt)
                                                ->native(false),
                                            Select::make('supplier_id')
                                                ->label('Proveedor TDG')
                                                ->options(Supplier::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                                ->native(false),
                                            TextInput::make('supplier_external')
                                                ->label('Proveedor externo')
                                                ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                                                ->maxLength(255),
                                            Select::make('operation_inventory_ubication_id')
                                                ->label('Ubicación inventario (medicamentos)')
                                                ->options(OperationInventoryUbication::query()->where('is_active', true)->orderBy('name', 'asc')->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->prefixIcon(Heroicon::OutlinedMapPin)
                                                ->visible(fn (Get $get): bool => self::approvedQuoteServiceType((int) $get('approved_quote_id')) === 'MEDICAMENTOS')
                                                ->native(false)
                                                ->columnSpanFull(),
                                            TextInput::make('service_order_description')
                                                ->label('Descripción de la orden')
                                                ->required(fn (Get $get): bool => self::hasApprovedQuotePendingOrderInForm($get('quote_statuses')))
                                                ->prefixIcon(Heroicon::OutlinedDocumentText)
                                                ->maxLength(500)
                                                ->columnSpanFull(),
                                            Textarea::make('service_order_observations')
                                                ->label('Observaciones de la orden')
                                                ->rows(3)
                                                ->maxLength(2000)
                                                ->columnSpanFull(),
                                        ]),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->action(fn (OperationCoordinationService $record, array $data): mixed => self::saveManageServiceQuotes($record, $data)),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function serviceOrderType(OperationCoordinationService $record): ?string
    {
        if ($record->telemedicinePatientMedications()->where('status', '!=', 'EN GESTION')->exists()) {
            return 'MEDICAMENTOS';
        }

        if ($record->telemedicinePatientStudies()->where('status', '!=', 'EN GESTION')->exists()) {
            return 'IMAGENOLOGIA';
        }

        if ($record->telemedicinePatientLabs()->where('status', '!=', 'EN GESTION')->exists()) {
            return 'LABORATORIOS';
        }

        if ($record->telemedicinePatientSpecialties()->where('status', '!=', 'EN GESTION')->exists()) {
            return 'ESPECIALISTA';
        }

        return null;
    }

    private static function serviceOrderTypeBadge(OperationCoordinationService $record): HtmlString
    {
        $type = self::serviceOrderType($record);

        if ($type === null) {
            return new HtmlString(
                '<div class="rounded-xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-50">'
                .'No hay ítems pendientes para crear orden en este momento. Si todos están en <strong>EN GESTION</strong>, no se mostrarán para selección.'
                .'</div>'
            );
        }

        return new HtmlString(
            '<div class="rounded-xl border border-primary-200/80 bg-primary-50/90 px-4 py-3 text-sm text-primary-900 dark:border-primary-500/30 dark:bg-primary-950/40 dark:text-primary-50">'
            .'Tipo de orden detectado: <strong>'.$type.'</strong>'
            .'</div>'
        );
    }

    private static function existingServiceOrdersTable(OperationCoordinationService $record): HtmlString
    {
        $orders = OperationServiceOrder::query()
            ->where('operation_coordination_service_id', $record->id)
            ->latest('id')
            ->limit(5)
            ->get(['order_number', 'service_type', 'status', 'created_at']);

        if ($orders->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-xl border border-gray-200/90 bg-gray-50/90 px-4 py-3 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">'
                .'Aún no hay órdenes registradas para esta coordinación.'
                .'</div>'
            );
        }

        $rows = $orders->map(function (OperationServiceOrder $order): string {
            return '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                .'<td class="px-3 py-2 font-medium">'.e((string) $order->order_number).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($order->service_type ?? '—')).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($order->status ?? '—')).'</td>'
                .'<td class="px-3 py-2">'.e(optional($order->created_at)->format('d/m/Y H:i') ?? '—').'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="overflow-x-auto rounded-xl border border-gray-200/90 dark:border-white/10">'
            .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
            .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
            .'<th class="px-3 py-2 text-left font-semibold">Orden</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Tipo</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Estatus</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Creada</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</div>'
        );
    }

    private static function serviceOrderSelectableLabel(OperationCoordinationService $record): string
    {
        return match (self::serviceOrderType($record)) {
            'MEDICAMENTOS' => 'Medicamentos disponibles',
            'IMAGENOLOGIA' => 'Estudios de imagenología disponibles',
            'LABORATORIOS' => 'Laboratorios disponibles',
            'ESPECIALISTA' => 'Especialidades disponibles',
            default => 'Ítems disponibles',
        };
    }

    private static function serviceOrderSelectableItems(OperationCoordinationService $record): Collection
    {
        $type = self::serviceOrderType($record);

        return match ($type) {
            'MEDICAMENTOS' => $record->telemedicinePatientMedications()
                ->where('status', '!=', 'EN GESTION')
                ->orderBy('id')
                ->with('operationInventory:id,is_covered')
                ->get(['id', 'medicine', 'indications', 'status', 'is_covered', 'operation_inventory_id'])
                ->map(fn (TelemedicinePatientMedications $item): array => [
                    'coverage_label' => self::coverageLabel(self::coverageValue($type, $item)),
                    'id' => (int) $item->id,
                    'label' => (string) ($item->medicine ?? 'Medicamento sin nombre'),
                    'description' => 'Indicaciones: '.($item->indications ?? '—').' · Cobertura: '.self::coverageLabel(self::coverageValue($type, $item)).' · Estatus: '.($item->status ?? '—'),
                ]),
            'IMAGENOLOGIA' => $record->telemedicinePatientStudies()
                ->where('status', '!=', 'EN GESTION')
                ->orderBy('id')
                ->get(['id', 'study', 'type', 'status'])
                ->map(fn (TelemedicinePatientStudy $item): array => [
                    'coverage_label' => self::coverageLabel(self::coverageValue($type, $item)),
                    'id' => (int) $item->id,
                    'label' => (string) ($item->study ?? 'Estudio sin nombre'),
                    'description' => 'Tipo: '.($item->type ?? '—').' · Cobertura: '.self::coverageLabel(self::coverageValue($type, $item)).' · Estatus: '.($item->status ?? '—'),
                ]),
            'LABORATORIOS' => $record->telemedicinePatientLabs()
                ->where('status', '!=', 'EN GESTION')
                ->orderBy('id')
                ->get(['id', 'laboratory', 'type', 'status'])
                ->map(fn (TelemedicinePatientLab $item): array => [
                    'coverage_label' => self::coverageLabel(self::coverageValue($type, $item)),
                    'id' => (int) $item->id,
                    'label' => (string) ($item->laboratory ?? 'Laboratorio sin nombre'),
                    'description' => 'Tipo: '.($item->type ?? '—').' · Cobertura: '.self::coverageLabel(self::coverageValue($type, $item)).' · Estatus: '.($item->status ?? '—'),
                ]),
            'ESPECIALISTA' => $record->telemedicinePatientSpecialties()
                ->where('status', '!=', 'EN GESTION')
                ->orderBy('id')
                ->get(['id', 'specialty', 'type', 'status'])
                ->map(fn (TelemedicinePatientSpecialty $item): array => [
                    'coverage_label' => self::coverageLabel(self::coverageValue($type, $item)),
                    'id' => (int) $item->id,
                    'label' => (string) ($item->specialty ?? 'Especialidad sin nombre'),
                    'description' => 'Tipo: '.($item->type ?? '—').' · Cobertura: '.self::coverageLabel(self::coverageValue($type, $item)).' · Estatus: '.($item->status ?? '—'),
                ]),
            default => collect(),
        };
    }

    private static function serviceOrderSelectableOptions(OperationCoordinationService $record): array
    {
        return self::serviceOrderSelectableItems($record)
            ->mapWithKeys(fn (array $item): array => [$item['id'] => $item['label']])
            ->all();
    }

    private static function serviceOrderSelectableDescriptions(OperationCoordinationService $record): array
    {
        return self::serviceOrderSelectableItems($record)
            ->mapWithKeys(fn (array $item): array => [$item['id'] => $item['description']])
            ->all();
    }

    private static function selectedServiceOrderRecords(OperationCoordinationService $record, array $ids): EloquentCollection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return new EloquentCollection;
        }

        return match (self::serviceOrderType($record)) {
            'MEDICAMENTOS' => TelemedicinePatientMedications::query()
                ->where('operation_coordination_service_id', $record->id)
                ->whereKey($ids)
                ->with('operationInventory:id,is_covered')
                ->get(),
            'IMAGENOLOGIA' => TelemedicinePatientStudy::query()
                ->where('operation_coordination_service_id', $record->id)
                ->whereKey($ids)
                ->get(),
            'LABORATORIOS' => TelemedicinePatientLab::query()
                ->where('operation_coordination_service_id', $record->id)
                ->whereKey($ids)
                ->get(),
            'ESPECIALISTA' => TelemedicinePatientSpecialty::query()
                ->where('operation_coordination_service_id', $record->id)
                ->whereKey($ids)
                ->get(),
            default => new EloquentCollection,
        };
    }

    private static function selectedServiceOrderItemsTable(OperationCoordinationService $record, mixed $selectedIds): HtmlString
    {
        $ids = is_array($selectedIds) ? $selectedIds : [];
        $records = self::selectedServiceOrderRecords($record, $ids);

        if ($records->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-xl border border-dashed border-gray-300/90 bg-gray-50/70 px-4 py-3 text-sm text-gray-600 dark:border-white/15 dark:bg-white/5 dark:text-gray-300">'
                .'Seleccione al menos un ítem para ver la vista previa de la orden.'
                .'</div>'
            );
        }

        $rows = $records->map(function ($row) use ($record): string {
            $type = self::serviceOrderType($record);
            $isCovered = self::coverageValue($type, $row);
            $coverageLabel = self::coverageLabel($isCovered);
            $coverageBadgeClass = match ($isCovered) {
                true => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300/80 dark:bg-emerald-500/20 dark:text-emerald-100 dark:ring-emerald-500/30',
                false => 'bg-rose-100 text-rose-800 ring-1 ring-rose-300/80 dark:bg-rose-500/20 dark:text-rose-100 dark:ring-rose-500/30',
                default => 'bg-gray-100 text-gray-700 ring-1 ring-gray-300/80 dark:bg-white/10 dark:text-gray-200 dark:ring-white/20',
            };

            $itemName = match ($type) {
                'MEDICAMENTOS' => (string) ($row->medicine ?? '—'),
                'IMAGENOLOGIA' => (string) ($row->study ?? '—'),
                'LABORATORIOS' => (string) ($row->laboratory ?? '—'),
                'ESPECIALISTA' => (string) ($row->specialty ?? '—'),
                default => '—',
            };

            $extra = match ($type) {
                'MEDICAMENTOS' => (string) ($row->indications ?? '—'),
                default => (string) ($row->type ?? '—'),
            };

            return '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                .'<td class="px-3 py-2 font-medium">'.e($itemName).'</td>'
                .'<td class="px-3 py-2">'.e($extra).'</td>'
                .'<td class="px-3 py-2"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold '.$coverageBadgeClass.'">'.e($coverageLabel).'</span></td>'
                .'<td class="px-3 py-2">'.e((string) ($row->status ?? '—')).'</td>'
                .'</tr>';
        })->implode('');

        $middleTitle = self::serviceOrderType($record) === 'MEDICAMENTOS' ? 'Indicaciones' : 'Tipo';

        return new HtmlString(
            '<div class="overflow-x-auto rounded-xl border border-gray-200/90 dark:border-white/10">'
            .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
            .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
            .'<th class="px-3 py-2 text-left font-semibold">Ítem</th>'
            .'<th class="px-3 py-2 text-left font-semibold">'.e($middleTitle).'</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Cobertura</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Estatus</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</div>'
        );
    }

    private static function coverageValue(?string $serviceOrderType, mixed $row): ?bool
    {
        if ($serviceOrderType === 'MEDICAMENTOS') {
            if (isset($row->operationInventory) && $row->operationInventory !== null && $row->operationInventory->is_covered !== null) {
                return (bool) $row->operationInventory->is_covered;
            }

            return isset($row->is_covered) ? (bool) $row->is_covered : null;
        }

        if (isset($row->type) && is_string($row->type)) {
            return mb_strtoupper(trim($row->type)) === 'CUBIERTO';
        }

        return null;
    }

    private static function coverageLabel(?bool $isCovered): string
    {
        return match ($isCovered) {
            true => 'Cubierto',
            false => 'No cubierto',
            default => 'Sin dato',
        };
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     category: string,
     *     label: string,
     *     detail: string,
     *     coverage: bool|null,
     *     coverage_label: string,
     *     status: string,
     *     selectable: bool
     * }>
     */
    private static function associatedServiceItemsForManagement(OperationCoordinationService $record): Collection
    {
        $items = collect();

        $record->telemedicinePatientMedications()
            ->orderBy('id')
            ->with('operationInventory:id,is_covered')
            ->get(['id', 'medicine', 'indications', 'status', 'is_covered', 'operation_inventory_id'])
            ->each(function (TelemedicinePatientMedications $item) use ($items): void {
                $coverage = self::coverageValue('MEDICAMENTOS', $item);
                $items->push([
                    'key' => 'medication:'.$item->id,
                    'category' => 'Medicamento',
                    'label' => (string) ($item->medicine ?? 'Medicamento sin nombre'),
                    'detail' => (string) ($item->indications ?? '—'),
                    'coverage' => $coverage,
                    'coverage_label' => self::coverageLabel($coverage),
                    'status' => (string) ($item->status ?? '—'),
                    'selectable' => ($item->status ?? null) !== 'EN GESTION',
                ]);
            });

        $record->telemedicinePatientLabs()
            ->orderBy('id')
            ->get(['id', 'laboratory', 'type', 'status'])
            ->each(function (TelemedicinePatientLab $item) use ($items): void {
                $coverage = self::coverageValue('LABORATORIOS', $item);
                $items->push([
                    'key' => 'lab:'.$item->id,
                    'category' => 'Laboratorio',
                    'label' => (string) ($item->laboratory ?? 'Laboratorio sin nombre'),
                    'detail' => (string) ($item->type ?? '—'),
                    'coverage' => $coverage,
                    'coverage_label' => self::coverageLabel($coverage),
                    'status' => (string) ($item->status ?? '—'),
                    'selectable' => ($item->status ?? null) !== 'EN GESTION',
                ]);
            });

        $record->telemedicinePatientStudies()
            ->orderBy('id')
            ->get(['id', 'study', 'type', 'status'])
            ->each(function (TelemedicinePatientStudy $item) use ($items): void {
                $coverage = self::coverageValue('IMAGENOLOGIA', $item);
                $items->push([
                    'key' => 'study:'.$item->id,
                    'category' => 'Estudio',
                    'label' => (string) ($item->study ?? 'Estudio sin nombre'),
                    'detail' => (string) ($item->type ?? '—'),
                    'coverage' => $coverage,
                    'coverage_label' => self::coverageLabel($coverage),
                    'status' => (string) ($item->status ?? '—'),
                    'selectable' => ($item->status ?? null) !== 'EN GESTION',
                ]);
            });

        $record->telemedicinePatientSpecialties()
            ->orderBy('id')
            ->get(['id', 'specialty', 'type', 'status'])
            ->each(function (TelemedicinePatientSpecialty $item) use ($items): void {
                $coverage = self::coverageValue('ESPECIALISTA', $item);
                $items->push([
                    'key' => 'specialty:'.$item->id,
                    'category' => 'Especialista',
                    'label' => (string) ($item->specialty ?? 'Especialidad sin nombre'),
                    'detail' => (string) ($item->type ?? '—'),
                    'coverage' => $coverage,
                    'coverage_label' => self::coverageLabel($coverage),
                    'status' => (string) ($item->status ?? '—'),
                    'selectable' => ($item->status ?? null) !== 'EN GESTION',
                ]);
            });

        return $items;
    }

    private static function manageServiceItemsContextHeader(OperationCoordinationService $record): HtmlString
    {
        return new HtmlString(
            '<div class="rounded-2xl border border-black/[0.06] bg-zinc-50/90 px-4 py-3.5 dark:border-white/[0.08] dark:bg-zinc-900/90">'
            .'<div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">'
            .'<p><span class="font-semibold text-gray-900 dark:text-white">Paciente:</span> '.e($record->patient ?? '—').'</p>'
            .'<p><span class="font-semibold text-gray-900 dark:text-white">Referencia:</span> '.e($record->reference_number ?? '—')
            .' · <span class="font-semibold text-gray-900 dark:text-white">Servicio:</span> '.e($record->specific_service ?? $record->servicie ?? '—').'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">Seleccione ítems pendientes. Los cubiertos habilitan orden de servicio; los no cubiertos requieren cotización.</p>'
            .'</div>'
            .'</div>'
        );
    }

    private static function manageServiceEmptyState(): HtmlString
    {
        return new HtmlString(
            '<div class="rounded-2xl border border-dashed border-gray-300/90 bg-gradient-to-br from-gray-50 to-white px-6 py-8 text-center dark:border-white/15 dark:from-white/5 dark:to-zinc-900/40">'
            .'<p class="text-base font-semibold text-gray-900 dark:text-white">No hay ítems pendientes por gestionar</p>'
            .'<p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Todos los ítems asociados ya están en gestión o aún no se han registrado para esta coordinación.</p>'
            .'</div>'
        );
    }

    private static function manageServiceMixedTypesWarning(): HtmlString
    {
        return new HtmlString(
            '<div class="rounded-2xl border border-amber-200/90 bg-gradient-to-br from-amber-50/95 to-white px-4 py-4 text-sm text-amber-950 shadow-sm dark:border-amber-500/30 dark:from-amber-950/35 dark:to-zinc-900/90 dark:text-amber-50">'
            .'<p class="font-semibold">Tipos de servicio mezclados</p>'
            .'<p class="mt-1 leading-relaxed opacity-90">Los ítems cubiertos seleccionados pertenecen a distintos tipos de servicio. Seleccione ítems del mismo tipo para crear una única orden de servicio.</p>'
            .'</div>'
        );
    }

    private static function manageServiceMixedQuoteTypesWarning(): HtmlString
    {
        return new HtmlString(
            '<div class="rounded-2xl border border-amber-200/90 bg-gradient-to-br from-amber-50/95 to-white px-4 py-4 text-sm text-amber-950 shadow-sm dark:border-amber-500/30 dark:from-amber-950/35 dark:to-zinc-900/90 dark:text-amber-50">'
            .'<p class="font-semibold">Tipos de servicio mezclados en ítems no cubiertos</p>'
            .'<p class="mt-1 leading-relaxed opacity-90">Los ítems no cubiertos seleccionados pertenecen a distintos tipos de servicio. Seleccione ítems del mismo tipo para generar una única cotización.</p>'
            .'</div>'
        );
    }

    private static function manageServiceNonCoveredItemsNotice(): HtmlString
    {
        return new HtmlString(
            '<div class="rounded-2xl border border-rose-200/90 bg-gradient-to-br from-rose-50/95 via-white to-white px-5 py-4 text-sm text-rose-950 shadow-sm dark:border-rose-500/30 dark:from-rose-950/35 dark:via-zinc-900/90 dark:to-zinc-900/90 dark:text-rose-50">'
            .'<p class="font-semibold">Cotización obligatoria</p>'
            .'<p class="mt-1 leading-relaxed opacity-90">Debe registrar la cotización de los ítems no cubiertos seleccionados antes de confirmar la gestión.</p>'
            .'</div>'
        );
    }

    private static function manageServiceNonCoveredItemsTable(OperationCoordinationService $record, mixed $selectedKeys): HtmlString
    {
        return self::manageServiceSelectedItemsTable(
            $record,
            self::nonCoveredSelectedManagementItemKeys($record, $selectedKeys)
        );
    }

    private static function formatManageQuoteAmountPreview(?float $amount, string $currency = 'USD'): string
    {
        if ($amount === null) {
            return '—';
        }

        $prefix = $currency === 'VES' ? 'Bs. ' : 'US$ ';

        return $prefix.number_format($amount, 2, '.', ',');
    }

    private static function manageQuoteSummaryPanel(Get $get): HtmlString
    {
        $subtotal = self::manageQuoteSubtotal($get('manage_quote_costo_dolares'));
        $porcentaje = self::decimalOrNull($get('manage_quote_porcentaje_ganancia')) ?? 0.0;
        $total = self::manageQuoteTotal(
            $get('manage_quote_costo_dolares'),
            $get('manage_quote_porcentaje_ganancia')
        );
        $bcvRate = self::decimalOrNull($get('manage_quote_bcv_rate'));
        $ganancia = ($subtotal !== null && $total !== null) ? round($total - $subtotal, 2) : null;
        $totalBs = ($total !== null && $bcvRate !== null) ? round($total * $bcvRate, 2) : null;

        $rows = [
            [
                'label' => 'Costo base',
                'value' => self::formatManageQuoteAmountPreview($subtotal),
                'tone' => 'slate',
            ],
            [
                'label' => 'Ganancia ('.number_format($porcentaje, 2, '.', '').'%)',
                'value' => self::formatManageQuoteAmountPreview($ganancia),
                'tone' => 'amber',
            ],
        ];

        $html = '<div class="fi-manage-quote-summary h-full rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/95 via-white to-white p-4 shadow-sm dark:border-amber-500/25 dark:from-amber-950/30 dark:via-zinc-900/95 dark:to-zinc-900/90">'
            .'<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-800/80 dark:text-amber-200/70">Resumen de cotización</p>'
            .'<div class="mt-3 space-y-2.5">';

        foreach ($rows as $row) {
            $html .= self::manageQuoteSummaryRow($row['label'], $row['value'], $row['tone']);
        }

        $html .= '</div>'
            .'<div class="mt-4 rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/95 to-white px-4 py-3.5 dark:border-emerald-500/30 dark:from-emerald-950/35 dark:to-zinc-900/90">'
            .'<p class="text-xs font-medium text-emerald-800/80 dark:text-emerald-200/75">Total cotización</p>'
            .'<p class="mt-1 text-2xl font-bold tracking-tight text-emerald-950 dark:text-emerald-50">'.e(self::formatManageQuoteAmountPreview($total)).'</p>'
            .'<p class="mt-1 text-sm font-medium text-emerald-800/70 dark:text-emerald-200/65">'.e(self::formatManageQuoteAmountPreview($totalBs, 'VES')).'</p>'
            .'<p class="mt-2 text-[11px] leading-relaxed text-emerald-900/60 dark:text-emerald-100/55">Costo base + ganancia aplicada, convertido con la tasa BCV del día.</p>'
            .'</div>'
            .'</div>';

        return new HtmlString($html);
    }

    private static function manageQuoteSummaryRow(string $label, string $value, string $tone): string
    {
        $toneClasses = match ($tone) {
            'amber' => 'border-amber-200/70 bg-amber-50/50 text-amber-950 dark:border-amber-500/20 dark:bg-amber-950/20 dark:text-amber-50',
            default => 'border-black/[0.05] bg-white/70 text-zinc-900 dark:border-white/[0.08] dark:bg-zinc-900/50 dark:text-zinc-50',
        };

        return '<div class="flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 '.$toneClasses.'">'
            .'<span class="text-xs font-medium opacity-80">'.e($label).'</span>'
            .'<span class="text-sm font-semibold tabular-nums">'.e($value).'</span>'
            .'</div>';
    }

    private static function manageQuoteSubtotal(mixed $costoDolares): ?float
    {
        $costo = self::decimalOrNull($costoDolares);

        if ($costo === null) {
            return null;
        }

        return round($costo, 2);
    }

    private static function manageQuoteTotal(mixed $costoDolares, mixed $porcentajeGanancia): ?float
    {
        $subtotal = self::manageQuoteSubtotal($costoDolares);

        if ($subtotal === null) {
            return null;
        }

        $porcentaje = self::decimalOrNull($porcentajeGanancia) ?? 0.0;

        return round($subtotal + ($subtotal * $porcentaje / 100), 2);
    }

    private static function managementCategoryBadgeClass(string $category): string
    {
        return match ($category) {
            'Medicamento' => 'bg-violet-100 text-violet-800 ring-violet-300/70 dark:bg-violet-500/15 dark:text-violet-100 dark:ring-violet-400/30',
            'Laboratorio' => 'bg-cyan-100 text-cyan-900 ring-cyan-300/70 dark:bg-cyan-500/15 dark:text-cyan-100 dark:ring-cyan-400/30',
            'Estudio' => 'bg-indigo-100 text-indigo-900 ring-indigo-300/70 dark:bg-indigo-500/15 dark:text-indigo-100 dark:ring-indigo-400/30',
            'Especialista' => 'bg-fuchsia-100 text-fuchsia-900 ring-fuchsia-300/70 dark:bg-fuchsia-500/15 dark:text-fuchsia-100 dark:ring-fuchsia-400/30',
            default => 'bg-gray-100 text-gray-800 ring-gray-300/70 dark:bg-white/10 dark:text-gray-100 dark:ring-white/20',
        };
    }

    private static function managementCoverageBadgeClass(?bool $coverage): string
    {
        return match ($coverage) {
            true => 'bg-emerald-100 text-emerald-800 ring-emerald-300/80 dark:bg-emerald-500/20 dark:text-emerald-100 dark:ring-emerald-500/30',
            false => 'bg-rose-100 text-rose-800 ring-rose-300/80 dark:bg-rose-500/20 dark:text-rose-100 dark:ring-rose-500/30',
            default => 'bg-gray-100 text-gray-700 ring-gray-300/80 dark:bg-white/10 dark:text-gray-200 dark:ring-white/20',
        };
    }

    private static function managementStatusBadgeClass(string $status): string
    {
        $normalized = mb_strtoupper(trim($status));

        return match ($normalized) {
            'FINALIZADO' => 'bg-emerald-100 text-emerald-800 ring-emerald-300/80 dark:bg-emerald-500/20 dark:text-emerald-100',
            'PENDIENTE' => 'bg-rose-100 text-rose-800 ring-rose-300/80 dark:bg-rose-500/20 dark:text-rose-100',
            'EN GESTION' => 'bg-amber-100 text-amber-900 ring-amber-300/80 dark:bg-amber-500/20 dark:text-amber-100',
            default => 'bg-slate-100 text-slate-700 ring-slate-300/80 dark:bg-white/10 dark:text-gray-200',
        };
    }

    /**
     * @param  Collection<int, array{key: string, category: string, label: string, detail: string, coverage: bool|null, coverage_label: string, status: string, selectable: bool}>  $items
     */
    private static function renderManagementItemsTable(Collection $items, bool $includeStatus = true): HtmlString
    {
        if ($items->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-2xl border border-dashed border-gray-300/90 bg-gray-50/70 px-4 py-6 text-center text-sm text-gray-600 dark:border-white/15 dark:bg-white/5 dark:text-gray-300">'
                .'No hay ítems para mostrar.'
                .'</div>'
            );
        }

        $rows = $items->map(function (array $item) use ($includeStatus): string {
            $categoryClass = self::managementCategoryBadgeClass($item['category']);
            $coverageClass = self::managementCoverageBadgeClass($item['coverage']);
            $statusClass = self::managementStatusBadgeClass($item['status']);

            $row = '<tr class="border-b border-gray-100 transition-colors hover:bg-gray-50/80 last:border-0 dark:border-white/10 dark:hover:bg-white/[0.03]">'
                .'<td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset '.$categoryClass.'">'.e($item['category']).'</span></td>'
                .'<td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">'.e($item['label']).'</td>'
                .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300">'.e($item['detail']).'</td>'
                .'<td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.$coverageClass.'">'.e($item['coverage_label']).'</span></td>';

            if ($includeStatus) {
                $row .= '<td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.$statusClass.'">'.e($item['status']).'</span></td>';
            }

            return $row.'</tr>';
        })->implode('');

        $statusHeader = $includeStatus
            ? '<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Estatus</th>'
            : '';

        return new HtmlString(
            '<div class="fi-manage-service-items-table overflow-hidden rounded-2xl border border-gray-200/90 shadow-sm dark:border-white/10">'
            .'<div class="overflow-x-auto">'
            .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
            .'<thead class="bg-gradient-to-r from-gray-50 to-slate-50/90 dark:from-white/5 dark:to-white/[0.02]"><tr>'
            .'<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo</th>'
            .'<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ítem</th>'
            .'<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Descripción</th>'
            .'<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cobertura</th>'
            .$statusHeader
            .'</tr></thead>'
            .'<tbody class="bg-white/80 dark:bg-transparent">'.$rows.'</tbody>'
            .'</table>'
            .'</div>'
            .'</div>'
        );
    }

    private static function manageServiceSelectableOptions(OperationCoordinationService $record): array
    {
        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => $item['selectable'])
            ->mapWithKeys(fn (array $item): array => [$item['key'] => $item['category'].': '.$item['label']])
            ->all();
    }

    private static function manageServiceSelectableDescriptions(OperationCoordinationService $record): array
    {
        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => $item['selectable'])
            ->mapWithKeys(fn (array $item): array => [
                $item['key'] => $item['category'].': '.$item['label'].' · '.$item['coverage_label'].' · '.$item['status'],
            ])
            ->all();
    }

    private static function associatedServiceItemsOverviewTable(OperationCoordinationService $record): HtmlString
    {
        return self::renderManagementItemsTable(self::associatedServiceItemsForManagement($record));
    }

    private static function manageServiceSelectedItemsTable(OperationCoordinationService $record, mixed $selectedKeys): HtmlString
    {
        $keys = is_array($selectedKeys) ? $selectedKeys : [];

        if ($keys === []) {
            return new HtmlString(
                '<div class="rounded-2xl border border-dashed border-sky-200/80 bg-gradient-to-br from-sky-50/80 to-white px-5 py-6 text-center dark:border-sky-500/20 dark:from-sky-950/20 dark:to-zinc-900/40">'
                .'<p class="text-sm font-medium text-sky-950 dark:text-sky-100">Seleccione al menos un ítem para ver la vista previa de gestión.</p>'
                .'</div>'
            );
        }

        $items = self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => in_array($item['key'], $keys, true))
            ->values();

        if ($items->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-2xl border border-dashed border-amber-200/80 bg-amber-50/70 px-5 py-6 text-center text-sm text-amber-950 dark:border-amber-500/25 dark:bg-amber-950/20 dark:text-amber-100">'
                .'Los ítems seleccionados no están disponibles para esta coordinación.'
                .'</div>'
            );
        }

        return self::renderManagementItemsTable($items, includeStatus: false);
    }

    private static function manageServiceCoveredItemsNotice(OperationCoordinationService $record, mixed $selectedKeys): HtmlString
    {
        $coveredKeys = self::coveredSelectedManagementItemKeys($record, $selectedKeys);
        $nonCoveredCount = collect(is_array($selectedKeys) ? $selectedKeys : [])
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->reject(fn (string $key): bool => in_array($key, $coveredKeys, true))
            ->count();

        $message = 'Se creará una orden de servicio únicamente para los ítems cubiertos seleccionados.';

        if ($nonCoveredCount > 0) {
            $message .= ' Los ítems no cubiertos seleccionados pasarán a gestión sin orden de servicio.';
        }

        return new HtmlString(
            '<div class="rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/95 via-white to-white px-5 py-4 text-sm text-emerald-950 shadow-sm dark:border-emerald-500/30 dark:from-emerald-950/35 dark:via-zinc-900/90 dark:to-zinc-900/90 dark:text-emerald-50">'
            .'<p class="font-semibold">Próximo paso: orden de servicio</p>'
            .'<p class="mt-1 leading-relaxed opacity-90">'.e($message).'</p>'
            .'</div>'
        );
    }

    private static function manageServiceOrderTypeBadge(?string $serviceOrderType): HtmlString
    {
        if ($serviceOrderType === null) {
            return new HtmlString(
                '<div class="rounded-2xl border border-amber-200/90 bg-gradient-to-br from-amber-50/95 to-white px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:from-amber-950/30 dark:to-zinc-900/90 dark:text-amber-50">'
                .'No se detectó un tipo de servicio homogéneo entre los ítems cubiertos.'
                .'</div>'
            );
        }

        return new HtmlString(
            '<div class="inline-flex items-center gap-2 rounded-2xl border border-primary-200/80 bg-gradient-to-r from-primary-50/95 to-white px-4 py-3 text-sm font-semibold text-primary-950 shadow-sm dark:border-primary-500/30 dark:from-primary-950/40 dark:to-zinc-900/90 dark:text-primary-50">'
            .'<span class="inline-flex h-2 w-2 rounded-full bg-primary-500"></span>'
            .'Tipo de orden detectado: <span class="ml-1 uppercase tracking-wide">'.e($serviceOrderType).'</span>'
            .'</div>'
        );
    }

    private static function manageServiceCoveredItemsTable(OperationCoordinationService $record, mixed $selectedKeys): HtmlString
    {
        $coveredKeys = self::coveredSelectedManagementItemKeys($record, $selectedKeys);

        return self::manageServiceSelectedItemsTable($record, $coveredKeys);
    }

    private static function serviceOrderTypeFromManagementKey(string $key): ?string
    {
        if (! str_contains($key, ':')) {
            return null;
        }

        [$type] = explode(':', $key, 2);

        return match ($type) {
            'medication' => 'MEDICAMENTOS',
            'lab' => 'LABORATORIOS',
            'study' => 'IMAGENOLOGIA',
            'specialty' => 'ESPECIALISTA',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private static function nonCoveredSelectedManagementItemKeys(OperationCoordinationService $record, mixed $selectedKeys): array
    {
        $keys = is_array($selectedKeys) ? $selectedKeys : [];

        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => in_array($item['key'], $keys, true) && $item['coverage'] === false)
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function coveredSelectedManagementItemKeys(OperationCoordinationService $record, mixed $selectedKeys): array
    {
        $keys = is_array($selectedKeys) ? $selectedKeys : [];

        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => in_array($item['key'], $keys, true) && $item['coverage'] === true)
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keys
     */
    private static function resolveServiceOrderTypeFromManagementKeys(array $keys): ?string
    {
        $types = collect($keys)
            ->map(fn (string $key): ?string => self::serviceOrderTypeFromManagementKey($key))
            ->filter()
            ->unique()
            ->values();

        return $types->count() === 1 ? $types->first() : null;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, int>
     */
    private static function managementKeysToNumericIds(array $keys): array
    {
        return collect($keys)
            ->map(function (string $key): ?int {
                if (! str_contains($key, ':')) {
                    return null;
                }

                [, $id] = explode(':', $key, 2);
                $id = (int) $id;

                return $id > 0 ? $id : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private static function selectedServiceOrderRecordsByType(
        OperationCoordinationService $record,
        array $ids,
        string $type
    ): EloquentCollection {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return new EloquentCollection;
        }

        return match ($type) {
            'MEDICAMENTOS' => TelemedicinePatientMedications::query()
                ->where('operation_coordination_service_id', $record->id)
                ->whereKey($ids)
                ->with('operationInventory:id,is_covered')
                ->get(),
            'IMAGENOLOGIA' => TelemedicinePatientStudy::query()
                ->where('operation_coordination_service_id', $record->id)
                ->whereKey($ids)
                ->get(),
            'LABORATORIOS' => TelemedicinePatientLab::query()
                ->where('operation_coordination_service_id', $record->id)
                ->whereKey($ids)
                ->get(),
            'ESPECIALISTA' => TelemedicinePatientSpecialty::query()
                ->where('operation_coordination_service_id', $record->id)
                ->whereKey($ids)
                ->get(),
            default => new EloquentCollection,
        };
    }

    private static function manageSelectedServiceItems(OperationCoordinationService $record, array $data): mixed
    {
        $selectedKeys = array_values(array_filter(
            (array) ($data['managed_service_item_keys'] ?? []),
            fn (mixed $key): bool => is_string($key) && $key !== ''
        ));

        if ($selectedKeys === []) {
            Notification::make()
                ->title('Gestionar servicio')
                ->body('Seleccione al menos un ítem para gestionar.')
                ->warning()
                ->send();

            return null;
        }

        $coveredKeys = self::coveredSelectedManagementItemKeys($record, $selectedKeys);
        $nonCoveredKeys = self::nonCoveredSelectedManagementItemKeys($record, $selectedKeys);
        $serviceOrderType = self::resolveServiceOrderTypeFromManagementKeys($coveredKeys);
        $quoteType = self::resolveServiceOrderTypeFromManagementKeys($nonCoveredKeys);
        $shouldCreateServiceOrder = $coveredKeys !== [] && $serviceOrderType !== null;
        $shouldCreateQuote = $nonCoveredKeys !== [] && $quoteType !== null;

        if ($coveredKeys !== [] && $serviceOrderType === null) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('Los ítems cubiertos seleccionados pertenecen a distintos tipos de servicio. Se gestionarán los ítems, pero no se creará la orden.')
                ->warning()
                ->send();
        }

        if ($nonCoveredKeys !== [] && $quoteType === null) {
            Notification::make()
                ->title('Cotización')
                ->body('Los ítems no cubiertos seleccionados pertenecen a distintos tipos de servicio. Se gestionarán los ítems, pero no se registrará la cotización.')
                ->warning()
                ->send();
        }

        if ($shouldCreateQuote) {
            $costoUsd = self::decimalOrNull($data['manage_quote_costo_dolares'] ?? null);

            if ($costoUsd === null || $costoUsd <= 0) {
                Notification::make()
                    ->title('Cotización')
                    ->body('Indique un costo en dólares mayor a cero para los ítems no cubiertos.')
                    ->warning()
                    ->send();

                return null;
            }

            $bcvRate = self::decimalOrNull($data['manage_quote_bcv_rate'] ?? null);

            if ($bcvRate === null || $bcvRate <= 0) {
                Notification::make()
                    ->title('Cotización')
                    ->body('No fue posible obtener una tasa BCV válida. Intente nuevamente.')
                    ->warning()
                    ->send();

                return null;
            }
        }

        $managedCount = 0;
        $quoteItemsPayload = $shouldCreateQuote
            ? self::buildManageQuoteItemsPayload($record, $nonCoveredKeys)
            : [];

        DB::transaction(function () use (
            $record,
            $selectedKeys,
            $data,
            $shouldCreateQuote,
            $quoteType,
            $quoteItemsPayload,
            &$managedCount
        ): void {
            foreach ($selectedKeys as $key) {
                if (! str_contains($key, ':')) {
                    continue;
                }

                [$type, $id] = explode(':', $key, 2);
                $id = (int) $id;

                if ($id <= 0) {
                    continue;
                }

                $updated = match ($type) {
                    'medication' => TelemedicinePatientMedications::query()
                        ->where('operation_coordination_service_id', $record->id)
                        ->whereKey($id)
                        ->where('status', '!=', 'EN GESTION')
                        ->update(['status' => 'EN GESTION']),
                    'lab' => TelemedicinePatientLab::query()
                        ->where('operation_coordination_service_id', $record->id)
                        ->whereKey($id)
                        ->where('status', '!=', 'EN GESTION')
                        ->update(['status' => 'EN GESTION']),
                    'study' => TelemedicinePatientStudy::query()
                        ->where('operation_coordination_service_id', $record->id)
                        ->whereKey($id)
                        ->where('status', '!=', 'EN GESTION')
                        ->update(['status' => 'EN GESTION']),
                    'specialty' => TelemedicinePatientSpecialty::query()
                        ->where('operation_coordination_service_id', $record->id)
                        ->whereKey($id)
                        ->where('status', '!=', 'EN GESTION')
                        ->update(['status' => 'EN GESTION']),
                    default => 0,
                };

                $managedCount += (int) $updated;
            }

            if ($managedCount > 0) {
                $record->status = 'EN GESTION';
                $record->updated_by = Auth::user()?->name;
                $record->save();
            }

            if ($shouldCreateQuote && $quoteItemsPayload !== []) {
                self::persistManageQuote($record, $data, $quoteType, $quoteItemsPayload);
            }
        });

        if ($managedCount === 0) {
            Notification::make()
                ->title('Gestionar servicio')
                ->body('No fue posible gestionar los ítems seleccionados. Verifique que sigan pendientes.')
                ->warning()
                ->send();

            return null;
        }

        $body = $managedCount === 1
            ? 'Se gestionó 1 ítem y la coordinación pasó a EN GESTION.'
            : 'Se gestionaron '.$managedCount.' ítems y la coordinación pasó a EN GESTION.';

        if ($shouldCreateServiceOrder) {
            $orderCreated = self::createServiceOrderFromManageModal($record, $data, $coveredKeys, $serviceOrderType);

            if ($orderCreated) {
                $body .= ' Se creó la orden de servicio para los ítems cubiertos seleccionados.';
            }
        }

        if ($shouldCreateQuote) {
            $body .= ' Se registró la cotización para los ítems no cubiertos seleccionados.';
        }

        Notification::make()
            ->title('Ítems gestionados')
            ->body($body)
            ->success()
            ->send();

        return null;
    }

    /**
     * @param  array<int, string>  $coveredKeys
     */
    private static function createServiceOrderFromManageModal(
        OperationCoordinationService $record,
        array $data,
        array $coveredKeys,
        string $serviceOrderType
    ): bool {
        $records = self::selectedServiceOrderRecordsByType(
            $record,
            self::managementKeysToNumericIds($coveredKeys),
            $serviceOrderType
        );

        if ($records->isEmpty()) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('Los ítems cubiertos seleccionados no están disponibles para crear la orden.')
                ->warning()
                ->send();

            return false;
        }

        $payload = [
            'order_number' => $data['order_number'] ?? null,
            'telemedicine_priority_id' => $data['telemedicine_priority_id'] ?? $record->telemedicine_priority_id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'supplier_external' => $data['supplier_external'] ?? null,
            'operation_inventory_ubication_id' => $data['operation_inventory_ubication_id'] ?? null,
            'description' => $data['service_order_description'] ?? null,
            'service_type' => $serviceOrderType,
            'status' => 'EN GESTION',
            'observations' => $data['service_order_observations'] ?? null,
            'created_by' => Auth::user()?->name,
            'updated_by' => Auth::user()?->name,
        ];

        if ($serviceOrderType === 'MEDICAMENTOS') {
            $payload['medications_list'] = $records->map(fn (TelemedicinePatientMedications $item): array => [
                'quantity' => 1,
                'indications' => $item->indications ?? null,
            ])->values()->all();
        }

        OperationServiceOrderController::create($payload, $record->toArray(), $records);

        $record->service_order_number = $data['order_number'] ?? $record->service_order_number;
        $record->updated_by = Auth::user()?->name;
        $record->save();

        return true;
    }

    /**
     * @param  array<int, string>  $nonCoveredKeys
     * @return array<int, array<string, mixed>>
     */
    private static function buildManageQuoteItemsPayload(OperationCoordinationService $record, array $nonCoveredKeys): array
    {
        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => in_array($item['key'], $nonCoveredKeys, true))
            ->map(fn (array $item): array => [
                'key' => $item['key'],
                'category' => $item['category'],
                'label' => $item['label'],
                'detail' => $item['detail'],
                'coverage_label' => $item['coverage_label'],
                'status' => $item['status'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private static function persistManageQuote(
        OperationCoordinationService $record,
        array $data,
        string $quoteType,
        array $items
    ): void {
        $costoUsd = self::decimalOrNull($data['manage_quote_costo_dolares'] ?? null);
        $bcvRate = self::decimalOrNull($data['manage_quote_bcv_rate'] ?? null);

        if ($costoUsd === null || $costoUsd <= 0 || $bcvRate === null || $bcvRate <= 0 || $items === []) {
            throw new \RuntimeException('No fue posible registrar la cotización con los datos proporcionados.');
        }

        $porcentaje = self::decimalOrNull($data['manage_quote_porcentaje_ganancia'] ?? 0) ?? 0.0;
        $costoBs = self::decimalOrNull($data['manage_quote_costo_bolivares'] ?? null) ?? round($costoUsd * $bcvRate, 2);
        $subtotal = self::manageQuoteSubtotal($costoUsd) ?? 0.0;
        $total = self::manageQuoteTotal($costoUsd, $porcentaje) ?? 0.0;

        $quote = OperationQuoteGenerator::query()->create([
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'operation_coordination_service_id' => $record->id,
            'type_service' => $quoteType,
            'status' => OperationQuoteGenerator::STATUS_PENDING,
            'items' => $items,
            'costo_dolares' => $subtotal,
            'costo_bolivares' => $costoBs,
            'porcentaje_ganancia' => $porcentaje,
            'subtotal' => $subtotal,
            'total' => $total,
            'created_by' => Auth::user()?->name ?? 'system',
            'updated_by' => Auth::user()?->name,
        ]);

        $quote->update([
            'quote_pdf_path' => OperationQuoteGeneratorPdfService::store($quote, $record, $bcvRate),
        ]);

        $record->neto = $subtotal;
        $record->porcen_tdec = $porcentaje;
        $record->quote_price = $total;
        $record->updated_by = Auth::user()?->name;
        $record->save();
    }

    /**
     * @param  array<int, string>  $nonCoveredKeys
     */
    private static function createQuoteFromManageModal(
        OperationCoordinationService $record,
        array $data,
        array $nonCoveredKeys,
        string $quoteType
    ): bool {
        $items = self::buildManageQuoteItemsPayload($record, $nonCoveredKeys);

        if ($items === []) {
            Notification::make()
                ->title('Cotización')
                ->body('Los ítems no cubiertos seleccionados no están disponibles para registrar la cotización.')
                ->warning()
                ->send();

            return false;
        }

        try {
            self::persistManageQuote($record, $data, $quoteType, $items);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, OperationQuoteGenerator>
     */
    private static function coordinationQuotes(OperationCoordinationService $record): Collection
    {
        return OperationQuoteGenerator::query()
            ->where('operation_coordination_service_id', $record->id)
            ->latest('id')
            ->get();
    }

    private static function nextServiceOrderNumber(): string
    {
        return 'ORD-'.str_pad((string) (((int) (OperationServiceOrder::max('id') ?? 0)) + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private static function manageServiceQuoteFormDefaults(OperationCoordinationService $record): array
    {
        return [
            'quote_statuses' => self::coordinationQuotes($record)
                ->map(fn (OperationQuoteGenerator $quote): array => [
                    'quote_id' => $quote->id,
                    'status' => $quote->status ?? OperationQuoteGenerator::STATUS_PENDING,
                    'has_service_order' => filled($quote->operation_service_order_id),
                ])
                ->all(),
            'approved_quote_id' => null,
            'order_number' => self::nextServiceOrderNumber(),
            'telemedicine_priority_id' => $record->telemedicine_priority_id,
            'supplier_id' => null,
            'supplier_external' => null,
            'operation_inventory_ubication_id' => null,
            'service_order_description' => null,
            'service_order_observations' => null,
        ];
    }

    private static function renderCoordinationQuotesSummary(OperationCoordinationService $record): HtmlString
    {
        $quotes = self::coordinationQuotes($record);

        if ($quotes->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-2xl border border-dashed border-gray-300/80 px-4 py-3 text-sm text-gray-600 dark:border-white/15 dark:text-gray-300">No hay cotizaciones registradas para esta coordinación.</div>'
            );
        }

        $rows = $quotes->map(function (OperationQuoteGenerator $quote): string {
            $statusClass = match ($quote->status) {
                OperationQuoteGenerator::STATUS_APPROVED => 'bg-emerald-100 text-emerald-800 ring-emerald-300/70 dark:bg-emerald-500/20 dark:text-emerald-100',
                OperationQuoteGenerator::STATUS_REJECTED => 'bg-rose-100 text-rose-800 ring-rose-300/70 dark:bg-rose-500/20 dark:text-rose-100',
                default => 'bg-amber-100 text-amber-900 ring-amber-300/70 dark:bg-amber-500/20 dark:text-amber-100',
            };

            return '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                .'<td class="px-3 py-2 font-medium">#'.e((string) $quote->id).'</td>'
                .'<td class="px-3 py-2">'.e((string) $quote->type_service).'</td>'
                .'<td class="px-3 py-2">'.e(self::formatManageQuoteAmountPreview((float) $quote->total)).'</td>'
                .'<td class="px-3 py-2"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 '.$statusClass.'">'.e((string) ($quote->status ?? OperationQuoteGenerator::STATUS_PENDING)).'</span></td>'
                .'<td class="px-3 py-2">'.self::renderQuoteGeneratorPdfCell($quote).'</td>'
                .'<td class="px-3 py-2">'.e(optional($quote->created_at)->format('d/m/Y H:i') ?? '—').'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="overflow-x-auto rounded-2xl border border-black/[0.06] dark:border-white/10">'
            .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
            .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
            .'<th class="px-3 py-2 text-left font-semibold">ID</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Tipo</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Total</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Estatus</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Documento</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Creada</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>'
        );
    }

    private static function renderQuoteGeneratorPdfCell(OperationQuoteGenerator $quote): string
    {
        if (! filled($quote->quote_pdf_path)) {
            return '<span class="text-xs text-gray-500 dark:text-gray-400">Sin PDF</span>';
        }

        $pdfUrl = URL::to(Storage::url((string) $quote->quote_pdf_path));
        $downloadName = e(OperationQuoteGeneratorPdfService::filename($quote));

        return '<a href="'.e($pdfUrl).'" download="'.$downloadName.'" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-full border-b-2 border-primary-600 bg-primary-500/15 px-3 py-1.5 text-xs font-semibold text-primary-700 no-underline dark:border-primary-500 dark:bg-primary-500/25 dark:text-primary-300">'
            .'<svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>'
            .'Descargar'
            .'</a>';
    }

    private static function renderOperationQuotePreview(OperationQuoteGenerator $quote): HtmlString
    {
        $items = collect(is_array($quote->items) ? $quote->items : []);

        $itemRows = $items->map(function (array $item): string {
            return '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                .'<td class="px-3 py-2">'.e((string) ($item['category'] ?? '—')).'</td>'
                .'<td class="px-3 py-2 font-medium">'.e((string) ($item['label'] ?? '—')).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($item['detail'] ?? '—')).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($item['coverage_label'] ?? '—')).'</td>'
                .'</tr>';
        })->implode('');

        $itemsTable = $items->isEmpty()
            ? '<p class="text-sm text-gray-600 dark:text-gray-300">Sin ítems registrados en la cotización.</p>'
            : '<div class="overflow-x-auto rounded-xl border border-gray-200/90 dark:border-white/10">'
                .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
                .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
                .'<th class="px-3 py-2 text-left font-semibold">Categoría</th>'
                .'<th class="px-3 py-2 text-left font-semibold">Ítem</th>'
                .'<th class="px-3 py-2 text-left font-semibold">Detalle</th>'
                .'<th class="px-3 py-2 text-left font-semibold">Cobertura</th>'
                .'</tr></thead><tbody>'.$itemRows.'</tbody></table></div>';

        return new HtmlString(
            '<div class="space-y-4 rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50/70 via-white to-white p-4 dark:border-amber-500/20 dark:from-amber-950/20 dark:via-zinc-900/90 dark:to-zinc-900/90">'
            .'<div class="flex flex-wrap items-start justify-between gap-3">'
            .'<div><p class="text-xs font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/70">Cotización #'.e((string) $quote->id).'</p>'
            .'<p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">'.e((string) $quote->type_service).'</p></div>'
            .'<div class="text-right"><p class="text-xs text-gray-500 dark:text-gray-400">Total</p>'
            .'<p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">'.e(self::formatManageQuoteAmountPreview((float) $quote->total)).'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">'.e(self::formatManageQuoteAmountPreview((float) $quote->costo_bolivares, 'VES')).'</p></div>'
            .'</div>'
            .'<div class="grid gap-3 sm:grid-cols-3">'
            .self::manageQuoteSummaryRow('Costo base', self::formatManageQuoteAmountPreview((float) $quote->costo_dolares), 'slate')
            .self::manageQuoteSummaryRow('Ganancia', number_format((float) $quote->porcentaje_ganancia, 2, '.', '').'%', 'amber')
            .self::manageQuoteSummaryRow('Subtotal', self::formatManageQuoteAmountPreview((float) $quote->subtotal), 'slate')
            .'</div>'
            .$itemsTable
            .'</div>'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $quoteStatuses
     */
    private static function hasApprovedQuotePendingOrderInForm(mixed $quoteStatuses): bool
    {
        if (! is_array($quoteStatuses)) {
            return false;
        }

        return collect($quoteStatuses)->contains(
            fn (array $entry): bool => ($entry['status'] ?? null) === OperationQuoteGenerator::STATUS_APPROVED
                && ! (bool) ($entry['has_service_order'] ?? false)
        );
    }

    private static function approvedQuoteServiceType(int $quoteId): ?string
    {
        if ($quoteId <= 0) {
            return null;
        }

        return OperationQuoteGenerator::query()->whereKey($quoteId)->value('type_service');
    }

    private static function approvedQuoteOrderNotice(int $quoteId): HtmlString
    {
        $quote = OperationQuoteGenerator::query()->find($quoteId);

        if (! $quote instanceof OperationQuoteGenerator) {
            return new HtmlString(
                '<div class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/25 dark:bg-amber-950/25 dark:text-amber-50">Seleccione una cotización aprobada para completar la orden de servicio.</div>'
            );
        }

        return new HtmlString(
            '<div class="rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/95 to-white px-4 py-3 text-sm text-emerald-950 dark:border-emerald-500/30 dark:from-emerald-950/35 dark:to-zinc-900/90 dark:text-emerald-50">'
            .'<p class="font-semibold">Orden para cotización #'.e((string) $quote->id).'</p>'
            .'<p class="mt-1 opacity-90">Tipo <strong>'.e((string) $quote->type_service).'</strong> · Total '.e(self::formatManageQuoteAmountPreview((float) $quote->total)).'. Los campos se completaron automáticamente y puede ajustarlos antes de guardar.</p>'
            .'</div>'
        );
    }

    private static function prefillServiceOrderFormFromQuote(Set $set, OperationCoordinationService $record, int $quoteId): void
    {
        $quote = OperationQuoteGenerator::query()->find($quoteId);

        if (! $quote instanceof OperationQuoteGenerator || filled($quote->operation_service_order_id)) {
            return;
        }

        $itemsCount = count(is_array($quote->items) ? $quote->items : []);

        $set('approved_quote_id', $quote->id);
        $set('order_number', self::nextServiceOrderNumber());
        $set('telemedicine_priority_id', $record->telemedicine_priority_id);
        $set('service_order_description', sprintf(
            'Orden por cotización #%d · %s · %d ítem(s) · Total %s',
            $quote->id,
            $quote->type_service,
            $itemsCount,
            self::formatManageQuoteAmountPreview((float) $quote->total)
        ));
        $set('service_order_observations', sprintf(
            'Generada automáticamente desde cotización #%d (costo base %s, ganancia %s%%).',
            $quote->id,
            self::formatManageQuoteAmountPreview((float) $quote->subtotal),
            number_format((float) $quote->porcentaje_ganancia, 2, '.', '')
        ));
    }

    private static function saveManageServiceQuotes(OperationCoordinationService $record, array $data): mixed
    {
        $entries = is_array($data['quote_statuses'] ?? null) ? $data['quote_statuses'] : [];
        $approvedQuoteId = self::resolveApprovedQuoteIdForOrderCreation(
            $entries,
            (int) ($data['approved_quote_id'] ?? 0)
        );
        $shouldCreateOrder = self::hasApprovedQuotePendingOrderInForm($entries)
            && $approvedQuoteId > 0
            && collect($entries)->contains(
                fn (array $entry): bool => (int) ($entry['quote_id'] ?? 0) === $approvedQuoteId
                    && ($entry['status'] ?? null) === OperationQuoteGenerator::STATUS_APPROVED
                    && ! (bool) ($entry['has_service_order'] ?? false)
            );

        if ($shouldCreateOrder) {
            if (blank($data['order_number'] ?? null) || blank($data['service_order_description'] ?? null)) {
                Notification::make()
                    ->title('Orden de servicio')
                    ->body('Complete número y descripción de la orden para la cotización aprobada.')
                    ->warning()
                    ->send();

                return null;
            }
        }

        $ordersCreated = 0;

        DB::transaction(function () use ($record, $data, $entries, $shouldCreateOrder, $approvedQuoteId, &$ordersCreated): void {
            foreach ($entries as $entry) {
                $quoteId = (int) ($entry['quote_id'] ?? 0);
                $quote = OperationQuoteGenerator::query()->find($quoteId);

                if (! $quote instanceof OperationQuoteGenerator) {
                    continue;
                }

                if (filled($quote->operation_service_order_id)) {
                    continue;
                }

                $quote->status = (string) ($entry['status'] ?? OperationQuoteGenerator::STATUS_PENDING);
                $quote->updated_by = Auth::user()?->name;
                $quote->save();
            }

            if ($shouldCreateOrder) {
                $quote = OperationQuoteGenerator::query()->find($approvedQuoteId);

                if ($quote instanceof OperationQuoteGenerator && blank($quote->operation_service_order_id)) {
                    $orderId = self::createServiceOrderFromApprovedQuote($record, $data, $quote);

                    if ($orderId > 0) {
                        $quote->status = OperationQuoteGenerator::STATUS_APPROVED;
                        $quote->operation_service_order_id = $orderId;
                        $quote->updated_by = Auth::user()?->name;
                        $quote->save();
                        $ordersCreated++;
                    }
                }
            }
        });

        $body = 'Se actualizaron los estatus de las cotizaciones.';

        if ($ordersCreated > 0) {
            $body .= $ordersCreated === 1
                ? ' Se creó la orden de servicio vinculada a la cotización aprobada.'
                : ' Se crearon '.$ordersCreated.' órdenes de servicio.';
        }

        Notification::make()
            ->title('Cotizaciones gestionadas')
            ->body($body)
            ->success()
            ->send();

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private static function resolveApprovedQuoteIdForOrderCreation(array $entries, int $approvedQuoteId): int
    {
        if ($approvedQuoteId > 0) {
            return $approvedQuoteId;
        }

        foreach ($entries as $entry) {
            $entryQuoteId = (int) ($entry['quote_id'] ?? 0);

            if (
                $entryQuoteId > 0
                && ($entry['status'] ?? null) === OperationQuoteGenerator::STATUS_APPROVED
                && ! (bool) ($entry['has_service_order'] ?? false)
            ) {
                return $entryQuoteId;
            }
        }

        return 0;
    }

    private static function createServiceOrderFromApprovedQuote(
        OperationCoordinationService $record,
        array $data,
        OperationQuoteGenerator $quote
    ): int {
        $keys = collect(is_array($quote->items) ? $quote->items : [])
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();

        $records = self::selectedServiceOrderRecordsByType(
            $record,
            self::managementKeysToNumericIds($keys),
            (string) $quote->type_service
        );

        if ($records->isEmpty()) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('Los ítems de la cotización aprobada no están disponibles para crear la orden.')
                ->warning()
                ->send();

            return 0;
        }

        $payload = [
            'order_number' => $data['order_number'] ?? null,
            'telemedicine_priority_id' => $data['telemedicine_priority_id'] ?? $record->telemedicine_priority_id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'supplier_external' => $data['supplier_external'] ?? null,
            'operation_inventory_ubication_id' => $data['operation_inventory_ubication_id'] ?? null,
            'description' => $data['service_order_description'] ?? null,
            'service_type' => $quote->type_service,
            'status' => 'EN GESTION',
            'observations' => $data['service_order_observations'] ?? null,
            'created_by' => Auth::user()?->name,
            'updated_by' => Auth::user()?->name,
        ];

        if ($quote->type_service === 'MEDICAMENTOS') {
            $payload['medications_list'] = $records->map(fn (TelemedicinePatientMedications $item): array => [
                'quantity' => 1,
                'indications' => $item->indications ?? null,
            ])->values()->all();
        }

        OperationServiceOrderController::create($payload, $record->toArray(), $records);

        $order = OperationServiceOrder::query()
            ->where('operation_coordination_service_id', $record->id)
            ->where('order_number', (string) ($payload['order_number'] ?? ''))
            ->latest('id')
            ->first();

        if ($order instanceof OperationServiceOrder) {
            $order->associated_quote_pdf_path = self::resolveQuotePdfPathForOrder($quote, $record);
            $order->updated_by = Auth::user()?->name;
            $order->save();

            $record->service_order_number = $payload['order_number'];
            $record->updated_by = Auth::user()?->name;
            $record->save();

            return (int) $order->id;
        }

        return 0;
    }

    private static function resolveQuotePdfPathForOrder(
        OperationQuoteGenerator $quote,
        OperationCoordinationService $record
    ): ?string {
        if (filled($quote->quote_pdf_path)) {
            return (string) $quote->quote_pdf_path;
        }

        try {
            $subtotalUsd = (float) ($quote->subtotal ?? 0);
            $bcvRate = $subtotalUsd > 0
                ? ((float) ($quote->costo_bolivares ?? 0) / $subtotalUsd)
                : (self::referenciaTasaBcvDesdeApi() ?? 1.0);

            if ($bcvRate <= 0) {
                $bcvRate = self::referenciaTasaBcvDesdeApi() ?? 1.0;
            }

            $path = OperationQuoteGeneratorPdfService::store($quote, $record, $bcvRate);
            $quote->quote_pdf_path = $path;
            $quote->updated_by = Auth::user()?->name;
            $quote->save();

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function createServiceOrderFromWizard(OperationCoordinationService $record, array $data, array $quotePayload): void
    {
        $selectedIds = array_values(array_unique(array_map('intval', (array) ($data['service_order_item_ids'] ?? []))));

        if ($selectedIds === []) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('Active la creación y seleccione al menos un ítem antes de guardar.')
                ->warning()
                ->send();

            return;
        }

        $type = self::serviceOrderType($record);

        if ($type === null) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('No se detectó un tipo de servicio elegible para crear la orden.')
                ->warning()
                ->send();

            return;
        }

        $records = self::selectedServiceOrderRecords($record, $selectedIds);

        if ($records->isEmpty()) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('Los ítems seleccionados no están disponibles para esta coordinación.')
                ->warning()
                ->send();

            return;
        }

        $payload = [
            'order_number' => $data['order_number'] ?? null,
            'telemedicine_priority_id' => $data['telemedicine_priority_id'] ?? $record->telemedicine_priority_id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'supplier_external' => $data['supplier_external'] ?? null,
            'operation_inventory_ubication_id' => $data['operation_inventory_ubication_id'] ?? null,
            'description' => $data['service_order_description'] ?? null,
            'service_type' => $type,
            'status' => 'EN GESTION',
            'observations' => $data['service_order_observations'] ?? null,
            'created_by' => Auth::user()?->name,
            'updated_by' => Auth::user()?->name,
        ];

        $createQuote = (bool) ($data['create_associated_quote'] ?? false);
        if ($createQuote) {
            if (($quotePayload['quote_price_usd'] ?? null) === null || (float) $quotePayload['quote_price_usd'] <= 0) {
                Notification::make()
                    ->title('Cotización asociada')
                    ->body('Indique un precio en dólares mayor a cero para crear la cotización asociada.')
                    ->warning()
                    ->send();

                return;
            }

            if (($quotePayload['quote_bcv_rate'] ?? null) === null || (float) $quotePayload['quote_bcv_rate'] <= 0) {
                Notification::make()
                    ->title('Cotización asociada')
                    ->body('No fue posible obtener una tasa BCV válida. Intente nuevamente.')
                    ->warning()
                    ->send();

                return;
            }

            $payload['currency'] = 'USD';
            $payload['tasa_bcv'] = $quotePayload['quote_bcv_rate'];
            $payload['total_amount_usd'] = $quotePayload['quote_price_usd'];
            $payload['total_amount_ves'] = $quotePayload['quote_price_ves'] ?? round(
                ((float) $quotePayload['quote_price_usd']) * ((float) $quotePayload['quote_bcv_rate']),
                4
            );
        }

        if ($type === 'MEDICAMENTOS') {
            $payload['medications_list'] = $records->map(fn (TelemedicinePatientMedications $item): array => [
                'quantity' => 1,
                'indications' => $item->indications ?? null,
            ])->values()->all();
        }

        OperationServiceOrderController::create($payload, $record->toArray(), $records);

        $createdOrder = OperationServiceOrder::query()
            ->where('operation_coordination_service_id', $record->id)
            ->where('order_number', (string) ($payload['order_number'] ?? ''))
            ->latest('id')
            ->first();

        if ($createdOrder instanceof OperationServiceOrder) {
            self::persistGeneratedOrderDocuments(
                $createdOrder,
                $record,
                $data,
                $quotePayload,
                $createQuote
            );
        }

        $records->each(function ($item): void {
            $item->status = 'EN GESTION';
            $item->save();
        });

        $record->status = 'EN GESTION';
        $record->updated_by = Auth::user()?->name;
        $record->save();
    }

    private static function persistGeneratedOrderDocuments(
        OperationServiceOrder $order,
        OperationCoordinationService $coordination,
        array $data,
        array $quotePayload,
        bool $createQuote
    ): void {
        $disk = Storage::disk('public');
        $timestamp = now()->format('YmdHis');
        $safeOrder = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $order->order_number) ?: (string) $order->id;
        $baseDirectory = 'operation-service-orders/generated-pdf';

        $serviceOrderPdf = OperationServiceOrderPdfService::make($order)->output();
        $serviceOrderPath = $baseDirectory.'/service-order-'.$safeOrder.'-'.$timestamp.'.pdf';
        $disk->put($serviceOrderPath, $serviceOrderPdf);

        $quotePath = null;
        if ($createQuote) {
            $selectedItemNames = self::selectedServiceItemNames(
                $coordination,
                is_array($data['service_order_item_ids'] ?? null) ? $data['service_order_item_ids'] : []
            );

            $quoteData = [
                'service_label' => $selectedItemNames !== []
                    ? implode(' · ', $selectedItemNames)
                    : (string) ($data['type_service'] ?: ($coordination->specific_service ?: 'Servicio no especificado')),
                'price_usd' => (float) ($quotePayload['quote_price_usd'] ?? 0),
                'price_ves' => (float) ($quotePayload['quote_price_ves'] ?? 0),
                'bcv_rate' => (float) ($quotePayload['quote_bcv_rate'] ?? 0),
            ];

            $quotePdf = OperationServiceOrderQuotePdfService::make($order, $quoteData)->output();
            $quotePath = $baseDirectory.'/quote-'.$safeOrder.'-'.$timestamp.'.pdf';
            $disk->put($quotePath, $quotePdf);
        }

        $order->service_order_pdf_path = $serviceOrderPath;
        $order->associated_quote_pdf_path = $quotePath;
        $order->updated_by = Auth::user()?->name;
        $order->save();
    }
}
