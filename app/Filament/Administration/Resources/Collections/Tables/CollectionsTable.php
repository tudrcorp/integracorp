<?php

namespace App\Filament\Administration\Resources\Collections\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

use Carbon\Carbon;
use App\Models\Collection;
use App\Models\Affiliation;
use Filament\Actions\Action;
use App\Mail\MailAvisoDeCobro;
use App\Jobs\CreateAvisoDeCobro;
use Filament\Actions\ActionGroup;
use Filament\Tables\Filters\Filter;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Filament\Schemas\Components\Grid;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextInputColumn;

class CollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description('Registro de indicadores de cobranza según frecuencia de cobro')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('include_date')
                    ->label('Fecha')
                    ->badge()
                    ->sortable()
                    ->icon('heroicon-s-calendar-days')
                    ->searchable(),

                TextColumn::make('collection_invoice_number')
                ->sortable()
                    ->badge()
                    ->icon('heroicon-s-document-text')
                    ->label('Nro. de Aviso')
                    ->searchable(),
                TextColumn::make('quote_number')
                ->sortable()
                    ->badge()
                    ->icon('heroicon-m-tag')
                    ->label('Cotización')
                    ->searchable(),
                TextColumn::make('affiliation_code')
                ->sortable()
                    ->badge()
                    ->icon('heroicon-s-user-group')
                    ->label('Afiliación')
                    ->searchable(),

                TextColumn::make('code_agency')
                ->sortable()
                    ->badge()
                    ->icon('heroicon-s-building-library')
                    ->label('Agencia')
                    ->searchable(),
                TextColumn::make('agent.name')
                ->sortable()
                    ->badge()
                    ->icon('heroicon-m-user')
                    ->label('Agente')
                    ->numeric()
                    ->searchable(),
                TextColumn::make('affiliate_full_name')
                ->sortable()
                    ->label('Afiliado')
                    ->searchable(),
                TextColumn::make('affiliate_contact')
                ->sortable()
                    ->label('Contacto')
                    ->searchable(),
                TextColumn::make('affiliate_ci_rif')
                ->sortable()
                    ->label('C.I./R.I.F.')
                    ->searchable(),
                TextColumn::make('affiliate_phone')
                ->sortable()
                    ->label('Número de teléfono')
                    ->searchable(),
                TextColumn::make('affiliate_email')
                ->sortable()
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('affiliate_status')
                ->sortable()
                    ->label('Estatus afiliación')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ACTIVA' => 'success',
                            'INACTIVA' => 'danger',
                            default => 'secondary',
                        };
                    })
                    ->searchable(),
                TextColumn::make('plan.description')
                ->sortable()
                    ->label('Plan')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'PLAN INICIAL'  => 'azul',
                            'PLAN IDEAL'    => 'azulOscuro',
                            'PLAN ESPECIAL' => 'verde',
                            default => 'secondary',
                        };
                    })
                    ->searchable(),
                TextColumn::make('coverage.price')
                ->sortable()
                    ->suffix('US$')
                    ->numeric()
                    ->sortable(),
                // TextColumn::make('service')
                //     ->searchable(),
                TextColumn::make('persons')
                ->sortable()
                    ->label('Población')
                    ->searchable(),
                TextColumn::make('type')
                ->sortable()
                    ->label('Tipo')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'AFILIACION INDIVIDUAL' => 'primary',
                            'AFILIACIÓN CORPORATIVA' => 'verdeOpaco',
                        };
                    })
                    ->searchable(),
                TextColumn::make('reference')
                ->sortable()
                    ->label('Referencia')
                    ->searchable(),
                TextColumn::make('payment_method')
                ->sortable()
                    ->label('Metodo de pago')
                    ->searchable(),
                TextColumn::make('payment_frequency')
                ->sortable()
                    ->label('Frecuencia de pago')
                    ->searchable(),
                TextInputColumn::make('next_payment_date')
                ->sortable()
                    ->label('Proximo pago')
                    ->searchable(),
                TextColumn::make('total_amount')
                ->sortable()
                    ->label('Monto total')
                    ->numeric()
                    ->suffix('US$')
                    ->sortable(),
                TextColumn::make('status')
                ->sortable()
                    ->badge()
                    ->label('Estado')
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PAGADO' => 'success',
                            'POR PAGAR' => 'warning',
                            'CANCELADO' => 'danger',
                            default => 'secondary',
                        };
                    })
                    ->searchable(),
                TextColumn::make('created_by')
                ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                ->sortable()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                ->sortable()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                SelectFilter::make('payment_frequency')
                    ->options([
                        'ANUAL'      => 'ANUAL',
                        'SEMESTRAL'  => 'SEMESTRAL',
                        'TRIMESTRAL' => 'TRIMESTRAL',
                        'MENSUAL'    => 'MENSUAL',
                    ])
                    ->label('Frecuencia de Pago'),
                SelectFilter::make('plan_id')
                    ->relationship('plan', 'description')
                    ->label('Planes'),
                SelectFilter::make('payment_method')
                    ->options([
                        'EFECTIVO US$'      => 'EFECTIVO US$',
                        'ZELLE'             => 'ZELLE',
                        'PAGO MOVIL VES'    => 'PAGO MOVIL VES',
                        'TRANSFERENCIA VES' => 'TRANSFERENCIA VES'
                    ])
                    ->label('Metodo de Pago'),
                SelectFilter::make('status')
                    ->options([
                        'POR PAGAR' => 'POR PAGAR',
                        'PAGADO'    => 'PAGADO',
                    ])
                    ->label('Estatus'),
                SelectFilter::make('bank')
                    ->options([
                        'CHASE BANK'                => 'CHASE BANK',
                        'BANK OF AMERICA'           => 'BANK OF AMERICA',
                        'BANESCO, S.A-US$'          => 'BANESCO, S.A - US$',
                        'BANCAMIGA - US$'           => 'BANCAMIGA - US$',
                        'BANCAMIGA - VES'           => 'BANCAMIGA - VES',
                        'BANCO DE VENEZUELA - US$'  => 'BANCO DE VENEZUELA - US$',
                        'BANCO DE VENEZUELA - VES'  => 'BANCO DE VENEZUELA - VES',
                    ])
                    ->label('Banco'),

            ])
            ->recordActions([
                ActionGroup::make([

                    /**ENVIO MANUAL DEL AVISO DE COBRO */
                    Action::make('send_email')
                        ->label('Enviar Aviso de Cobro')
                        ->icon('heroicon-c-arrow-uturn-right')
                        ->modalHeading('Envío manual de aviso de cobro')
                        ->color('azul')
                        ->form([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('email')
                                        ->helperText('Si deja este campo en blanco, se enviara el aviso de cobro al correo del afiliado registrado.')
                                        ->email()
                                        ->maxLength(255)
                                        ->label('Email')
                                        ->placeholder('ejemplo@gmail.com'),
                                    TextInput::make('phone')
                                        ->helperText('Si deja este campo en blanco, se enviara el aviso de cobro al teléfono del afiliado registrado.')
                                        ->maxLength(255)
                                        ->label('Teléfono')
                                        ->tel()
                                        ->placeholder('04127869087')
                                ])
                        ])
                        ->action(function (Collection $record, array $data) {

                            try {

                                $name_pdf = 'ADP-' . $record->collection_invoice_number . '.pdf';

                                if ($data['email'] == null) {
                                    Mail::to($record->affiliate_email)->send(new MailAvisoDeCobro($name_pdf));
                                }

                                if ($data['email'] != null) {
                                    Mail::to($data['email'])->send(new MailAvisoDeCobro($name_pdf));
                                }

                                /**
                                 * TODO
                                 * Debo agregar la logica para el envio del aviso de cobro por whatsapp
                                 * ---------------------------------------------------------------------
                                 */

                                /**
                                 * Notificacion al usuario
                                 *--------------------------------------------------------------------
                                 */
                                Notification::make()
                                    ->title('ENVIADO')
                                    ->body('Aviso de cobro enviado correctamente')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->success()
                                    ->send();

                                /**
                                 * LOG
                                 */
                                LogController::log(Auth::user()->id, 'Envió de aviso de cobro', 'Modulo Cobranza', 'DESCARGAR');
                            } catch (\Throwable $th) {
                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(fn(Collection $record) => $record->status == 'PAGADO'),

                    /**DESCARGAR PDF */
                    Action::make('download_pdf')
                        ->label('Descargar PDF')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->action(function (Collection $record) {

                            try {
                                /**
                                 * Descargar el documento asociado a la cotizacion
                                 * ruta: storage/
                                 */
                                $path = public_path('storage/avisoDeCobro/ADP-' . $record->collection_invoice_number . '.pdf');
                                return response()->download($path);
                                /**
                                 * LOG
                                 */
                                LogController::log(Auth::user()->id, 'Descarga de documento', 'Modulo Cotizacion Individual', 'DESCARGAR');
                            } catch (\Throwable $th) {
                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /**REGENERAR PDF */
                    Action::make('regenerate_pdf')
                        ->label('Renerar PDF')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('warning')
                        ->action(function (Collection $record) {

                            try {
                                // dd($record->affiliation);

                                if ($record->type == 'AFILIACION INDIVIDUAL') {
                                    $address = Affiliation::where('code', $record->affiliation_code)->first();
                                } else {
                                    $address = AffiliationCorporate::where('code', $record->affiliation_code)->first();
                                }

                                /**Ejecutamos el Job para crea el aviso de cobro */
                                $array_data = [
                                    'invoice_number'    => $record->collection_invoice_number,
                                    'emission_date'     => $record->next_payment_date,
                                    'full_name_ti'      => $record->affiliate_full_name,
                                    'ci_rif_ti'         => $record->affiliate_ci_rif,
                                    'address_ti'        => $address->adress_ti,
                                    'phone_ti'          => $record->affiliate_phone,
                                    'email_ti'          => $record->affiliate_email,
                                    'total_amount'      => $record->total_amount,
                                    'plan'              => $record->plan->description,
                                    'coverage'          => $record->coverage->price ?? null,
                                    'frequency'         => $record->payment_frequency,
                                ];
                                // dd($array_data);
                                dispatch(new CreateAvisoDeCobro($array_data, Auth::user()));

                                Notification::make()
                                    ->title('REGENERADO CON EXITO')
                                    ->body('Aviso de cobro generado correctamente')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->success()
                                    ->send();
                                
                            } catch (\Throwable $th) {
                                dd($th);
                            }
                        }),
                        
            ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}