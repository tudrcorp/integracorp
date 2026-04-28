<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Tables;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                    ->label('Servicio Específico')
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
                // SelectColumn::make('type_service')
                //     ->label('Tipo de Servicio')
                //     ->options(OperationTypeService::all()->pluck('description', 'description'))
                //     ->searchableOptions()
                //     ->searchable()
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     }),
                // SelectColumn::make('supplier_service')
                //     ->label('Proveedor de Servicio')
                //     ->options(Supplier::all()->pluck('name', 'name'))
                //     ->searchableOptions()
                //     ->getOptionsSearchResultsUsing(fn (string $search): array => Supplier::query()
                //         // prueba 502091882
                //         ->where('name', 'like', "%{$search}%")
                //         ->orWhere('rif', 'like', "%{$search}%")
                //         ->limit(50)
                //         ->pluck('name', 'name')
                //         ->all()
                //     )
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     })
                //     ->searchable(),
                // TextColumn::make('farmadoc')
                //     ->label('Farmadoc')
                //     ->searchable(),
                // SelectColumn::make('type_negotiation')
                //     ->label('Tipo de Negociación')
                //     ->options(OperationTypeNegotiation::all()->pluck('description', 'description'))
                //     ->searchableOptions()
                //     ->searchable(),
                // TextInputColumn::make('status_negotiation')
                //     ->label('Estatus de Negociación')
                //     ->searchable()
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->status_negotiation = strtoupper($state);
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     }),
                // TextInputColumn::make('neto')
                //     ->label('Precio Neto')
                //     ->type('number')
                //     ->inputMode('decimal')
                //     ->prefix('US$')
                //     ->sortable(),
                // TextInputColumn::make('porcen_tdec')
                //     ->type('number')
                //     ->inputMode('decimal')
                //     ->prefix('%')
                //     ->label('% TDEC')
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->quote_price = ($record->neto * $state / 100) + $record->neto;
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     })
                //     ->sortable(),
                // TextColumn::make('quote_price')
                //     ->money()
                //     ->badge()
                //     ->color(fn ($record) => $record->quote_price > 0 ? 'success' : 'gray')
                //     ->icon('heroicon-s-currency-dollar')
                //     ->label('Precio de Cotización')
                //     ->sortable(),
                // SelectColumn::make('negotiation')
                //     ->label('Negociación')
                //     ->options(['SI' => 'SI', 'NO' => 'NO'])
                //     ->searchableOptions()
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     })
                //     ->searchable(),
                // TextInputColumn::make('porcen_discount')
                //     ->type('number')
                //     ->inputMode('decimal')
                //     ->prefix('%')
                //     ->label('Porcentaje de Descuento')
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->price_discount = ($record->quote_price * $state / 100);
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     })
                //     ->sortable(),
                // TextInputColumn::make('price_discount')
                //     ->type('number')
                //     ->inputMode('decimal')
                //     ->prefix('US$')
                //     ->label('Precio de Descuento')
                //     ->sortable()
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     }),
                // TextInputColumn::make('quote_number')
                //     ->label('Número de Cotización')
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     })
                //     ->searchable(),
                // TextInputColumn::make('approved_number')
                //     ->label('Número de Aprobación')
                //     ->searchable()
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     }),
                // TextInputColumn::make('service_order_number')
                //     ->label('Número Orden de Servicio')
                //     ->searchable()
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     }),
                // TextColumn::make('bill_number')
                //     ->label('Número de Factura')
                //     ->searchable(),
                // TextColumn::make('bill_price')
                //     ->money()
                //     ->badge()
                //     ->color(fn ($record) => $record->bill_price > 0 ? 'success' : 'gray')
                //     ->icon('heroicon-s-currency-dollar')
                //     ->prefix('US$')
                //     ->label('Precio de Factura')
                //     ->sortable(),
                // TextColumn::make('bill_date')
                //     ->label('Fecha de Factura')
                //     ->searchable(),
                // SelectColumn::make('incidence')
                //     ->label('Incidencia')
                //     ->options(['SI' => 'SI', 'NO' => 'NO'])
                //     ->searchableOptions()
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     })
                //     ->searchable(),
                // SelectColumn::make('negotiation_description')
                //     ->label('Descripción de Negociación')
                //     ->options(['SI' => 'SI', 'NO' => 'NO'])
                //     ->searchableOptions()
                //     ->afterStateUpdated(function ($record, $state) {
                //         $record->updated_by = Auth::user()->name;
                //         $record->save();
                //     })
                //     ->searchable(),
                // TextColumn::make('qc_description')
                //     ->label('Descripción de QC')
                //     ->searchable(),
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
                $selectTdgDoctorForAmbulanceAction,
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
