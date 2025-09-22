<?php

namespace App\Filament\Agents\Resources\IndividualQuotes\Tables;

use Carbon\Carbon;
use App\Models\Bitacora;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\IndividualQuote;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Actions\ExportAction;
use Illuminate\Support\HtmlString;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\MailLinkIndividualQuote;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use App\Mail\SendMailPropuestaMultiPlan;
use App\Mail\SendMailPropuestaPlanIdeal;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Mail\SendMailPropuestaPlanInicial;
use App\Http\Controllers\MessageController;
use App\Jobs\ResendEmailPropuestaEconomica;
use App\Mail\SendMailPropuestaPlanEspecial;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use App\Http\Controllers\NotificationController;
use Filament\Actions\Exports\Enums\ExportFormat;
use App\Filament\Exports\IndividualQuoteExporter;

class IndividualQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(IndividualQuote::query()->where('agent_id', Auth::user()->agent_id))
            ->defaultSort('created_at', 'desc')
            ->heading('Lista de cotizaciones generadas por el agente')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Emitida el:')
                    ->badge()
                    ->color('azulOscuro')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('count_days')
                    ->label('Transcurrido')
                    ->alignCenter()
                    ->badge()
                    ->suffix('dias')
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA'  => 'verdeOpaco',
                            'APROBADA'      => 'success',
                            'ANULADA'       => 'warning',
                            'DECLINADA'     => 'danger',
                            default         => 'azul',
                        };
                    })
                    ->searchable(),
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
            ])
            ->recordActions([
                ActionGroup::make([

                    /**EMIT */
                    Action::make('emit')
                        ->hidden(function (IndividualQuote $record) {
                            if ($record->status == 'APROBADA') {
                                return true;
                            }
                            return false;
                        })
                        ->label('Aprobar')
                        ->icon('heroicon-m-shield-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('APROBACIÓN DIRECTA PARA PRE-AFILIACIÓN')
                        ->modalIcon('heroicon-m-shield-check')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalDescription(new HtmlString(Blade::render(<<<BLADE
                                    <div class="fi-section-header-description mt-5 mb-5">
                                        Felicitaciones!.
                                        <br>
                                    Solo falta completar el formulario de pre-afiliación
                                    </div>
                            BLADE)))
                        ->action(function (IndividualQuote $record) {

                            try {

                                /**
                                 * Actualizo el status a APROBADA
                                 */
                                $record->status = 'APROBADA';
                                $record->save();

                                /**Creamos una variable de session con la cantidad dde personas en la cotizacion */
                                session()->put('persons', $record->detailsQuote()->first()->total_persons);

                                Notification::make()
                                    ->title('COTIZACION INDIVIDUAL APROBADA')
                                    ->body('Nro.' . $record->code . ', puede proceder a realizar la pre-afiliación')
                                    ->icon('heroicon-s-user-group')
                                    ->iconColor('success')
                                    ->persistent()
                                    ->success()
                                    ->send();

                                /**
                                 * Logica para enviar una notificacion a la sesion del administrador despues de crear la corizacion
                                 * ----------------------------------------------------------------------------------------------------
                                 * $record [Data de la cotizacion guardada en la base de dastos]
                                 */


                                /**
                                 * LOG
                                 */
                                LogController::log(Auth::user()->id, 'Aprobacion directa de la cotizacion Nro.' . $record->code, 'Modulo Cotizacion Individual', 'APROBADA');

                                /**
                                 * Redirecciono a la pagina para crear la afiliacion
                                 */
                                $count_plans = $record->detailsQuote()->distinct()->pluck('plan_id');
                                // dd($count_plans[0]);
                                if ($count_plans->count() == 1) {
                                    return redirect()->route('filament.agents.resources.affiliations.create', ['id' => $record->id, 'plan_id' => $count_plans[0]]);
                                }

                                return redirect()->route('filament.agents.resources.affiliations.create', ['id' => $record->id, 'plan_id' => null]);
                            } catch (\Throwable $th) {
                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.emit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(function (IndividualQuote $record) {
                            if ($record->status == 'APROBADA' || $record->status == 'EJECUTADA') {
                                return true;
                            }
                            return false;
                        }),

                    /**FORWARD */
                    Action::make('forward')
                        ->label('Reenviar')
                        ->icon('fluentui-document-arrow-right-20')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalIcon('fluentui-document-arrow-right-20')
                        ->modalHeading('Reenvío de Cotización')
                        ->modalDescription('La propuesta será enviada por email y/o teléfono!')
                        ->modalWidth(Width::ExtraLarge)
                        ->form([
                            Section::make()
                                ->schema([
                                    TextInput::make('email')
                                        ->label('Email')
                                        ->email(),
                                    Grid::make(2)->schema([
                                        Select::make('country_code')
                                            ->label('Código de país')
                                            ->options(fn () => UtilsController::getCountries())
                                            ->searchable()
                                            ->default('+58')
                                            ->live(onBlur: true),
                                        TextInput::make('phone')
                                            ->prefixIcon('heroicon-s-phone')
                                            ->tel()
                                            ->label('Número de teléfono')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $countryCode = $get('country_code');
                                                if ($countryCode) {
                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                    $set('phone', $countryCode . $cleanNumber);
                                                }
                                            }),
                                    ])
                                ])
                        ])
                        ->action(function (IndividualQuote $record, array $data) {

                            try {

                                // dd($record);

                                $email = null;
                                $phone = null;

                                if (isset($data['email'])) {
                                    
                                    $email = $data['email'];
                                    $doc = $record->code . '.pdf';
                                    
                                    if($record->plan == 1){
                                        Mail::to($data['email'])->send(new SendMailPropuestaPlanInicial($record['full_name'], $doc));
                                    }
                                    
                                    if($record->plan == 2){
                                        Mail::to($data['email'])->send(new SendMailPropuestaPlanIdeal($record['full_name'], $doc));
                                    }
                                    
                                    if ($record->plan == 3) {
                                        Mail::to($data['email'])->send(new SendMailPropuestaPlanEspecial($record['full_name'], $doc));
                                    }

                                    if ($record->plan == 'CM') {
                                        Mail::to($data['email'])->send(new SendMailPropuestaMultiPlan($record['full_name'], $doc));
                                    }

                                }

                                if (isset($data['phone'])) {
                                    
                                    $phone = $data['phone'];
                                    $nameDoc = $record->code . '.pdf';
                                    
                                    $res = NotificationController::sendQuote($phone, $nameDoc);

                                    if (!$res) {
                                        Notification::make()
                                            ->title('ERROR')    
                                            ->body('La cotización no pudo ser enviada por whatsapp. Por favor, contacte con el administrador del Sistema.')
                                            ->icon('heroicon-s-x-circle')
                                            ->iconColor('danger')
                                            ->danger()
                                            ->send();
                                    }
                                }

                                Notification::make()
                                    ->title('ENVÍO EXITOSO')
                                    ->body('La cotización fue reenviada exitosamente.')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('verde')
                                    ->success()
                                    ->send();
                                
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

                    /**FORWARD */
                    Action::make('link')
                        ->label('Link Interactivo')
                        ->icon('fluentui-document-arrow-right-20')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalIcon('fluentui-document-arrow-right-20')
                        ->modalHeading('Link Interactivo de Cotización')
                        ->modalDescription('El link será enviado por email y/o teléfono!')
                        ->modalWidth(Width::ExtraLarge)
                        ->form([
                            Section::make()
                                // ->heading('Informacion')
                                // ->description('El link puede sera enviado por email y/o telefono!')
                                ->schema([
                                    TextInput::make('email')
                                        ->label('Email')
                                        ->email(),
                                    Grid::make(2)->schema([
                                        Select::make('country_code')
                                            ->label('Código de país')
                                            ->options(fn() => UtilsController::getCountries())
                                            ->searchable()
                                            ->default('+58')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->validationMessages([
                                                'required'  => 'Campo Requerido',
                                            ]),
                                        TextInput::make('phone')
                                            ->prefixIcon('heroicon-s-phone')
                                            ->tel()
                                            ->label('Número de teléfono')
                                            ->required()
                                            ->validationMessages([
                                                'required'  => 'Campo Requerido',
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $countryCode = $get('country_code');
                                                if ($countryCode) {
                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                    $set('phone', $countryCode . $cleanNumber);
                                                }
                                            }),
                                    ])
                                ])
                        ])
                        ->action(function (IndividualQuote $record, array $data) {

                            try {

                                $email = null;
                                $phone = null;
                                $link = config('parameters.INTEGRACORP_URL') . '/in/' . Crypt::encryptString($record->id) . '/w';

                                if (isset($data['email'])) {
                                    
                                    $email = $data['email'];
                                    
                                    $email = Mail::to($email)->send(new MailLinkIndividualQuote($link));
                                    
                                    if ($email) {
                                        Notification::make()
                                            ->title('ENVIADO EXITOSO')
                                            ->body('El link fue enviado por email exitosamente.')
                                            ->icon('heroicon-s-check-circle')
                                            ->iconColor('verde')
                                            ->success()
                                            ->send();
                                    }
                                }

                                if (isset($data['phone'])) {
                                    $phone = $data['phone'];
                                    $wp = NotificationController::sendLinkIndividualQuote($phone, $link);
                                    if ($wp) {

                                        Notification::make()
                                            ->title('ENVIADO EXITOSO')
                                            ->body('El link fue enviado por whatsapp exitosamente.')
                                            ->icon('heroicon-s-check-circle')
                                            ->iconColor('verde')
                                            ->success()
                                            ->send();
                                    } else {

                                        Notification::make()
                                            ->title('ERROR')    
                                            ->body('El link no pudo ser enviado por whatsapp. Por favor, contacte con el administrador del Sistema.')
                                            ->icon('heroicon-s-x-circle')
                                            ->iconColor('danger')
                                            ->danger()
                                            ->send();
                                    }
                                    
                                }
                                

                            } catch (\Throwable $th) {
                                dd($th);
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

                    /* DESCARGAR DOCUMENTO */
                    Action::make('download')
                            ->label('Descargar Cotización')
                            ->icon('heroicon-s-arrow-down-on-square-stack')
                            ->color('verde')
                            ->requiresConfirmation()
                            ->modalHeading('DESCARGAR COTIZACION')
                            ->modalWidth(Width::ExtraLarge)
                            ->modalIcon('heroicon-s-arrow-down-on-square-stack')
                            ->modalDescription('Descargará un archivo PDF al hacer clic en confirmar!.')
                            ->action(function (IndividualQuote $record, array $data) {

                                try {

                                    if (!file_exists(public_path('storage/quotes/' . $record->code . '.pdf'))) {

                                        Notification::make()
                                            ->title('NOTIFICACIÓN')
                                            ->body('El documento asociado a la cotización no se encuentra disponible. Por favor, intente nuevamente en unos segundos.')
                                            ->icon('heroicon-s-x-circle')
                                            ->iconColor('warning')
                                            ->warning()
                                            ->send();

                                        return;
                                    }
                                    /**
                                     * Descargar el documento asociado a la cotizacion
                                     * ruta: storage/
                                     */
                                    $path = public_path('storage/quotes/' . $record->code . '.pdf');
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

                    Action::make('add_observations')
                        ->label('Agregar Observaciones')
                        ->icon('heroicon-s-hand-raised')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->requiresConfirmation()
                        ->modalHeading('OBSERVACIONES DEL AGENTE')
                        ->modalIcon('heroicon-s-hand-raised')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalDescription('Envíanos su inquietud o comentarios!')
                        ->form([
                            Section::make()
                                ->schema([
                                    Textarea::make('description')
                                        ->label('Observaciones')
                                        ->rows(4)
                                ])
                        ])
                        ->action(function (IndividualQuote $record, array $data) {

                            try {

                                $bitacora = new Bitacora();
                                $bitacora->individual_quote()->associate($record);
                                $bitacora->user()->associate(Auth::user());
                                $bitacora->details = $data['description'];
                                $bitacora->save();

                                Notification::make()
                                    ->body('Las observaciones fueron registradas exitosamente.')
                                    ->success()
                                    ->send();

                                $notoficationWp = NotificationController::saddObervationToIndividualQuote($record->code, Auth::user()->name, $data['description']);
                                    
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
                ])
                ->icon('heroicon-c-ellipsis-vertical')
                ->color('azulOscuro')
                ->hidden(function (IndividualQuote $record) {
                    return $record->status == 'ANULADA' || $record->status == 'DECLINADA';
                })
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ])->striped();
    }
}