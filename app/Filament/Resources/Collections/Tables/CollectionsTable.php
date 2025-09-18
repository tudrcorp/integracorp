<?php

namespace App\Filament\Resources\Collections\Tables;

use Carbon\Carbon;
use App\Models\Collection;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Mail\MailAvisoDeCobro;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Filament\Actions\BulkActionGroup;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

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
                    ->icon('heroicon-s-calendar-days')
                    ->searchable(),

                TextColumn::make('collection_invoice_number')
                    ->badge()
                    ->icon('heroicon-s-document-text')
                    ->label('Nro. de Aviso')
                    ->searchable(),
                TextColumn::make('quote_number')
                    ->badge()
                    ->icon('heroicon-m-tag')
                    ->label('Cotización')
                    ->searchable(),
                TextColumn::make('affiliation_code')
                    ->badge()
                    ->icon('heroicon-s-user-group')
                    ->label('Afiliación')
                    ->searchable(),

                TextColumn::make('code_agency')
                    ->badge()
                    ->icon('heroicon-s-building-library')
                    ->label('Agencia')
                    ->searchable(),
                TextColumn::make('agent.name')
                    ->badge()
                    ->icon('heroicon-m-user')
                    ->label('Agente')
                    ->numeric()
                    ->searchable(),
                TextColumn::make('affiliate_full_name')
                    ->label('Afiliado')
                    ->searchable(),
                TextColumn::make('affiliate_contact')
                    ->label('Contacto')
                    ->searchable(),
                TextColumn::make('affiliate_ci_rif')
                    ->label('C.I./R.I.F.')
                    ->searchable(),
                TextColumn::make('affiliate_phone')
                    ->label('Número de teléfono')
                    ->searchable(),
                TextColumn::make('affiliate_email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('affiliate_status')
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
                    ->suffix('US$')
                    ->numeric()
                    ->sortable(),
                // TextColumn::make('service')
                //     ->searchable(),
                TextColumn::make('persons')
                    ->label('Población')
                    ->searchable(),
                TextColumn::make('type')
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
                    ->label('Referencia')
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->label('Metodo de pago')
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de pago')
                    ->searchable(),
                TextColumn::make('next_payment_date')
                    ->badge()
                    ->icon('heroicon-s-calendar-days')
                    ->color('warning')
                    ->label('Proximo pago')
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label('Monto total')
                    ->numeric()
                    ->suffix('US$')
                    ->sortable(),
                TextColumn::make('status')
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
                TextColumn::make('days')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->searchable(),
                TextColumn::make('bank')
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
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}