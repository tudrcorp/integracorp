<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Tables;

use App\Models\OperationCoordinationService;
use App\Models\OperationTypeNegotiation;
use App\Models\OperationTypeService;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class OperationCoordinationServicesTable
{
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
                                        ->where('managed_by', 'TDG')
                                        ->where('status', 'ACTIVO')
                                        ->orderBy('full_name')
                                        ->get()
                                        ->mapWithKeys(fn (TelemedicineDoctor $doctor): array => [
                                            $doctor->id => $doctor->full_name.' — '.($doctor->specialty ?? '—'),
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
                : 'Documentos ingreso / egreso clínica')
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
            ->modalWidth(Width::FiveExtraLarge)
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
                    .'<p class="rounded-lg border border-primary-200/80 bg-primary-50/80 px-3 py-2 text-xs text-primary-950 dark:border-primary-500/30 dark:bg-primary-950/40 dark:text-primary-50">'
                    .'<span class="font-semibold">Asistente por pasos:</span> use <strong>Siguiente</strong> y <strong>Anterior</strong> para revisar todo. El área central hace scroll si no cabe en pantalla. Al final pulse <strong>Guardar cambios</strong>. '
                    .'El precio de cotización se recalcula al guardar: <span class="font-mono">neto + (neto × % TDEC ÷ 100)</span>.'
                    .'</p>'
                    .'</div>'
                );
            })
            ->modalIcon(Heroicon::OutlinedCurrencyDollar)
            ->modalIconColor('primary')
            ->modalWidth(Width::FiveExtraLarge)
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
                    '[&_.fi-modal-content]:max-h-[min(calc(92vh-10rem),48rem))]',
                    '[&_.fi-modal-content]:overflow-y-auto',
                    '[&_.fi-modal-content]:overscroll-contain',
                ]),
            ], merge: true)
            ->closeModalByClickingAway(false)
            ->fillForm(fn (OperationCoordinationService $record): array => [
                'type_service' => $record->type_service,
                'supplier_service' => $record->supplier_service,
                'farmadoc' => $record->farmadoc,
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
                                            ->orderBy('description')
                                            ->pluck('description', 'description')
                                            ->all()
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpanFull(),
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
                                            ->orderBy('name')
                                            ->limit(50)
                                            ->pluck('name', 'name')
                                            ->all()
                                    )
                                    ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? (string) $value : null)
                                    ->native(false)
                                    ->columnSpanFull(),
                                TextInput::make('farmadoc')
                                    ->label('Farmadoc')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
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
                                                    ->orderBy('description')
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
                                        TextInput::make('porcen_discount')
                                            ->label('Porcentaje de Descuento')
                                            ->numeric()
                                            ->prefix('%'),
                                        TextInput::make('price_discount')
                                            ->label('Precio de Descuento')
                                            ->numeric()
                                            ->prefix('US$')
                                            ->helperText('Puede ajustar el importe manualmente si difiere del porcentaje.'),
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
                        Section::make('Facturación')
                            ->description('Opcional: complete cuando exista factura.')
                            ->icon(Heroicon::OutlinedReceiptPercent)
                            ->iconColor('danger')
                            ->collapsed()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('bill_number')
                                            ->label('Número de Factura')
                                            ->maxLength(255),
                                        TextInput::make('bill_price')
                                            ->label('Precio de Factura')
                                            ->numeric()
                                            ->prefix('US$'),
                                        TextInput::make('bill_date')
                                            ->label('Fecha de Factura')
                                            ->maxLength(255)
                                            ->helperText('Texto o fecha tal como debe figurar en reportes.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
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

            ->heading('Listado de Coordinacion de Servicios')
            ->description('Lista de servicios coordinados en el sistema para la telemedicina, RETAIL y otros servicios')
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
                TextColumn::make('type_service')
                    ->label('Tipo de Servicio')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->tooltip('Edite en la acción «Negociación y precios».'),
                TextColumn::make('supplier_service')
                    ->label('Proveedor de Servicio')
                    ->searchable()
                    ->limit(28)
                    ->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('farmadoc')
                    ->label('Farmadoc')
                    ->searchable(),
                TextColumn::make('type_negotiation')
                    ->label('Tipo de Negociación')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('status_negotiation')
                    ->label('Estatus de Negociación')
                    ->searchable(),
                TextColumn::make('neto')
                    ->label('Precio Neto')
                    ->money('USD')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('porcen_tdec')
                    ->label('% TDEC')
                    ->suffix('%')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('quote_price')
                    ->money()
                    ->badge()
                    ->color(fn ($record) => $record->quote_price > 0 ? 'success' : 'gray')
                    ->icon('heroicon-s-currency-dollar')
                    ->label('Precio de Cotización')
                    ->sortable(),
                TextColumn::make('negotiation')
                    ->label('Negociación')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'SI' ? 'success' : 'gray')
                    ->searchable(),
                TextColumn::make('porcen_discount')
                    ->label('% Descuento')
                    ->suffix('%')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('price_discount')
                    ->label('Precio de Descuento')
                    ->money('USD')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('quote_number')
                    ->label('Número de Cotización')
                    ->searchable(),
                TextColumn::make('approved_number')
                    ->label('Número de Aprobación')
                    ->searchable(),
                TextColumn::make('service_order_number')
                    ->label('Número Orden de Servicio')
                    ->searchable(),
                TextColumn::make('bill_number')
                    ->label('Número de Factura')
                    ->searchable(),
                TextColumn::make('bill_price')
                    ->money()
                    ->badge()
                    ->color(fn ($record) => $record->bill_price > 0 ? 'success' : 'gray')
                    ->icon('heroicon-s-currency-dollar')
                    ->prefix('US$')
                    ->label('Precio de Factura')
                    ->sortable(),
                TextColumn::make('bill_date')
                    ->label('Fecha de Factura')
                    ->searchable(),
                TextColumn::make('incidence')
                    ->label('Incidencia')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'SI' ? 'warning' : 'gray')
                    ->searchable(),
                TextColumn::make('negotiation_description')
                    ->label('Descripción de Negociación')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('qc_description')
                    ->label('Descripción de QC')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->searchable(),
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
                $editNegotiationAndPricingAction,
                $clinicCoordinationDocumentsAction,
                $selectTdgDoctorForAmbulanceAction,
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
