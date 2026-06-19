<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\RelationManagers;

use App\Models\AffiliateCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use App\Services\AssociateAffiliatesWithCorporatePlanService;
use App\Support\Filament\FilamentIosButton;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AffiliationCorporatePlansRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliationCorporatePlans';

    protected static ?string $title = 'Plan(es) Afiliado(s)';

    protected static string|BackedEnum|null $icon = 'fontisto-share';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('NUEVO PLAN')
                    ->description('Fomulario para asociar un nuevo plan a la afiliacion corporativa.')
                    ->icon('heroicon-s-user')
                    ->schema([
                        Fieldset::make('Plan de afiliación')
                            ->schema([
                                Select::make('plan_id')
                                    ->options(function ($record) {
                                        return Plan::all()->pluck('description', 'id');
                                    })
                                    ->label('Planes')
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->preload()
                                    ->placeholder('Seleccione plan(es)'),

                                Select::make('age_range_id')
                                    ->label('Rango de edad')
                                    ->options(function (Get $get, $state) {
                                        return AgeRange::where('plan_id', intval($get('plan_id')))->get()->pluck('range', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),

                                Select::make('coverage_id')
                                    ->label('Cobertura')
                                    ->options(function (Get $get) {
                                        if ($get('age_range_id') == 1 || $get('age_range_id') == null) {
                                            return [];
                                        }
                                        $arrayFee = AgeRange::where('plan_id', $get('plan_id'))->where('id', $get('age_range_id'))->with('fees')->get()->toArray();

                                        return collect($arrayFee[0]['fees'])->pluck('coverage', 'coverage_id');
                                    })
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),

                                Select::make('fee')
                                    ->label('Tarifa Anual')
                                    ->options(function (Get $get) {
                                        return Fee::where('age_range_id', $get('age_range_id'))->where('coverage_id', $get('coverage_id'))->get()->pluck('price', 'price');
                                    })
                                    ->live()
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Obligatorio',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),
                                TextInput::make('payment_frequency')
                                    ->label('Frecuencia de pago')
                                    ->live()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(function () {
                                        return $this->getOwnerRecord()->payment_frequency;
                                    }),
                            ])->columnSpanFull()->columns(2),

                    ])->columnSpanFull()->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Lista de plan(es) afiliado(s)')
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->numeric()
                    ->suffix(' US$')
                    ->searchable(),
                TextColumn::make('ageRange.range')
                    ->label('Rango de Edad')
                    ->suffix(' años')
                    ->searchable(),
                TextColumn::make('fee')
                    ->suffix(' US$')
                    ->numeric()
                    ->label('Tarifa'),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de Pago')
                    ->searchable(),
                TextColumn::make('total_persons')
                    ->suffix(' afiliado(s)')
                    ->numeric()
                    ->label('Afiliados')
                    ->summarize(Sum::make()
                        ->label(('Afiliados'))
                        ->suffix(' afiliado(s)')
                        ->numeric()),
                TextColumn::make('subtotal_anual')
                    ->suffix(' US$')
                    ->numeric()
                    ->label('Subtotal Anual')
                    ->summarize(Sum::make()
                        ->label(('Subtotal Anual'))
                        ->suffix(' US$')
                        ->numeric()),
                TextColumn::make('subtotal_biannual')
                    ->suffix(' US$')
                    ->numeric()
                    ->label('Subtotal Semestral')
                    ->summarize(Sum::make()
                        ->label(('Subtotal Semestral'))
                        ->suffix(' US$')
                        ->numeric()),
                TextColumn::make('subtotal_quarterly')
                    ->suffix(' US$')
                    ->numeric()
                    ->label('Subtotal Trimestral')
                    ->summarize(Sum::make()
                        ->label(('Subtotal Trimestral'))
                        ->suffix(' US$')
                        ->numeric()),
            ])
            ->headerActions([
                Action::make('edit_subtotals_modal')
                    ->label('Editar subtotales')
                    ->icon(Heroicon::PencilSquare)
                    ->color('primary')
                    ->modalHeading('Editar subtotales')
                    ->modalDescription('Ajuste los subtotales por fila. Los demás datos son solo referencia.')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Guardar cambios')
                    ->fillForm(fn (): array => [
                        'plan_rows' => $this->getOwnerRecord()
                            ->affiliationCorporatePlans()
                            ->with(['plan', 'coverage', 'ageRange'])
                            ->orderBy('id')
                            ->get()
                            ->map(fn (AfilliationCorporatePlan $plan): array => [
                                'id' => $plan->getKey(),
                                'plan_label' => $plan->plan?->description ?? '—',
                                'coverage_label' => $plan->coverage !== null ? (string) $plan->coverage->price : '—',
                                'age_range_label' => $plan->ageRange?->range ?? '—',
                                'fee' => $plan->fee,
                                'payment_frequency' => $plan->payment_frequency ?? '—',
                                'total_persons' => $plan->total_persons,
                                'subtotal_anual' => $plan->subtotal_anual,
                                'subtotal_biannual' => $plan->subtotal_biannual,
                                'subtotal_quarterly' => $plan->subtotal_quarterly,
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->schema([
                        Section::make('Planes asociados')
                            ->schema([
                                Repeater::make('plan_rows')
                                    ->label('')
                                    ->schema([
                                        Hidden::make('id'),
                                        Grid::make(12)
                                            ->schema([
                                                TextInput::make('plan_label')
                                                    ->label('Plan')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->columnSpan(3),
                                                TextInput::make('coverage_label')
                                                    ->label('Cobertura')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->columnSpan(2),
                                                TextInput::make('age_range_label')
                                                    ->label('Rango')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->columnSpan(2),
                                                TextInput::make('fee')
                                                    ->label('Tarifa')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->suffix(' US$')
                                                    ->columnSpan(2),
                                                TextInput::make('payment_frequency')
                                                    ->label('Frec. pago')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->columnSpan(1),
                                                TextInput::make('total_persons')
                                                    ->label('Afiliados')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->columnSpan(2),
                                            ]),
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('subtotal_anual')
                                                    ->label('Subtotal anual')
                                                    ->numeric()
                                                    ->suffix(' US$')
                                                    ->required(),
                                                TextInput::make('subtotal_biannual')
                                                    ->label('Subtotal semestral')
                                                    ->numeric()
                                                    ->suffix(' US$')
                                                    ->required(),
                                                TextInput::make('subtotal_quarterly')
                                                    ->label('Subtotal trimestral')
                                                    ->numeric()
                                                    ->suffix(' US$')
                                                    ->required(),
                                            ]),
                                    ])
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data): void {
                        $ownerId = (int) $this->getOwnerRecord()->id;

                        foreach ($data['plan_rows'] ?? [] as $row) {
                            $id = $row['id'] ?? null;
                            if ($id === null) {
                                continue;
                            }

                            $plan = AfilliationCorporatePlan::query()->find($id);
                            if (! $plan || (int) $plan->affiliation_corporate_id !== $ownerId) {
                                continue;
                            }

                            $plan->update([
                                'subtotal_anual' => (float) ($row['subtotal_anual'] ?? 0),
                                'subtotal_biannual' => (float) ($row['subtotal_biannual'] ?? 0),
                                'subtotal_quarterly' => (float) ($row['subtotal_quarterly'] ?? 0),
                            ]);
                        }

                        Notification::make()
                            ->title('Subtotales actualizados')
                            ->success()
                            ->send();
                    }),
                CreateAction::make()
                    ->label('Asociar Nuevo Plan')
                    ->modalHeading('FORMULARIO DE ASOCIACION DE PLANES')
                    ->color('success')
                    ->createAnother(false)
                    ->icon(Heroicon::Plus)
                    ->before(function (array $data, CreateAction $action) {

                        $plans = $this->getOwnerRecord()->affiliationCorporatePlans
                            ->where('plan_id', $data['plan_id'])
                            ->where('coverage_id', $data['coverage_id'])
                            ->where('age_range_id', $data['age_range_id'])
                            ->pluck('plan_id')
                            ->toArray();

                        if (count($plans) > 0) {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->icon(Heroicon::ExclamationCircle)
                                ->body('El plan, la cobertura y el rango de edad seleccionado ya se encuentra en la lista de planes afiliados. Por favor, seleccione un plan que pertenece a la afiliación corporativa')
                                ->send();

                            $action->halt();
                        }

                    })
                    ->using(function (array $data) {
                        $this->getOwnerRecord()->affiliationCorporatePlans()->create([
                            'affiliation_corporate_id' => $this->getOwnerRecord()->id,
                            'code_affiliation' => $this->getOwnerRecord()->code,
                            'plan_id' => $data['plan_id'],
                            'coverage_id' => $data['coverage_id'],
                            'age_range_id' => $data['age_range_id'],
                            'fee' => $data['fee'],
                            'payment_frequency' => $data['payment_frequency'],
                            'total_persons' => 0,
                            'subtotal_anual' => $data['fee'],
                            'subtotal_biannual' => $data['fee'] / 2,
                            'subtotal_quarterly' => $data['fee'] / 4,
                            'status' => 'ACTIVA',
                            'created_by' => Auth::id(),
                        ]);
                    }),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->label('Eliminar')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->action(function (Collection $records): void {
                        $ids = $records
                            ->pluck('id')
                            ->filter()
                            ->values()
                            ->all();

                        if ($ids === []) {
                            return;
                        }

                        $this->getOwnerRecord()->affiliationCorporatePlans()->whereIn('id', $ids)->delete();
                    }),
                BulkAction::make('associate_and_recalculate')
                    ->label('Asociar y recalcular')
                    ->icon(Heroicon::ArrowPath)
                    ->color('success')
                    ->modalHeading('Asociar afiliados al plan')
                    ->modalDescription('Seleccione exactamente una fila de plan en la tabla. Plan, cobertura y tarifa son los de esa fila (solo lectura). Elija los afiliados cuya edad calza con el rango de esa fila. Los subtotales estiman según la cantidad seleccionada y la frecuencia de pago.')
                    ->modalIcon(Heroicon::ArrowPath)
                    ->modalIconColor('success')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Asociar y recalcular')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalSubmitAction(
                        fn (Action $action) => $action
                            ->color('success')
                            ->extraAttributes([
                                'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                            ])
                    )
                    ->modalCancelAction(
                        fn (Action $action) => $action
                            ->color('gray')
                            ->extraAttributes([
                                'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                            ])
                    )
                    ->extraModalWindowAttributes([
                        'class' => 'fi-ios-affiliation-associate-plan-modal',
                    ], merge: false)
                    ->deselectRecordsAfterCompletion()
                    ->fillForm(function (Action $action): array {
                        $records = $action->getSelectedRecords();
                        if ($records->count() !== 1) {
                            return [];
                        }

                        $row = $records->first();
                        if (! $row instanceof AfilliationCorporatePlan) {
                            return [];
                        }

                        return [
                            'associate_plan_row_id' => $row->getKey(),
                        ];
                    })
                    ->schema([
                        Section::make('Afiliados de la corporación')
                            ->description('Solo se listan afiliados cuya edad está dentro del rango de edad de la fila de plan seleccionada. Marque los que desea asociar a este plan.')
                            ->icon(Heroicon::UserGroup)
                            ->schema([
                                CheckboxList::make('affiliate_ids')
                                    ->label('')
                                    ->options(function (Get $get): array {
                                        $row = $this->resolveAssociateModalPlanRow($get('associate_plan_row_id'));
                                        if ($row === null) {
                                            return [];
                                        }

                                        $eligibleIds = AssociateAffiliatesWithCorporatePlanService::idsForAffiliatesMatchingPlanRowAgeRange(
                                            $this->getOwnerRecord(),
                                            $row,
                                        );

                                        if ($eligibleIds === []) {
                                            return [];
                                        }

                                        return AffiliateCorporate::query()
                                            ->where('affiliation_corporate_id', $this->getOwnerRecord()->id)
                                            ->whereIn('id', $eligibleIds)
                                            ->orderBy('first_name')
                                            ->orderBy('last_name')
                                            ->get()
                                            ->mapWithKeys(function (AffiliateCorporate $a): array {
                                                $name = trim(($a->first_name ?? '').' '.($a->last_name ?? ''));
                                                $label = $name !== '' ? $name : 'Afiliado #'.$a->id;

                                                return [
                                                    $a->id => $label.' · Edad '.($a->age ?? '—').' · CI '.($a->nro_identificacion ?? '—'),
                                                ];
                                            })
                                            ->all();
                                    })
                                    ->columns(1)
                                    ->gridDirection('row')
                                    ->bulkToggleable()
                                    ->searchable()
                                    ->required()
                                    ->live(onBlur: false)
                                    ->columnSpanFull(),
                                Placeholder::make('payment_frequency_hint')
                                    ->label('Frecuencia de pago (afiliación)')
                                    ->content(fn (): string => 'Frecuencia actual: '.($this->getOwnerRecord()->payment_frequency ?? '—').'. Los montos por periodo se calculan con esta frecuencia.'),
                            ])
                            ->columnSpanFull(),
                        Section::make('Cobertura y tarifa')
                            ->description('Datos de la fila seleccionada en la tabla. Solo lectura: no se pueden modificar desde este formulario.')
                            ->icon(Heroicon::ClipboardDocumentCheck)
                            ->schema([
                                Hidden::make('associate_plan_row_id'),
                                Placeholder::make('selected_plan_label')
                                    ->label('Plan')
                                    ->content(function (Get $get): string {
                                        $row = $this->resolveAssociateModalPlanRow($get('associate_plan_row_id'));
                                        if ($row === null) {
                                            return 'Marque una sola fila en la tabla de planes y vuelva a abrir esta acción.';
                                        }

                                        return $row->plan?->description ?? '—';
                                    }),
                                Placeholder::make('age_range_readonly')
                                    ->label('Rango de edad')
                                    ->content(function (Get $get): string {
                                        $row = $this->resolveAssociateModalPlanRow($get('associate_plan_row_id'));

                                        return $row?->ageRange?->range !== null && $row->ageRange->range !== ''
                                            ? $row->ageRange->range.' años'
                                            : '—';
                                    }),
                                Placeholder::make('coverage_readonly')
                                    ->label('Cobertura')
                                    ->content(function (Get $get): string {
                                        $row = $this->resolveAssociateModalPlanRow($get('associate_plan_row_id'));
                                        if ($row?->coverage === null) {
                                            return '—';
                                        }

                                        return number_format((float) $row->coverage->price, 2, '.', ',').' US$';
                                    }),
                                Placeholder::make('fee_readonly')
                                    ->label('Tarifa anual (por persona)')
                                    ->content(function (Get $get): string {
                                        $row = $this->resolveAssociateModalPlanRow($get('associate_plan_row_id'));
                                        $fee = (float) ($row?->fee ?? 0);

                                        return $fee > 0
                                            ? number_format($fee, 2, '.', ',').' US$'
                                            : '—';
                                    }),
                                Placeholder::make('calc_summary')
                                    ->label('Vista previa del cálculo')
                                    ->content(function (Get $get): string {
                                        $row = $this->resolveAssociateModalPlanRow($get('associate_plan_row_id'));
                                        $fee = (float) ($row?->fee ?? 0);

                                        $ids = $get('affiliate_ids');
                                        $n = is_array($ids) ? count($ids) : 0;
                                        if ($n === 0 || $fee <= 0) {
                                            return 'Seleccione afiliados para ver totales anuales estimados.';
                                        }
                                        $annual = $fee * $n;
                                        $owner = $this->getOwnerRecord();
                                        $f = strtoupper(trim((string) ($owner->payment_frequency ?? '')));
                                        $perPeriod = match ($f) {
                                            'SEMESTRAL' => $annual / 2,
                                            'TRIMESTRAL' => $annual / 4,
                                            'MENSUAL' => $annual / 12,
                                            default => $annual,
                                        };

                                        return sprintf(
                                            '%d afiliado(s) × US$ %s anuales = US$ %s / año. Monto referencial por periodo (%s): US$ %s.',
                                            $n,
                                            number_format($fee, 2, '.', ','),
                                            number_format($annual, 2, '.', ','),
                                            $owner->payment_frequency ?? 'N/D',
                                            number_format($perPeriod, 2, '.', ','),
                                        );
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, Collection $records): void {
                        if ($records->count() !== 1) {
                            Notification::make()
                                ->title('Seleccione una sola fila')
                                ->body('Marque exactamente un plan en la tabla para asociar afiliados y recalcular.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $planRow = $records->first();
                        if (! $planRow instanceof AfilliationCorporatePlan) {
                            Notification::make()
                                ->title('Plan no válido')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ((int) $planRow->affiliation_corporate_id !== (int) $this->getOwnerRecord()->id) {
                            Notification::make()
                                ->title('Plan no válido')
                                ->danger()
                                ->send();

                            return;
                        }

                        $planRow = AfilliationCorporatePlan::query()
                            ->whereKey($planRow->getKey())
                            ->where('affiliation_corporate_id', $this->getOwnerRecord()->id)
                            ->first();

                        if ($planRow === null) {
                            Notification::make()
                                ->title('Plan no válido')
                                ->danger()
                                ->send();

                            return;
                        }

                        $affiliateIds = $data['affiliate_ids'] ?? [];
                        if (! is_array($affiliateIds)) {
                            $affiliateIds = [];
                        }

                        try {
                            AssociateAffiliatesWithCorporatePlanService::run(
                                $this->getOwnerRecord(),
                                $planRow,
                                $affiliateIds,
                                [
                                    'plan_id' => $planRow->plan_id,
                                    'age_range_id' => $planRow->age_range_id,
                                    'coverage_id' => $planRow->coverage_id,
                                    'fee' => $planRow->fee,
                                ],
                            );
                        } catch (ValidationException $e) {
                            $errors = $e->errors();
                            $msg = collect($errors)->flatten()->implode(' ');

                            if (isset($errors['age_range'])) {
                                Notification::make()
                                    ->title('Edad no compatible con el plan')
                                    ->body($msg !== '' ? $msg : 'Uno o más afiliados están fuera del rango de edad de esta fila o no tienen edad registrada.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('No se pudo completar la asociación')
                                ->body($msg !== '' ? $msg : 'Revise los datos e intente de nuevo.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $assignedCount = count($affiliateIds);
                        Notification::make()
                            ->title('Asociación y recálculo listos')
                            ->body($assignedCount === 1
                                ? 'Se actualizó 1 afiliado, la fila de plan y los totales de la afiliación.'
                                : 'Se actualizaron '.$assignedCount.' afiliados, la fila de plan y los totales de la afiliación.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    private function resolveAssociateModalPlanRow(mixed $rowId): ?AfilliationCorporatePlan
    {
        if (blank($rowId)) {
            return null;
        }

        $ownerId = (int) $this->getOwnerRecord()->id;

        return AfilliationCorporatePlan::query()
            ->whereKey((int) $rowId)
            ->where('affiliation_corporate_id', $ownerId)
            ->with(['plan', 'ageRange', 'coverage'])
            ->first();
    }
}
