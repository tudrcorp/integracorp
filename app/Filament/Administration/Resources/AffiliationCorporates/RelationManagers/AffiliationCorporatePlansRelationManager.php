<?php

namespace App\Filament\Administration\Resources\AffiliationCorporates\RelationManagers;

use BackedEnum;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\AgeRange;

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\RelationManagers\RelationManager;

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
                                        'required'  => 'Campo Obligatorio',
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
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),

                                Select::make('coverage_id')
                                    ->label('Cobertura')
                                    ->options(function (get $get) {
                                        if ($get('age_range_id') == 1 || $get('age_range_id') == NULL) {
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
                                    ->options(function (get $get) {
                                        return Fee::where('age_range_id', $get('age_range_id'))->where('coverage_id', $get('coverage_id'))->get()->pluck('price', 'price');
                                    })
                                    ->live()
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
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
                                    })
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
                            'affiliation_corporate_id'  => $this->getOwnerRecord()->id,
                            'code_affiliation'          => $this->getOwnerRecord()->code,
                            'plan_id'                   => $data['plan_id'],
                            'coverage_id'               => $data['coverage_id'],
                            'age_range_id'              => $data['age_range_id'],
                            'fee'                       => $data['fee'],
                            'payment_frequency'         => $data['payment_frequency'],
                            'total_persons'             => 0,
                            'subtotal_anual'            => $data['fee'],
                            'subtotal_biannual'         => $data['fee'] / 2,
                            'subtotal_quarterly'        => $data['fee'] / 4,
                            'status'                    => 'ACTIVA',
                            'created_by'                => Auth::user()->id
                        ]);
                    })
            ]);
    }
}