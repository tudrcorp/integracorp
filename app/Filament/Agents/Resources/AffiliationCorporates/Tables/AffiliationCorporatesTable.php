<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\Tables;

use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use App\Models\AffiliateCorporate;
use Illuminate\Support\Facades\Log;
use App\Models\AffiliationCorporate;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;

class AffiliationCorporatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(AffiliationCorporate::query()->where('agent_id', Auth::user()->agent_id))
            ->defaultSort('created_at', 'desc')
            ->heading('AFILIACIONES CORPORATIVAS')
            ->description('Lista de afiliaciones corporativas registradas en el sistema')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('corporate_quote.code')
                    ->label('Nro. de cotización')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('full_name_con')
                    ->label('Nombre contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('email_con')
                    ->label('Email contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('phone_con')
                    ->label('Telefono contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('adress_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city_id_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state_id_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country_id_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('cuestion_1')
                    ->label('Prgunta 1')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_2')
                    ->label('Prgunta 2')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_3')
                    ->label('Prgunta 3')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_4')
                    ->label('Prgunta 4')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_5')
                    ->label('Prgunta 5')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_6')
                    ->label('Prgunta 6')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_7')
                    ->label('Prgunta 7')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_8')
                    ->label('Prgunta 8')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_9')
                    ->label('Prgunta 9')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_10')
                    ->label('Prgunta 10')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_11')
                    ->label('Prgunta 11')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_12')
                    ->label('Prgunta 12')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_13')
                    ->label('Prgunta 13')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_14')
                    ->label('Prgunta 14')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('date_today')
                    ->label('Fecha')
                    // ->dateTime()
                    ->searchable(),
                TextColumn::make('created_by')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estatus')

                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'PRE-APROBADA'          => 'success',
                            'ACTIVA'                => 'success',
                            'PENDIENTE'             => 'warning',
                            'EXCLUIDO'              => 'danger',
                        };
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('upload')
                        ->label('Cargar comprobante')
                        ->icon('heroicon-s-cloud-arrow-up')
                        ->form([
                            Section::make('CARGA DE COMPROBANTE')
                                ->icon('heroicon-s-cloud-arrow-up')
                                ->schema([
                                    Grid::make(3)->schema([
                                        Select::make('plan_id')
                                            ->label('Plan(es) cotizados')
                                            ->live()
                                            ->options(function (AffiliationCorporate $record) {
                                                Log::info($record);
                                                $plans = DetailCorporateQuote::join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                                                    ->join('corporate_quotes', 'detail_corporate_quotes.corporate_quote_id', '=', 'corporate_quotes.id')
                                                    ->where('corporate_quotes.id', $record->corporate_quote_id)
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
                                            ->options(function (AffiliationCorporate $record, Get $get) {
                                                $coverages = DetailCorporateQuote::join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                                                    ->join('corporate_quotes', 'detail_corporate_quotes.corporate_quote_id', '=', 'corporate_quotes.id')
                                                    ->where('corporate_quotes.id', $record->corporate_quote_id)
                                                    ->where('detail_corporate_quotes.plan_id', $get('plan_id'))
                                                    ->select('coverages.id as coverage_id', 'coverages.price as description')
                                                    ->distinct() // Asegurarse de que no haya duplicados
                                                    ->get()
                                                    ->pluck('description', 'coverage_id');

                                                return $coverages;
                                            })
                                            ->searchable()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required'  => 'Campo Requerido',
                                            ])
                                            ->preload(),

                                        Select::make('payment_frequency')
                                            ->label('Frecuencia de pago')
                                            ->live()
                                            ->options([
                                                'ANUAL'      => 'ANUAL',
                                                'TRIMESTRAL' => 'TRIMESTRAL',
                                                'SEMESTRAL'  => 'SEMESTRAL',
                                                'MENSUAL'    => 'MENSUAL'
                                            ])
                                            ->searchable()
                                            ->live()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required'  => 'Campo Requerido',
                                            ])
                                            ->preload()
                                            ->afterStateUpdated(function ($state, $set, Get $get, AffiliationCorporate $record) {
                                                if ($get('payment_frequency') == 'ANUAL') {
                                                    //busco el valor de la cotizacion de acuerdo al plan y a la covertura
                                                    $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_anual')
                                                        ->where('corporate_quote_id', $record->corporate_quote_id)
                                                        ->where('plan_id', $get('plan_id'))
                                                        ->where('coverage_id', $get('coverage_id'))
                                                        ->get();

                                                    $set('total_amount', $data_quote->sum('subtotal_anual'));
                                                }
                                                if ($get('payment_frequency') == 'TRIMESTRAL') {

                                                    $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_quarterly')
                                                        ->where('corporate_quote_id', $record->corporate_quote_id)
                                                        ->where('plan_id', $get('plan_id'))
                                                        ->where('coverage_id', $get('coverage_id'))
                                                        ->get();

                                                    $set('total_amount', $data_quote->sum('subtotal_quarterly'));
                                                }
                                                if ($get('payment_frequency') == 'SEMESTRAL') {

                                                    $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_biannual')
                                                        ->where('corporate_quote_id', $record->corporate_quote_id)
                                                        ->where('plan_id', $get('plan_id'))
                                                        ->where('coverage_id', $get('coverage_id'))
                                                        ->get();

                                                    $set('total_amount', $data_quote->sum('subtotal_biannual'));
                                                }
                                                if ($get('payment_frequency') == 'MENSUAL') {

                                                    $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_monthly')
                                                        ->where('corporate_quote_id', $record->corporate_quote_id)
                                                        ->where('plan_id', $get('plan_id'))
                                                        ->where('coverage_id', $get('coverage_id'))
                                                        ->get();

                                                    $set('total_amount', $data_quote->sum('subtotal_monthly'));
                                                }
                                            }),



                                    ]),
                                    Grid::make(3)->schema([

                                        TextInput::make('total_amount')
                                            ->label('Total a pagar')
                                            ->helperText('Punto(.) para separar decimales')
                                            ->prefix('US$')
                                            ->numeric()
                                            ->live(),

                                        TextInput::make('pay_amount')
                                            ->label('Monto pagado')
                                            ->helperText('Punto(.) para separar decimales')
                                            ->prefix('US$/VES')
                                            ->numeric()
                                            ->required()
                                            ->validationMessages([
                                                'required'  => 'Campo requerido',
                                                'numeric'   => 'El campo es numerico',
                                            ])
                                            ->required(),

                                        Select::make('currency')
                                            ->label('Tipo de pago')
                                            ->live()
                                            ->options([
                                                'usd'   => 'DOLARES US$',
                                                'zelle' => 'ZELLE',
                                                'ves'   => 'BOLIVARES VES',
                                                'pm'    => 'PAGO MOVIL',
                                                't'     => 'TRANSFERENCIA'
                                            ])
                                            ->searchable()
                                            ->live()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required'  => 'Campo Requerido',
                                            ])
                                            ->preload(),

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

                                    ]),
                                    Grid::make(1)->schema([
                                        FileUpload::make('document')
                                            ->label('Comprobante de pago')
                                            ->uploadingMessage('Cargando...')
                                            ->image()
                                            ->imageEditor()
                                            ->required()
                                            ->imageEditorAspectRatios([
                                                '16:9',
                                                '4:3',
                                                '1:1',
                                            ]),
                                    ]),
                                    Grid::make(1)->schema([
                                        Textarea::make('observations_payment')
                                            ->label('Observaciones')
                                            ->rows(2)
                                            ->autosize()
                                    ]),

                                ])
                        ])
                        ->action(function (AffiliationCorporate $record, array $data): void {

                            //1. Actualizamos la tabla de afiliaciones
                            $record->update([
                                'payment_frequency'     => $data['payment_frequency'],
                                'plan_id'               => $data['plan_id'],
                                'coverage_id'           => $data['coverage_id'],
                                'activated_at'          => now()->format('d-m-Y'),
                                'corporate_members'     => AffiliateCorporate::select('affiliation_corporate_id')->where('affiliation_corporate_id', $record->id)->count(),
                                'document'              => $data['document'],
                            ]);

                            if ($data['payment_frequency'] == 'ANUAL') {
                                //busco el valor de la cotizacion de acuerdo al plan y a la covertura
                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_anual')
                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                    ->where('plan_id', $data['plan_id'])
                                    ->where('coverage_id', $data['coverage_id'])
                                    ->get();

                                $record->paid_membership_corporates()->create([
                                    'affiliation_id'        => $record->id,
                                    'agent_id'              => $record->agent_id,
                                    'code_agency'           => $record->code_agency,
                                    'plan_id'               => $data['plan_id'],
                                    'coverage_id'           => $data['coverage_id'],
                                    'pay_amount'            => $data['pay_amount'],
                                    'total_amount'          => $data['total_amount'],
                                    'currency'              => $data['currency'],
                                    'payment_date'          => now()->format('d-m-Y'),
                                    'prox_payment_date'     => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                    'reference_payment'     => $data['reference_payment'],
                                    'observations_payment'  => $data['observations_payment'],
                                    'document'              => $data['document'],
                                    'renewal_date'          => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                ]);
                            }

                            if ($data['payment_frequency'] == 'TRIMESTRAL') {
                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_quarterly')
                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                    ->where('plan_id', $data['plan_id'])
                                    ->where('coverage_id', $data['coverage_id'])
                                    ->get();

                                $record->paid_membership_corporates()->create([
                                    'affiliation_id'        => $record->id,
                                    'agent_id'              => $record->agent_id,
                                    'code_agency'           => $record->code_agency,
                                    'plan_id'               => $data['plan_id'],
                                    'coverage_id'           => $data['coverage_id'],
                                    'pay_amount'            => $data['pay_amount'],
                                    'total_amount'          => $data['total_amount'],
                                    'currency'              => $data['currency'],
                                    'payment_date'          => now()->format('d-m-Y'),
                                    'prox_payment_date'     => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addMonths(3)->format('d-m-Y'),
                                    'reference_payment'     => $data['reference_payment'],
                                    'observations_payment'  => $data['observations_payment'],
                                    'document'              => $data['document'],
                                    'renewal_date'          => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                ]);
                            }

                            if ($data['payment_frequency'] == 'SEMESTRAL') {
                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_biannual')
                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                    ->where('plan_id', $data['plan_id'])
                                    ->where('coverage_id', $data['coverage_id'])
                                    ->get();

                                $record->paid_membership_corporates()->create([
                                    'affiliation_id'        => $record->id,
                                    'agent_id'              => $record->agent_id,
                                    'code_agency'           => $record->code_agency,
                                    'plan_id'               => $data['plan_id'],
                                    'coverage_id'           => $data['coverage_id'],
                                    'pay_amount'            => $data['pay_amount'],
                                    'total_amount'          => $data['total_amount'],
                                    'currency'              => $data['currency'],
                                    'payment_date'          => now()->format('d-m-Y'),
                                    'prox_payment_date'     => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addMonths(6)->format('d-m-Y'),
                                    'reference_payment'     => $data['reference_payment'],
                                    'observations_payment'  => $data['observations_payment'],
                                    'document'              => $data['document'],
                                    'renewal_date'          => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                ]);
                            }

                            if ($data['payment_frequency'] == 'MENSUAL') {
                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_monthly')
                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                    ->where('plan_id', $data['plan_id'])
                                    ->where('coverage_id', $data['coverage_id'])
                                    ->get();

                                $record->paid_membership_corporates()->create([
                                    'affiliation_id'        => $record->id,
                                    'agent_id'              => $record->agent_id,
                                    'code_agency'           => $record->code_agency,
                                    'plan_id'               => $data['plan_id'],
                                    'coverage_id'           => $data['coverage_id'],
                                    'pay_amount'            => $data['pay_amount'],
                                    'total_amount'          => $data['total_amount'],
                                    'currency'              => $data['currency'],
                                    'payment_date'          => now()->format('d-m-Y'),
                                    'prox_payment_date'     => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addMonths()->format('d-m-Y'),
                                    'reference_payment'     => $data['reference_payment'],
                                    'observations_payment'  => $data['observations_payment'],
                                    'document'              => $data['document'],
                                    'renewal_date'          => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                ]);
                            }
                        }),

                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])->striped();
    }
}