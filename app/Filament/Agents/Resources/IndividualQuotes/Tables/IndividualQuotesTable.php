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
use Illuminate\Support\HtmlString;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Jobs\ResendEmailPropuestaEconomica;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;

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
                    ->label('Email')
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
                    
                    /**EDIT */
                    // Action::make('edit')
                    //     ->label('Editar Cotización')
                    //     ->color('warning')
                    //     ->icon('heroicon-s-pencil')
                    //     ->hidden(function (IndividualQuote $record) {
                    //         if ($record->status == 'APROBADA' || $record->status == 'EJECUTADA') {
                    //             return true;
                    //         }
                    //         return false;
                    //     }),

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
                        // ->modalDescription('Felicitaciones!')
                        // ->form([
                        //     Section::make()
                        //         ->schema([
                        //             TextEntry::make('title')
                        //                 ->alignCenter()
                        //                 ->label('Solo falta completar el formulario de pre-afiliación')
                        //         ])
                        // ])
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
                        ->modalDescription('La propuesta será enviado por email y/o teléfono!')
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
                                            ->options([
                                                '+1'   => '🇺🇸 +1 (Estados Unidos)',
                                                '+44'  => '🇬🇧 +44 (Reino Unido)',
                                                '+49'  => '🇩🇪 +49 (Alemania)',
                                                '+33'  => '🇫🇷 +33 (Francia)',
                                                '+34'  => '🇪🇸 +34 (España)',
                                                '+39'  => '🇮🇹 +39 (Italia)',
                                                '+7'   => '🇷🇺 +7 (Rusia)',
                                                '+55'  => '🇧🇷 +55 (Brasil)',
                                                '+91'  => '🇮🇳 +91 (India)',
                                                '+86'  => '🇨🇳 +86 (China)',
                                                '+81'  => '🇯🇵 +81 (Japón)',
                                                '+82'  => '🇰🇷 +82 (Corea del Sur)',
                                                '+52'  => '🇲🇽 +52 (México)',
                                                '+58'  => '🇻🇪 +58 (Venezuela)',
                                                '+57'  => '🇨🇴 +57 (Colombia)',
                                                '+54'  => '🇦🇷 +54 (Argentina)',
                                                '+56'  => '🇨🇱 +56 (Chile)',
                                                '+51'  => '🇵🇪 +51 (Perú)',
                                                '+502' => '🇬🇹 +502 (Guatemala)',
                                                '+503' => '🇸🇻 +503 (El Salvador)',
                                                '+504' => '🇭🇳 +504 (Honduras)',
                                                '+505' => '🇳🇮 +505 (Nicaragua)',
                                                '+506' => '🇨🇷 +506 (Costa Rica)',
                                                '+507' => '🇵🇦 +507 (Panamá)',
                                                '+593' => '🇪🇨 +593 (Ecuador)',
                                                '+592' => '🇬🇾 +592 (Guyana)',
                                                '+591' => '🇧🇴 +591 (Bolivia)',
                                                '+598' => '🇺🇾 +598 (Uruguay)',
                                                '+20'  => '🇪🇬 +20 (Egipto)',
                                                '+27'  => '🇿🇦 +27 (Sudáfrica)',
                                                '+234' => '🇳🇬 +234 (Nigeria)',
                                                '+212' => '🇲🇦 +212 (Marruecos)',
                                                '+971' => '🇦🇪 +971 (Emiratos Árabes)',
                                                '+92'  => '🇵🇰 +92 (Pakistán)',
                                                '+880' => '🇧🇩 +880 (Bangladesh)',
                                                '+62'  => '🇮🇩 +62 (Indonesia)',
                                                '+63'  => '🇵🇭 +63 (Filipinas)',
                                                '+66'  => '🇹🇭 +66 (Tailandia)',
                                                '+60'  => '🇲🇾 +60 (Malasia)',
                                                '+65'  => '🇸🇬 +65 (Singapur)',
                                                '+61'  => '🇦🇺 +61 (Australia)',
                                                '+64'  => '🇳🇿 +64 (Nueva Zelanda)',
                                                '+90'  => '🇹🇷 +90 (Turquía)',
                                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                                '+372' => '🇪🇪 +372 (Estonia)',
                                                '+371' => '🇱🇻 +371 (Letonia)',
                                                '+370' => '🇱🇹 +370 (Lituania)',
                                                '+48'  => '🇵🇱 +48 (Polonia)',
                                                '+40'  => '🇷🇴 +40 (Rumania)',
                                                '+46'  => '🇸🇪 +46 (Suecia)',
                                                '+47'  => '🇳🇴 +47 (Noruega)',
                                                '+45'  => '🇩🇰 +45 (Dinamarca)',
                                                '+41'  => '🇨🇭 +41 (Suiza)',
                                                '+43'  => '🇦🇹 +43 (Austria)',
                                                '+31'  => '🇳🇱 +31 (Países Bajos)',
                                                '+32'  => '🇧🇪 +32 (Bélgica)',
                                                '+353' => '🇮🇪 +353 (Irlanda)',
                                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                                '+380' => '🇺🇦 +380 (Ucrania)',
                                                '+994' => '🇦🇿 +994 (Azerbaiyán)',
                                                '+995' => '🇬🇪 +995 (Georgia)',
                                                '+976' => '🇲🇳 +976 (Mongolia)',
                                                '+998' => '🇺🇿 +998 (Uzbekistán)',
                                                '+84'  => '🇻🇳 +84 (Vietnam)',
                                                '+856' => '🇱🇦 +856 (Laos)',
                                                '+374' => '🇦🇲 +374 (Armenia)',
                                                '+965' => '🇰🇼 +965 (Kuwait)',
                                                '+966' => '🇸🇦 +966 (Arabia Saudita)',
                                                '+972' => '🇮🇱 +972 (Israel)',
                                                '+963' => '🇸🇾 +963 (Siria)',
                                                '+961' => '🇱🇧 +961 (Líbano)',
                                                '+960' => '🇲🇻 +960 (Maldivas)',
                                                '+992' => '🇹🇯 +992 (Tayikistán)',
                                            ])
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

                                if (isset($data['email'])) {
                                    $email = $data['email'];
                                }

                                if (isset($data['phone'])) {
                                    $phone = $data['phone'];
                                }

                                /**
                                 * JOB
                                 */
                                $job = ResendEmailPropuestaEconomica::dispatch($record, $email, $phone);

                                if ($job) {
                                    Notification::make()
                                        ->title('RE-ENVIADO EXITOSO')
                                        ->body('La informacion fue re-enviada exitosamente.')
                                        ->icon('heroicon-s-check-circle')
                                        ->iconColor('verde')
                                        ->success()
                                        ->send();
                                }
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

                                if(!file_exists(public_path('storage/individual-quotes/' . $record->code . '.pdf'))){
                                    
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
                                $path = public_path('storage/individual-quotes/' . $record->code . '.pdf');
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