<?php

namespace App\Filament\General\Resources\CorporateQuotes\Tables;

use App\Models\Agent;
use App\Models\Agency;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\CorporateQuote;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use App\Jobs\ResendEmailPropuestaEconomica;
use Filament\Schemas\Components\Utilities\Get;

class CorporateQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(CorporateQuote::query()->where('code_agency', Auth::user()->code_agency))
            ->defaultSort('created_at', 'desc')
            ->description('Lista de cotizaciones corporativas')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('registrated_by')
                    ->label('Registrado por:')
                    ->default(function ($record) {
                        if ($record->agent_id == null) {
                            return $record->code_agency;
                        }
                        if ($record->agent_id != null) {
                            if (Agent::where('id', $record->agent_id)->where('agent_type_id', 3)->exists()) {
                                return 'SUB-AGT-000' . $record->agent_id;
                            }
                            return 'AGT-000' . $record->agent_id;
                        }
                    })
                    ->badge()
                    ->icon(function ($record) {
                        $agency_type = Agency::select('agency_type_id')
                            ->where('code', $record->code_agency)
                            ->with('typeAgency')
                            ->first();
                        if (Agent::where('id', $record->agent_id)->where('agent_type_id', 3)->exists()) {
                            return 'heroicon-m-users';
                        } elseif (Agent::where('id', $record->agent_id)->where('agent_type_id', 2)->exists()) {
                            return 'heroicon-m-user';
                        } elseif ($agency_type->typeAgency->definition == 'MASTER') {
                            return 'heroicon-m-academic-cap';
                        } else {
                            return 'heroicon-s-building-library';
                        }
                    })
                    ->color('azul')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Solicitada por:')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif:')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Nro. de Teléfono')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Región')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Generada el:')
                    ->dateTime(),
                TextColumn::make('count_days')
                    ->label('Transcurrido')
                    ->alignCenter()
                    ->badge()
                    ->suffix(' dias')
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'verdeOpaco',
                            'APROBADA' => 'success',
                            'ANULADA' => 'warning',
                            'DECLINADA' => 'danger',
                            default => 'azul',
                        };
                    })
                    ->searchable(),
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

                    /**EMITIR */
                    Action::make('emit')
                        ->label('Emitir')
                        ->icon('heroicon-m-power')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('APROBACIÓN DIRECTA PARA PR-AFILIACIÓN CORPORATIVA')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-m-power')
                        ->action(function (CorporateQuote $record) {

                            try {

                                /**
                                 * Actualizo el status a APROBADA
                                 */
                                $record->status = 'APROBADA';
                                $record->save();

                                Notification::make()
                                    ->title('COTIZACION INDIVIDUAL APROBADA')
                                    ->body('Se realizo la aprobacion directa de la cotizacion corporativa Nro.' . $record->code . ' para realizar la pre-afiliacion')
                                    ->icon('heroicon-s-user-group')
                                    ->iconColor('success')
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
                                LogController::log(Auth::user()->id, 'Aprobacion directa de la cotizacion corporativa Nro.' . $record->code, 'Modulo Cotizacion Individual', 'APROBADA');

                                /**
                                 * Redirecciono a la pagina para crear la afiliacion
                                 */
                                $count_plans = $record->detailCoporateQuotes()->distinct()->pluck('plan_id');

                                if ($count_plans->count() == 1) {
                                    return redirect()->route('filament.general.resources.affiliation-corporates.create', ['corporate_quote_id' => $record->id, 'plan_id' => $count_plans[0]]);
                                }
                                return redirect()->route('filament.general.resources.affiliation-corporates.create', ['corporate_quote_id' => $record->id, 'plan_id' => null]);
                            } catch (\Throwable $th) {
                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.CorporateQuoteResource.action.emit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /**REEN\VIO DE COTIZACION CORPORATIVA */
                    Action::make('forward')
                        ->label('Reenviar Cotizacion')
                        ->icon('heroicon-o-arrow-uturn-right')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('REENVIÓ DE COTIZACIÓN CORPORATIVA')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-o-arrow-uturn-right')
                        ->form([
                            Section::make()
                                ->heading('Informacion')
                                ->description('El link puede sera enviado por email y/o telefono!')
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
                        ->action(function (CorporateQuote $record, array $data) {

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

                    /**DESCARGA DE COTIZACION */
                    Action::make('download')
                        ->label('Descargar cotización')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->requiresConfirmation()
                        ->modalHeading('DESCARGAR COTIZACIÓN CORPORATIVA')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-s-arrow-down-on-square-stack')
                        ->action(function (CorporateQuote $record, array $data) {

                            try {

                                /**
                                 * Descargar el documento asociado a la cotizacion
                                 * ruta: storage/
                                 */
                                $path = public_path('storage/' . $record->code . '.pdf');
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

                    /**OBSERVACIONES */
                    Action::make('observations')
                        ->label('Registrar observaciones')
                        ->icon('heroicon-s-hand-raised')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('OBSERVACIONES DEL AGENTE')
                        ->modalIcon('heroicon-s-hand-raised')
                        ->form([
                            Textarea::make('description')
                                ->label('Observaciones')
                                ->rows(5)
                        ])
                        ->action(function (CorporateQuote $record, array $data) {

                            try {

                                $record->observations = $data['description'];
                                $record->save();

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
                ->hidden(function (CorporateQuote $record) {
                    return $record->status == 'ANULADA' || $record->status == 'DECLINADA';
                })
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}