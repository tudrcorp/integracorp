<?php

namespace App\Filament\Resources\AffiliationCorporates\Tables;

use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\DetailCorporateQuote;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AffiliationCorporatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->defaultSort('created_at', 'desc')
            ->heading('AFILIACIONES CORPORATIVAS')
            ->description('Lista de afiliaciones corporativas registradas en el sistema')
            ->columns([
                TextColumn::make('code')
                    ->label('Codigo')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('code_corporate_quote')
                    ->label('Cotizacion')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('code_agency')
                    ->label('Agencia')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('code_agent')
                    ->label('Agente')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('full_name_agent')
                    ->label('Agente')
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

                /**
                 * INFORMACION ILS
                 * ------------------------------------------------------------
                 */
                TextColumn::make('vaucher_ils')
                    ->label('Voucher ILS')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_payment_initial_ils')
                    ->label('ILS-Desde')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_payment_final_ils')
                    ->label('ILS-Hasta')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('document_ils')
                    ->alignment(Alignment::Center)
                    ->label('Documento ILS')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->document_ils
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->document_ils
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->document_ils);
                    })
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
                //------------------------------------------------------------------

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
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA'          => 'heroicon-c-information-circle',
                            'ACTIVA'                => 'heroicon-s-check-circle',
                            'PENDIENTE'             => 'heroicon-s-exclamation-circle',
                            'EXCLUIDO'              => 'heroicon-c-x-circle',
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
                Action::make('affiliation_activate')
                    ->label('Activar')
                    ->color('success')
                    ->icon('heroicon-s-check-circle')
                    ->requiresConfirmation()
                    ->modalWidth(Width::ExtraLarge)
                    ->modalHeading('Activar afiliacion')
                    ->form([
                        Section::make('ACTIVAR AFILIACIÓN')
                            ->description('Formulario de activación de afiliación. Campo Requerido(*)')
                            ->icon('heroicon-s-check-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('vaucher_ils')
                                        ->label('Vaucher ILS')
                                        ->required(),
                                ]),
                                Grid::make(2)->schema([
                                    DatePicker::make('date_payment_initial_ils')
                                        ->label('Desde')
                                        ->format('d-m-Y')
                                        ->required(),
                                    DatePicker::make('date_payment_final_ils')
                                        ->label('Hasta')
                                        ->format('d-m-Y')
                                        ->required(),

                                ]),
                                Grid::make(1)->schema([
                                    FileUpload::make('document_ils')
                                        ->label('Documento/Comprobante ILS')
                                        ->required(),
                                ])
                            ])
                    ])
                    ->action(function (AffiliationCorporate $record, array $data): void {
                        $record->update([
                            'vaucher_ils'               => $data['vaucher_ils'],
                            'date_payment_initial_ils'  => $data['date_payment_initial_ils'],
                            'date_payment_final_ils'    => $data['date_payment_final_ils'],
                            'document_ils'              => $data['document_ils'],
                            'status'                    => 'ACTIVA',
                        ]);

                        $record->status_log_corporate_affiliations()->create([
                            'affiliation_corporate_id'  => $record->id,
                            'action'                    => 'ACTIVACIÓN',
                            'observation'               => 'AFILIACIÓN ACTIVADA. FECHA: ' . now()->format('d-m-Y'),
                            'updated_by'                => Auth::user()->name
                        ]);

                        Notification::make()
                            ->success()
                            ->title('AFILIACIÓN ACTIVADA')
                            ->send();
                    })
                    ->hidden(function (AffiliationCorporate $record): bool {
                        return $record->status !== 'PRE-APROBADA';
                    }),
                Action::make('upload')
                    ->label('Cargar pago')
                    ->color('verde')
                    ->icon('heroicon-s-cloud-arrow-up')
                    ->form([
                        Section::make('CARGA DE COMPROBANTE')
                            ->icon('heroicon-s-cloud-arrow-up')
                            ->schema([
                                Grid::make(3)->schema([
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
                                                    ->where('plan_id', $record->plan_id)
                                                    ->where('coverage_id', $record->coverage_id)
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_anual'));
                                            }
                                            if ($get('payment_frequency') == 'TRIMESTRAL') {

                                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_quarterly')
                                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                                    ->where('plan_id', $record->plan_id)
                                                    ->where('coverage_id', $record->coverage_id)
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_quarterly'));
                                            }
                                            if ($get('payment_frequency') == 'SEMESTRAL') {

                                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_biannual')
                                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                                    ->where('plan_id', $record->plan_id)
                                                    ->where('coverage_id', $record->coverage_id)
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_biannual'));
                                            }
                                            if ($get('payment_frequency') == 'MENSUAL') {

                                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_monthly')
                                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                                    ->where('plan_id', $record->plan_id)
                                                    ->where('coverage_id', $record->coverage_id)
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_monthly'));
                                            }
                                        }),



                                ]),
                                Grid::make(2)->schema([

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
                                        ->validationMessages([
                                            'numeric'   => 'El campo es numerico',
                                        ]),

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
                            'plan_id'               => $record->plan_id,
                            'coverage_id'           => $record->coverage_id,
                            'activated_at'          => now()->format('d-m-Y'),
                            'family_members'        => AffiliateCorporate::select('affiliation_id')->where('affiliation_id', $record->id)->count(),
                            'document'              => $data['document'],
                            'status'                => 'ACTIVA',
                        ]);

                        if ($data['payment_frequency'] == 'ANUAL') {
                            //busco el valor de la cotizacion de acuerdo al plan y a la covertura
                            $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_anual')
                                ->where('corporate_quote_id', $record->corporate_quote_id)
                                ->where('plan_id', $record->plan_id)
                                ->where('coverage_id', $record->coverage_id)
                                ->get();

                            $record->paid_memberships()->create([
                                'affiliation_id'        => $record->id,
                                'payment_frequency'     => $data['payment_frequency'],
                                'agent_id'              => $record->agent_id,
                                'code_agency'           => $record->code_agency,
                                'plan_id'               => $record->plan_id,
                                'coverage_id'           => $record->coverage_id,
                                'pay_amount'            => $data['pay_amount'],
                                'total_amount'          => $data['total_amount'],
                                'currency'              => $data['currency'],
                                'payment_date'          => now()->format('d-m-Y'),
                                'prox_payment_date'     => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                'reference_payment'     => $data['reference_payment'],
                                'observations_payment'  => $data['observations_payment'],
                                'document'              => $data['document'],
                                'renewal_date'          => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                'status'                => 'PAGADO',
                            ]);
                        }

                        if ($data['payment_frequency'] == 'TRIMESTRAL') {
                            $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_quarterly')
                                ->where('corporate_quote_id', $record->corporate_quote_id)
                                ->where('plan_id', $record->plan_id)
                                ->where('coverage_id', $record->coverage_id)
                                ->get();

                            $record->paid_memberships()->create([
                                'affiliation_id'        => $record->id,
                                'payment_frequency'     => $data['payment_frequency'],
                                'agent_id'              => $record->agent_id,
                                'code_agency'           => $record->code_agency,
                                'plan_id'               => $record->plan_id,
                                'coverage_id'           => $record->coverage_id,
                                'pay_amount'            => $data['pay_amount'],
                                'total_amount'          => $data['total_amount'],
                                'currency'              => $data['currency'],
                                'payment_date'          => now()->format('d-m-Y'),
                                'prox_payment_date'     => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addMonths(3)->format('d-m-Y'),
                                'reference_payment'     => $data['reference_payment'],
                                'observations_payment'  => $data['observations_payment'],
                                'document'              => $data['document'],
                                'renewal_date'          => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                'status'                => 'PAGADO',
                            ]);
                        }

                        if ($data['payment_frequency'] == 'SEMESTRAL') {
                            $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_biannual')
                                ->where('corporate_quote_id', $record->corporate_quote_id)
                                ->where('plan_id', $record->plan_id)
                                ->where('coverage_id', $record->coverage_id)
                                ->get();

                            $record->paid_memberships()->create([
                                'affiliation_id'        => $record->id,
                                'payment_frequency'     => $data['payment_frequency'],
                                'agent_id'              => $record->agent_id,
                                'code_agency'           => $record->code_agency,
                                'plan_id'               => $record->plan_id,
                                'coverage_id'           => $record->coverage_id,
                                'pay_amount'            => $data['pay_amount'],
                                'total_amount'          => $data['total_amount'],
                                'currency'              => $data['currency'],
                                'payment_date'          => now()->format('d-m-Y'),
                                'prox_payment_date'     => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addMonths(6)->format('d-m-Y'),
                                'reference_payment'     => $data['reference_payment'],
                                'observations_payment'  => $data['observations_payment'],
                                'document'              => $data['document'],
                                'renewal_date'          => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                'status'                => 'PAGADO',
                            ]);
                        }

                        if ($data['payment_frequency'] == 'MENSUAL') {
                            $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_monthly')
                                ->where('corporate_quote_id', $record->corporate_quote_id)
                                ->where('plan_id', $record->plan_id)
                                ->where('coverage_id', $record->coverage_id)
                                ->get();

                            $record->paid_memberships()->create([
                                'affiliation_id'        => $record->id,
                                'payment_frequency'     => $data['payment_frequency'],
                                'agent_id'              => $record->agent_id,
                                'code_agency'           => $record->code_agency,
                                'plan_id'               => $record->plan_id,
                                'coverage_id'           => $record->coverage_id,
                                'pay_amount'            => $data['pay_amount'],
                                'total_amount'          => $data['total_amount'],
                                'currency'              => $data['currency'],
                                'payment_date'          => now()->format('d-m-Y'),
                                'prox_payment_date'     => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addMonths()->format('d-m-Y'),
                                'reference_payment'     => $data['reference_payment'],
                                'observations_payment'  => $data['observations_payment'],
                                'document'              => $data['document'],
                                'renewal_date'          => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                                'status'                => 'PAGADO',
                            ]);
                        }
                    }),
                Action::make('change_status')
                    ->label('Actualizar estatus')
                    ->color('azulOscuro')
                    ->icon('heroicon-s-check-circle')
                    ->requiresConfirmation()
                    ->modalWidth(Width::ExtraLarge)
                    ->modalHeading('ACCIONES')
                    ->form([
                        Section::make()
                            ->heading('ACCIONES')
                            ->description('Seleccione la accion que desea realizar')
                            ->icon('heroicon-s-check-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    Radio::make('action')
                                        ->label('Que accion deseas realizar?')
                                        ->options([
                                            'observation' => 'Anadir observaciones',
                                            'status'      => 'Actualizar estatus',
                                            'exclude'     => 'Excluir Afiliación',
                                        ])
                                        ->live()
                                        ->required()
                                    // ->inline()
                                ]),

                                Grid::make(1)->schema([
                                    Textarea::make('description')
                                        ->label('Observaciones')
                                        ->autosize()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $set('description', strtoupper($state));
                                        })
                                ])->hidden(fn(Get $get) => $get('action') != 'observation'),

                                Grid::make(1)->schema([
                                    Select::make('status')
                                        ->label('Estatus')
                                        ->options([
                                            'PENDIENTE' => 'PENDIENTE',
                                        ])
                                        ->searchable()
                                        ->preload(),
                                    Textarea::make('description')
                                        ->autosize()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $set('description', strtoupper($state));
                                        })
                                ])->hidden(fn(Get $get) => $get('action') != 'status'),

                                Grid::make(1)->schema([
                                    DatePicker::make('date_egress')
                                        ->label('Fecha de egreso')
                                        ->format('d-m-Y'),
                                    Textarea::make('description')
                                        ->label('Observaciones')
                                        ->autosize()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $set('description', strtoupper($state));
                                        })
                                ])->hidden(fn(Get $get) => $get('action') != 'exclude'),
                            ])
                    ])
                    ->action(function (AffiliationCorporate $record, array $data): void {
                        if ($data['action'] == 'observation') {
                            $record->status_log_corporate_affiliations()->create([
                                'affiliation_corporate_id' => $record->id,
                                'action'         => 'AGREGO OBSERVACION',
                                'observation'    => $data['description'],
                                'updated_by'     => Auth::user()->name
                            ]);
                            Notification::make()
                                ->title('AFILIACION ACTUALIZADA')
                                ->success()
                                ->send();
                            return;
                        }

                        if ($data['action'] == 'status') {
                            $record->update([
                                'status' => $data['status'],
                            ]);
                            $record->status_log_corporate_affiliations()->create([
                                'affiliation_corporate_id' => $record->id,
                                'action'         => 'CAMBIO ESTATUS A: ' . $data['status'],
                                'observation'    => $data['description'],
                                'updated_by'     => Auth::user()->name
                            ]);
                            Notification::make()
                                ->title('AFILIACION ACTUALIZADA')
                                ->success()
                                ->send();
                            return;
                        }

                        if ($data['action'] == 'exclude') {
                            $record->update([
                                'status' => 'EXCLUIDO',
                            ]);
                            $record->status_log_corporate_affiliations()->create([
                                'affiliation_corporate_id'    => $record->id,
                                'action'            => 'EXCLUYO AFILIACION, FECHA DE EGRESO: ' . $data['date_egress'],
                                'observation'       => $data['description'],
                                'updated_by'        => Auth::user()->name
                            ]);
                            Notification::make()
                                ->title('AFILIACION ACTUALIZADA')
                                ->success()
                                ->send();
                            return;
                        }


                        Notification::make()
                            ->title('AFILIACION ACTUALIZADA')
                            ->success()
                            ->send();
                    }),
            ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}