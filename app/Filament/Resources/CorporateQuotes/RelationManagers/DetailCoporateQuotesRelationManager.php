<?php

namespace App\Filament\Resources\CorporateQuotes\RelationManagers;

use Carbon\Carbon;
use App\Models\Fee;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Support\Enums\Width;
use App\Models\CorporateQuoteData;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Illuminate\Support\Facades\Log;
use App\Models\DetailCorporateQuote;
use Filament\Actions\BulkActionGroup;
use Illuminate\Validation\Rules\File;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Http\Controllers\UtilsController;
use App\Models\CorporateQuoteRequestData;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Collection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Filament\Imports\AffiliateCorporateImporter;
use Filament\Resources\RelationManagers\RelationManager;
use App\Http\Controllers\DetailCorporateQuotesController;
use App\Filament\Imports\CorporateQuoteRequestDataImporter;
use App\Filament\Resources\CorporateQuotes\CorporateQuoteResource;

class DetailCoporateQuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'detailCoporateQuotes';

    protected static ?string $title = 'CALCULO DE COTIZACIÓN';

    protected static ?string $relatedResource = CorporateQuoteResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->heading('DETALLE DE COTIZACION')
            ->description('Lista de detalles de planes y coberturas con sus tarifas, agrupas por rango de edades')
            ->recordTitleAttribute('individual_quote_id')
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->sortable(),
                TextColumn::make('ageRange.range')
                    ->label('Rango de Edad')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->searchable()
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('fee')
                    ->label('Tarifa individual')
                    ->alignCenter()
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_anual')
                    ->label('Total anual')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_biannual')
                    ->label('Total semestral')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_quarterly')
                    ->label('Total trimestral')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'verde',
                            'APROBADA' => 'success',
                            'EJECUTADA' => 'azul',
                            'ACTIVA-PENDIENTE' => 'azul',
                        };
                    })
                    ->sortable(),
            ])
            //agrupar por planes y por coberturas
            ->defaultGroup('ageRange.range')
            ->filters([
                SelectFilter::make('plan_id')
                    ->label('Lista de planes')
                    ->multiple()
                    ->preload()
                    ->relationship('plan', 'description')
                    ->attribute('sucursal_id'),
                SelectFilter::make('coverage_id')
                    ->label('Lista de coberturas')
                    ->multiple()
                    ->preload()
                    ->relationship('coverage', 'price')
                    ->attribute('sucursal_id'),
            ])
            ->headerActions([
                Action::make('update_quote')
                    ->label('Actualizar cotización')
                    ->icon('fluentui-document-sync-16')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (RelationManager $livewire){
                        // dd($livewire->ownerRecord->id);
                        $exit_request = CorporateQuoteData::where('corporate_quote_id', $livewire->ownerRecord->id)->count();
                        if ($exit_request <= 0) {
                            Notification::make()
                                ->title('No exite cotización asociada a la solicitud')
                                ->danger()
                                ->send();
                            return;
                        }
                        $createCorporateQuote = UtilsController::createCorporateQuote($livewire);

                        if ($createCorporateQuote) {
                            Notification::make()
                                ->title('Cotización creada con éxito')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error al crear la cotización')
                                ->danger()
                                ->send();
                        }
                    })
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('affiliate_multiple_plans')
                            ->label('Afiliación Multiplan')
                            ->color('info')
                            ->icon('fluentui-share-android-20')
                            ->requiresConfirmation()
                            ->modalWidth(Width::ExtraLarge)
                            ->modalHeading('AFILIACIÓN MULTIPLAN')
                            ->modalDescription('Felicitaciones!, vas a iniciar el proceso de afiliación multiplan.')
                            ->modalIcon('fluentui-share-android-20')
                            ->deselectRecordsAfterCompletion()
                            ->action(function (Collection $records, RelationManager $livewire) {
                                // dd($records);
                                try {

                                    // dd($records->count(), $records);

                                    //Guardo data records en una varaiable de sesion, si la variable de session exite y tiene informacion se actualiza

                                    session()->get('data_records', []);

                                    session()->put('data_records', $records->toArray());

                                    $data_records = session()->get('data_records');
                                    // dd($data_records);
                                    /**
                                     * Actualizo el status a APROBADA
                                     */
                                    // $livewire->ownerRecord->status = 'APROBADA';
                                    // $livewire->ownerRecord->save();
                                    // dd($data_records);
                                    $record = $records->first();

                                    if ($records->count() == 1) {
                                        return redirect()->route('filament.admin.resources.affiliation-corporates.create', ['plan_id' => $record->plan_id, 'corporate_quote_id' => $livewire->ownerRecord->id]);
                                    }

                                    if ($records->count() > 1) {
                                        return redirect()->route('filament.admin.resources.affiliation-corporates.create', ['plan_id' => null, 'corporate_quote_id' => $livewire->ownerRecord->id]);
                                    }


                                    // $data_records = session()->get('data_records', []);
                                    // session()->put('data_records', $data_records);

                                    // dd($data_records, $data_records[0]['corporate_quote_id']);

                                    /** 2. Guardar los datos de la pre afiliacion */
                                    // $preAfiliation = new AffiliationCorporate();

                                    // $preAfiliation->code                = $this->getCode();
                                    // $preAfiliation->corporate_quote_id  = $data_records[0]['corporate_quote_id'];
                                    // $preAfiliation->type                = count($data_records) > 1 ? 'MULTIPLE' : null;
                                    // $preAfiliation->plan_id             = count($data_records) > 1 ? null : $data_records[0]['plan_id'];
                                    // $preAfiliation->coverage_id         = count($data_records) > 1 ? null : $data_records[0]['coverage_id'];
                                    // $preAfiliation->full_name_con       = $data['full_name_con'];
                                    // $preAfiliation->rif                 = $data['rif'];
                                    // $preAfiliation->email_con           = $data['email_con'];
                                    // $preAfiliation->phone_con           = $data['phone_con'];
                                    // // $preAfiliation->total_persons = array_sum(array_column($data, 'total_persons'));
                                    // $preAfiliation->agent_id            = Auth::user()->agent_id;
                                    // $preAfiliation->code_agency         = $this->getCodeAgency();
                                    // $preAfiliation->owner_code          = $this->getOwnerCode();
                                    // $preAfiliation->created_by          = Auth::user()->name;
                                    // $preAfiliation->status              = 'PRE-APROBADA';
                                    // //------------------------------------------------------------------------------------------------------------------------
                                    // $preAfiliation->save();

                                    // /** 1.- Cargo la data de la pre afiliacion en la tabla de afiliados */
                                    // for ($i = 0; $i < count($data_records); $i++) {

                                    //     /** Guardar los datos en la tabla de afiliados */
                                    //     $detailsAfiliationPlans = AfilliationCorporatePlan::create([
                                    //         'affiliation_corporate_id'  => $preAfiliation->id,
                                    //         'code_affiliation'          => $preAfiliation->code,
                                    //         'plan_id'                   => $data_records[$i]['plan_id'],
                                    //         'coverage_id'               => $data_records[$i]['coverage_id'],
                                    //         'age_range_id'              => $data_records[$i]['age_range_id'],
                                    //         'total_persons'             => $data_records[$i]['total_persons'],
                                    //         'fee'                       => $data_records[$i]['fee'],
                                    //         'subtotal_anual'            => $data_records[$i]['subtotal_anual'],
                                    //         'subtotal_quarterly'        => $data_records[$i]['subtotal_quarterly'],
                                    //         'subtotal_biannual'         => $data_records[$i]['subtotal_biannual'],
                                    //         'subtotal_monthly'          => $data_records[$i]['subtotal_monthly'],
                                    //         'status'                    => 'PRE-APROBADA',
                                    //         'created_by'                => Auth::user()->name,
                                    //     ]);

                                    // }

                                } catch (\Throwable $th) {
                                    dd($th);
                                    // $parte_entera = 0;
                                }
                            }),

                    BulkAction::make('increase')
                        ->label('Incrementar Costos')
                        ->color('success')
                        ->icon('fluentui-document-multiple-percent-24')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ExtraLarge)
                        ->modalHeading('Incremento de Coberturas')
                        ->modalDescription('Esta acción aplica un aumento en porcentaje a todas las coberturas seleccionadas.')
                        ->modalIcon('fluentui-document-multiple-percent-24')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Fieldset::make()
                                ->schema([
                                    TextInput::make('increase')
                                        ->helperText('El porcentaje debe ser un valor entre 0 y 100. Este aumento se aplica sobre todas las coberturas seleccionadas.')
                                        ->label('Porcentaje(%) de Incremento')
                                        ->numeric()
                                        ->required(),
                                    Textarea::make('description')
                                        ->rows(3)
                                        ->label('Observaciones')
                                        ->placeholder('Debe explicar el motivo del aumento.')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Campo requerido',
                                        ])
                                ])->columns(1)
                            ])
                        ->action(function (Collection $records, array $data) {

                            if ($data['increase'] > 100 || $data['increase'] < 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('El porcentaje debe ser un valor entre 0 y 100')
                                    ->send();
                                return;
                            }

                            foreach ($records as $record) {
                                $record->subtotal_anual     = DetailCorporateQuotesController::coverage_increase($record->subtotal_anual, $data['increase']);
                                $record->subtotal_quarterly = DetailCorporateQuotesController::coverage_increase($record->subtotal_quarterly, $data['increase']);
                                $record->subtotal_biannual  = DetailCorporateQuotesController::coverage_increase($record->subtotal_biannual, $data['increase']);
                                $record->subtotal_monthly   = DetailCorporateQuotesController::coverage_increase($record->subtotal_monthly, $data['increase']);
                                $record->save();
                            }

                            Notification::make()
                                ->success()
                                ->title('Incremento realizado con éxito')
                                ->send();
                        }),
                    BulkAction::make('discount')
                        ->label('Aplicar Descuento')
                        ->color('danger')
                        ->icon('fluentui-document-multiple-percent-24')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ExtraLarge)
                        ->modalHeading('Descuento de Coberturas')
                        ->modalDescription('Esta acción aplica un descuento en porcentaje a todas las coberturas seleccionadas.')
                        ->modalIcon('fluentui-document-multiple-percent-24')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Fieldset::make()
                                ->schema([
                                    TextInput::make('discount')
                                        ->helperText('El porcentaje debe ser un valor entre 0 y 100. Este descuento se aplica sobre todas las coberturas seleccionadas.')
                                        ->label('Porcentaje(%) de Descuento')
                                        ->numeric()
                                        ->required(),
                                    Textarea::make('description')
                                        ->rows(3)
                                        ->label('Observaciones')
                                        ->placeholder('Debe explicar el motivo del aumento.')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Campo requerido',
                                        ])
                                ])->columns(1)
                        ])
                        ->action(function (Collection $records, array $data) {

                            if ($data['discount'] > 100 || $data['discount'] < 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('El porcentaje debe ser un valor entre 0 y 100')
                                    ->send();
                                return;
                            }

                            foreach ($records as $record) {
                                $record->subtotal_anual     = DetailCorporateQuotesController::coverage_discount($record->subtotal_anual, $data['discount']);
                                $record->subtotal_quarterly = DetailCorporateQuotesController::coverage_discount($record->subtotal_quarterly, $data['discount']);
                                $record->subtotal_biannual  = DetailCorporateQuotesController::coverage_discount($record->subtotal_biannual, $data['discount']);
                                $record->subtotal_monthly   = DetailCorporateQuotesController::coverage_discount($record->subtotal_monthly, $data['discount']);
                                $record->save();
                            }

                            Notification::make()
                                ->success()
                                ->title('Cotización actualizada con éxito')
                                ->send();
                        })
                ]),
            ]);
    }

    public static function getFee(Get $get, Set $set): void
    {
        if ($get('age_range_id') == null || $get('coverage_id') == null) {
            $set('total_persons', '');
            $set('subtotal', '');
            return;
        }

        $fee = Fee::select('price', 'coverage_id')
            ->where('coverage_id', $get('coverage_id'))
            ->where('age_range_id', $get('age_range_id'))
            ->first();

        Log::info($fee, ['rango' => $get('age_range_id'), 'coverage' => $get('coverage_id')]);
        $calculo = $get('total_persons') * $fee->price;
        $set('subtotal', number_format($calculo, 2, '.', ''));
    }
}