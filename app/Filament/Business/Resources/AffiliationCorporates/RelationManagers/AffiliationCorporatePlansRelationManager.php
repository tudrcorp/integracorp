<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\RelationManagers;

use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
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
use Illuminate\Support\Facades\Auth;

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
            ]);
    }
}
