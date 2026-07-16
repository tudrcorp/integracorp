<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Tables;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceQuotes;
use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Http\Controllers\ApiBcvController;
use App\Http\Controllers\OperationServiceOrderController;
use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationInventoryUbication;
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
use App\Services\OperationServiceOrderPdfService;
use App\Services\OperationServiceOrderQuotePdfService;
use App\Support\Filament\FilamentIosButton;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\AccountsReceivableManager;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\CoordinationServiceQuoteManager;
use App\Support\Operations\OperationServiceOrderCoveredPricingFormFields;
use App\Support\Operations\OperationServiceOrderProviderFormFields;
use App\Support\Operations\OperationServiceOrderProviderSelection;
use App\Support\Operations\OperationServiceOrderUnregisteredProviderFormFields;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
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
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Services\RelationshipOrderer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OperationCoordinationServicesTable
{
    public static function referenciaTasaBcvDesdeApi(): ?float
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

    public static function decimalOrNull(mixed $value): ?float
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

    private static function coordinationIsManagedByTdg(OperationCoordinationService $record): bool
    {
        return mb_strtoupper(trim((string) $record->managed_by)) === 'TDG';
    }

    private static function managedByBadgeColor(?string $state): string
    {
        return match (mb_strtoupper(trim((string) $state))) {
            'ATENMEDI' => 'success',
            'TDG' => 'info',
            default => 'gray',
        };
    }

    private static function managedByReassignmentDescription(OperationCoordinationService $record): ?string
    {
        if (! self::coordinationIsManagedByTdg($record)) {
            return null;
        }

        $reason = TelemedicineCaseTdgReassignmentCoordination::reassignmentReasonFromObservations($record->observations);

        if ($reason === null) {
            return null;
        }

        return 'Motivo de Reasignación: '.$reason;
    }

    private static function patientBusinessLineLabel(OperationCoordinationService $record): string
    {
        $fromPatient = $record->telemedicinePatient?->businessLine?->definition;

        if (filled($fromPatient)) {
            return (string) $fromPatient;
        }

        if (filled($record->businessLine?->definition)) {
            return (string) $record->businessLine->definition;
        }

        return '—';
    }

    private static function patientBusinessUnitLabel(OperationCoordinationService $record): string
    {
        $fromPatient = $record->telemedicinePatient?->businessUnit?->definition;

        if (filled($fromPatient)) {
            return (string) $fromPatient;
        }

        if (filled($record->businessUnit?->definition)) {
            return (string) $record->businessUnit->definition;
        }

        return '—';
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

        $reassignManagedByToTdgAction = Action::make('reassignCoordinationManagedByToTdg')
            ->modalHeading('Reasignar gestión del servicio a TDG')
            ->modalDescription(function (OperationCoordinationService $record): Htmlable {
                $currentManagedBy = filled($record->managed_by)
                    ? mb_strtoupper((string) $record->managed_by)
                    : 'Sin asignar';

                $caseNote = filled($record->telemedicine_case_id)
                    ? 'El caso de telemedicina vinculado también pasará a gestión TDG.'
                    : 'Esta coordinación no tiene caso de telemedicina vinculado.';

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
                    .'<div class="rounded-xl border border-sky-200/80 bg-gradient-to-br from-sky-50/95 to-white p-4 shadow-inner dark:border-sky-500/25 dark:from-sky-950/40 dark:to-gray-950/90">'
                    .'<p class="text-sm font-medium text-sky-950 dark:text-sky-50">'
                    .'Gestión actual: <span class="font-semibold">'.e($currentManagedBy).'</span>'
                    .' → <span class="font-semibold">TDG</span>'
                    .'</p>'
                    .'<p class="mt-2 text-xs text-sky-900/80 dark:text-sky-100/80">'.e($caseNote).'</p>'
                    .'</div>'
                    .'</div>'
                );
            })
            ->modalIcon(Heroicon::OutlinedArrowsRightLeft)
            ->modalIconColor('warning')
            ->modalWidth(Width::ExtraLarge)
            ->modalSubmitActionLabel('Sí, reasignar a TDG')
            ->modalCancelActionLabel('Cancelar')
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
            ->closeModalByClickingAway(false)
            ->form([
                Textarea::make('reassignment_observation')
                    ->label('Motivo de la reasignación')
                    ->placeholder('Ej.: Coordinación con TDG por complejidad del caso, solicitud del cliente, escalamiento operativo…')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Se guardará en las observaciones de la coordinación y en la bitácora del caso vinculado.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debes indicar el motivo de la reasignación.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->action(function (OperationCoordinationService $record, array $data): void {
                $observationText = trim((string) ($data['reassignment_observation'] ?? ''));
                $bitacoraDescription = TelemedicineCaseTdgReassignmentCoordination::OBSERVATION_PREFIX."\n".'Motivo: '.$observationText;
                $userId = Auth::id();
                $userName = (string) (Auth::user()?->name ?? 'SISTEMA');

                DB::transaction(function () use ($record, $bitacoraDescription, $observationText, $userId, $userName): void {
                    $previousObservations = trim((string) ($record->observations ?? ''));

                    $record->managed_by = 'TDG';
                    $record->observations = $previousObservations !== ''
                        ? $previousObservations."\n\n".$bitacoraDescription
                        : $bitacoraDescription;
                    $record->updated_by = $userName;
                    $record->save();

                    if (filled($record->telemedicine_case_id)) {
                        TelemedicineCase::query()
                            ->whereKey($record->telemedicine_case_id)
                            ->update(['managed_by' => 'TDG']);

                        ObservationCase::query()->create([
                            'telemedicine_case_id' => $record->telemedicine_case_id,
                            'description' => $bitacoraDescription,
                            'created_by' => $userId !== null ? (string) $userId : null,
                        ]);
                    }

                    OperationServiceOrder::query()
                        ->where('operation_coordination_service_id', $record->id)
                        ->update(['managed_by' => 'TDG']);

                    AccountsReceivableManager::createFromTdgReassignment(
                        $record->fresh() ?? $record,
                        $observationText,
                        Auth::user(),
                    );
                });

                Notification::make()
                    ->title('Gestión reasignada a TDG')
                    ->body(
                        filled($record->telemedicine_case_id)
                            ? 'La coordinación, el caso vinculado y sus órdenes de servicio ahora corresponden a TDG. Se generó la cuenta por cobrar correspondiente.'
                            : 'La coordinación y sus órdenes de servicio ahora corresponden a TDG. Se generó la cuenta por cobrar correspondiente.'
                    )
                    ->success()
                    ->send();
            })
            ->visible(fn (OperationCoordinationService $record): bool => ! self::coordinationIsManagedByTdg($record));

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
                'doctor_nurse_id' => null,
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
                                Section::make('Datos de la orden de servicio')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->visible(fn (Get $get): bool => (bool) $get('create_service_order'))
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('order_number')
                                                    ->label('Número de orden')
                                                    ->required()
                                                    ->maxLength(255),
                                                Select::make('telemedicine_priority_id')
                                                    ->label('Prioridad')
                                                    ->options(TelemedicinePriority::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                                    ->required()
                                                    ->native(false),
                                                Select::make('operation_inventory_ubication_id')
                                                    ->label('Ubicación inventario (medicamentos)')
                                                    ->options(OperationInventoryUbication::query()->where('is_active', true)->orderBy('name', 'asc')->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->visible(fn (OperationCoordinationService $record): bool => self::serviceOrderType($record) === 'MEDICAMENTOS')
                                                    ->native(false)
                                                    ->columnSpanFull(),
                                                TextInput::make('service_order_description')
                                                    ->label('Descripción de la orden')
                                                    ->required()
                                                    ->maxLength(500)
                                                    ->columnSpanFull(),
                                                Textarea::make('service_order_observations')
                                                    ->label('Observaciones de la orden')
                                                    ->rows(3)
                                                    ->maxLength(2000)
                                                    ->columnSpanFull(),
                                            ]),
                                        ...OperationServiceOrderProviderFormFields::components(),
                                        ...OperationServiceOrderCoveredPricingFormFields::components(),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->columnSpanFull(),
                    ]),
                Step::make('Proveedor no convenido')
                    ->description('Registro del nuevo proveedor en el sistema')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->extraAttributes([
                        'class' => OperationServiceOrderUnregisteredProviderFormFields::WIZARD_STEP_CLASS,
                    ])
                    ->visible(fn (Get $get): bool => (bool) $get('create_service_order') && (bool) $get('register_unregistered_provider'))
                    ->schema(OperationServiceOrderUnregisteredProviderFormFields::wizardStepSchema()),
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
            ->modifyQueryUsing(function (Builder $query): Builder {
                OperationsSupplierScope::applyCoordinationListScope($query);

                return $query->with([
                    'telemedicinePriority',
                    'telemedicineDoctor',
                    'telemedicineCase',
                    'businessLine:id,definition',
                    'businessUnit:id,definition',
                    'telemedicinePatient:id,full_name,business_line_id,business_unit_id',
                    'telemedicinePatient.businessLine:id,definition',
                    'telemedicinePatient.businessUnit:id,definition',
                    'telemedicinePatientMedications.operationInventory:id,is_covered',
                    'telemedicinePatientLabs',
                    'telemedicinePatientStudies',
                    'telemedicinePatientSpecialties',
                ]);
            })
            ->columns([
                TextColumn::make('telemedicineCase.code')
                    ->label('Código del caso')
                    ->badge()
                    ->color('primary')
                    ->icon('healthicons-f-health-literacy')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? mb_strtoupper((string) $state) : '—')
                    ->description(fn (OperationCoordinationService $record): ?string => filled($record->telemedicineCase?->patient_name)
                        ? (string) $record->telemedicineCase->patient_name
                        : null)
                    ->tooltip(fn (OperationCoordinationService $record): ?string => filled($record->telemedicineCase?->code)
                        ? 'Abrir ficha del caso de telemedicina'
                        : 'Sin caso de telemedicina vinculado')
                    ->url(function (OperationCoordinationService $record): ?string {
                        $case = $record->telemedicineCase;

                        if ($case === null) {
                            return null;
                        }

                        return TelemedicineCaseResource::getUrl('view', ['record' => $case]);
                    })
                    ->extraCellAttributes(['class' => 'py-2 min-w-[8rem]'])
                    ->extraAttributes([
                        'class' => 'cursor-pointer underline decoration-dotted underline-offset-2 hover:opacity-90',
                    ]),
                TextColumn::make('patient_business_line')
                    ->label('Línea de servicio')
                    ->state(fn (OperationCoordinationService $record): string => self::patientBusinessLineLabel($record))
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $innerQuery) use ($search): void {
                            $innerQuery
                                ->whereHas('telemedicinePatient.businessLine', fn (Builder $lineQuery): Builder => $lineQuery->where('definition', 'like', "%{$search}%"))
                                ->orWhereHas('businessLine', fn (Builder $lineQuery): Builder => $lineQuery->where('definition', 'like', "%{$search}%"));
                        });
                    }),
                TextColumn::make('patient_business_unit')
                    ->label('Unidad de negocio')
                    ->state(fn (OperationCoordinationService $record): string => self::patientBusinessUnitLabel($record))
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $innerQuery) use ($search): void {
                            $innerQuery
                                ->whereHas('telemedicinePatient.businessUnit', fn (Builder $unitQuery): Builder => $unitQuery->where('definition', 'like', "%{$search}%"))
                                ->orWhereHas('businessUnit', fn (Builder $unitQuery): Builder => $unitQuery->where('definition', 'like', "%{$search}%"));
                        });
                    }),
                TextColumn::make('clinical_management_items')
                    ->label('Ítems clínicos')
                    ->getStateUsing(
                        fn (OperationCoordinationService $record): HtmlString => CoordinationServiceItemsManager::renderCoordinationClinicalItemsCompactList($record)
                    )
                    ->html()
                    ->alignStart()
                    ->extraHeaderAttributes([
                        'class' => 'fi-coordination-clinical-items-header',
                        'style' => 'min-width: 22rem;',
                    ])
                    ->extraCellAttributes([
                        'class' => 'fi-coordination-clinical-items-cell py-2.5 align-top',
                        'style' => 'min-width: 22rem; max-width: 30rem; white-space: normal; vertical-align: top;',
                    ])
                    ->tooltip('Detalle de medicamentos, laboratorios, estudios y especialidades asociados a esta coordinación.'),
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
                    ->color(fn (?string $state): string => self::managedByBadgeColor($state))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? mb_strtoupper($state) : '—')
                    ->description(fn (OperationCoordinationService $record): ?string => self::managedByReassignmentDescription($record))
                    ->wrap()
                    ->sortable()
                    ->searchable()
                    ->action($reassignManagedByToTdgAction)
                    ->tooltip(fn (OperationCoordinationService $record): ?string => self::coordinationIsManagedByTdg($record)
                        ? null
                        : 'Clic para reasignar la gestión del servicio a TDG')
                    ->extraCellAttributes(fn (OperationCoordinationService $record): array => self::coordinationIsManagedByTdg($record)
                        ? (self::managedByReassignmentDescription($record) !== null
                            ? ['class' => 'py-2 align-top', 'style' => 'min-width: 12rem; max-width: 18rem; white-space: normal;']
                            : [])
                        : [
                            'class' => 'transition active:opacity-90',
                        ])
                    ->extraAttributes(fn (OperationCoordinationService $record): array => self::coordinationIsManagedByTdg($record)
                        ? []
                        : [
                            'class' => 'cursor-pointer underline decoration-dotted underline-offset-2 hover:opacity-90 active:opacity-75',
                        ]),
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
                    ->label('Teléfono')
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
            ->recordClasses(fn (OperationCoordinationService $record): array => self::recordRowClasses($record))
            ->groups([
                Group::make('telemedicineCase.code')
                    ->label('Código del caso')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function (OperationCoordinationService $record): string {
                        $code = mb_strtoupper(trim((string) ($record->telemedicineCase?->code ?? '')));

                        if ($code === '') {
                            return 'Sin caso vinculado';
                        }

                        $patientName = trim((string) ($record->telemedicineCase?->patient_name ?? ''));

                        return $patientName !== '' ? $code.' · '.$patientName : $code;
                    })
                    ->getDescriptionFromRecordUsing(function (OperationCoordinationService $record): ?string {
                        $doctorName = trim((string) ($record->telemedicineDoctor?->full_name ?? ''));

                        return $doctorName !== '' ? 'Médico: '.$doctorName : null;
                    })
                    ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            app(RelationshipOrderer::class)->buildSubquery($query, 'telemedicineCase', 'created_at'),
                            'desc',
                        );
                    }),
            ])
            ->defaultGroup('telemedicineCase.code')
            ->collapsedGroupsByDefault()
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
                        ->disabled(fn (OperationCoordinationService $record): bool => CoordinationServiceItemsManager::manageServiceActionIsDisabled($record))
                        ->url(fn (OperationCoordinationService $record): string => ManageCoordinationServiceItems::getUrl(['record' => $record])),
                    Action::make('manage_service_quote')
                        ->label('Gestionar Cotización')
                        ->icon(Heroicon::OutlinedDocumentCurrencyDollar)
                        ->color('warning')
                        ->visible(fn (OperationCoordinationService $record): bool => CoordinationServiceQuoteManager::coordinationQuotes($record)->isNotEmpty())
                        ->url(fn (OperationCoordinationService $record): string => ManageCoordinationServiceQuotes::getUrl(['record' => $record])),
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
     * @param  array<int, string>  $keys
     * @return array<int, int>
     */
    public static function managementKeysToNumericIds(array $keys): array
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
    public static function selectedServiceOrderRecordsByType(
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

    /**
     * @return array<string, mixed>|null
     */
    public static function buildServiceOrderPayload(
        OperationCoordinationService $record,
        array $data,
        string $serviceOrderType
    ): ?array {
        $providerError = OperationServiceOrderProviderSelection::validationMessage($data);

        if ($providerError !== null) {
            Notification::make()
                ->title('Proveedor de la orden')
                ->body($providerError)
                ->warning()
                ->send();

            return null;
        }

        $providers = OperationServiceOrderProviderSelection::resolveProviders($data);

        $payload = [
            'order_number' => $data['order_number'] ?? null,
            'telemedicine_priority_id' => $data['telemedicine_priority_id'] ?? $record->telemedicine_priority_id,
            'doctor_nurse_id' => $providers['doctor_nurse_id'],
            'supplier_id' => $providers['supplier_id'],
            'supplier_external' => $providers['supplier_external'],
            'operation_inventory_ubication_id' => $data['operation_inventory_ubication_id'] ?? null,
            'description' => $data['service_order_description'] ?? null,
            'service_type' => $serviceOrderType,
            'status' => 'EN GESTION',
            'observations' => $data['service_order_observations'] ?? null,
            'created_by' => Auth::user()?->name,
            'updated_by' => Auth::user()?->name,
        ];

        $pricing = OperationServiceOrderCoveredPricingFormFields::pricingPayloadFromData($data);

        if ($pricing !== null) {
            $payload = [...$payload, ...$pricing];
        }

        return $payload;
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

        $payload = self::buildServiceOrderPayload($record, $data, $type);

        if ($payload === null) {
            return;
        }

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

    /**
     * @return list<string>
     */
    private static function recordRowClasses(OperationCoordinationService $record): array
    {
        if (CoordinationServiceItemsManager::allAssociatedItemsAreClosed($record)) {
            return [CoordinationServiceItemsManager::coordinationClosedRowClasses()];
        }

        return [TelemedicinePriorityFilamentBadge::recordRowClasses($record->telemedicinePriority?->name)];
    }

    /**
     * Mantiene visibles únicamente las coordinaciones con trabajo pendiente: se
     * conservan las que aún no tienen ítems o las que tienen al menos un ítem
     * PENDIENTE o EN GESTION, y se ocultan aquellas cuyos ítems están todos
     * cerrados (finalizados/cancelados/caducados), para que el usuario se centre
     * en lo que debe gestionar y finalizar.
     */
    public static function applyHideFullyFinalizedScope(Builder $query): Builder
    {
        $openStatuses = ['PENDIENTE', 'EN GESTION'];

        return $query->where(function (Builder $outer) use ($openStatuses): void {
            $outer
                ->where(function (Builder $withoutItems): void {
                    $withoutItems
                        ->whereDoesntHave('telemedicinePatientMedications')
                        ->whereDoesntHave('telemedicinePatientLabs')
                        ->whereDoesntHave('telemedicinePatientStudies')
                        ->whereDoesntHave('telemedicinePatientSpecialties');
                })
                ->orWhereHas('telemedicinePatientMedications', fn (Builder $items): Builder => self::whereItemStatusIsOpen($items, $openStatuses))
                ->orWhereHas('telemedicinePatientLabs', fn (Builder $items): Builder => self::whereItemStatusIsOpen($items, $openStatuses))
                ->orWhereHas('telemedicinePatientStudies', fn (Builder $items): Builder => self::whereItemStatusIsOpen($items, $openStatuses))
                ->orWhereHas('telemedicinePatientSpecialties', fn (Builder $items): Builder => self::whereItemStatusIsOpen($items, $openStatuses));
        });
    }

    /**
     * @param  list<string>  $openStatuses
     */
    private static function whereItemStatusIsOpen(Builder $query, array $openStatuses): Builder
    {
        return $query->whereRaw('UPPER(TRIM(status)) IN (?, ?)', $openStatuses);
    }
}
