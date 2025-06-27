<?php

namespace App\Filament\Resources\IndividualQuotes\Pages;

use Filament\Actions\Action;
use App\Models\IndividualQuote;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Models\DetailIndividualQuote;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Resources\IndividualQuotes\IndividualQuoteResource;

class EditIndividualQuote extends EditRecord
{
    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'DETALLE';

    protected function getActions(): array
    {
        return [
            Action::make('upload')
                ->label('Cargar comprobante')
                ->icon('heroicon-s-cloud-arrow-up')
                ->form([
                    Section::make('CARGA DE COMPROBANTE')
                        ->icon('heroicon-s-cloud-arrow-up')
                        ->schema([
                            Grid::make(2)->schema([
                                FileUpload::make('vaucher_payment')
                                    ->label('Comprobante de pago')
                                    ->uploadingMessage('Cargando...')
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorAspectRatios([
                                        '16:9',
                                        '4:3',
                                        '1:1',
                                    ]),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('reference_payment')
                                    ->label('Nro. de referencia')
                                    ->prefix('REF:')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo requerido',
                                        'numeric'   => 'El campo es numerico',
                                    ])
                                    ->required(),
                                Select::make('plan_id')
                                    ->label('Plan(es) cotizados')
                                    ->live()
                                    ->options(function (IndividualQuote $record) {
                                        $plans = DetailIndividualQuote::join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                                            ->join('individual_quotes', 'detail_individual_quotes.individual_quote_id', '=', 'individual_quotes.id')
                                            ->where('individual_quotes.id', $record->id)
                                            ->select('plans.id as plan_id', 'plans.description as description')
                                            ->distinct() // Asegurarse de que no haya duplicados
                                            ->get()
                                            ->pluck('description', 'plan_id');

                                        return $plans;
                                        // Log::info($record);

                                    })
                                    ->searchable()
                                    ->live()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->preload(),
                                Select::make('coverage_id')
                                    ->label('Cobertura(s) cotizadas')
                                    ->live()
                                    ->options(function (IndividualQuote $record, Get $get) {
                                        $coverages = DetailIndividualQuote::join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                                            ->join('individual_quotes', 'detail_individual_quotes.individual_quote_id', '=', 'individual_quotes.id')
                                            ->where('individual_quotes.id', $record->id)
                                            ->where('detail_individual_quotes.plan_id', $get('plan_id'))
                                            ->select('coverages.id as coverage_id', 'coverages.price as description')
                                            ->distinct() // Asegurarse de que no haya duplicados
                                            ->get()
                                            ->pluck('description', 'coverage_id');

                                        return $coverages;
                                        // Log::info($record);

                                    })
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->preload(),
                            ]),
                            Grid::make(1)->schema([
                                Textarea::make('observations_payment')
                                    ->label('Observaciones')
                                    ->autosize()
                            ]),

                        ])
                ])

        ];
    }
}