<?php

namespace App\Filament\Agents\Resources\CorporateQuotes\Tables;

use Carbon\Carbon;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\CorporateQuote;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Actions\ImportAction;
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
use Illuminate\Validation\Rules\File;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Database\Eloquent\Builder;
use App\Jobs\ResendEmailPropuestaEconomica;
use Filament\Schemas\Components\Utilities\Get;
use App\Http\Controllers\NotificationController;
use App\Jobs\SendNotificacionUploadDataCorporate;
use App\Filament\Imports\AffiliateCorporateImporter;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Imports\CorporateQuoteRequestDataImporter;
use App\Filament\Agents\Resources\CorporateQuotes\CorporateQuoteResource;

class CorporateQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->query(CorporateQuote::query()->where('agent_id', Auth::user()->agent_id))
            ->heading('Lista de cotizaciones corporativas generadas por el agente')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('status_migration')
                    ->label('Tipo')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Solicitada por:')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Nro. de Teléfono')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable(),
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
                            'PRE-APROBADA' => 'warning',
                            'APROBADA' => 'success',
                            'APROBADA-DATA-ENVIADA' => 'success',
                            'ANULADA' => 'danger',
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

                Action::make('upload_data_dress_tailor')
                    ->label('Cargar Data')
                    ->icon('heroicon-m-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('DATA DRESS-TAYLOR')
                    ->modalDescription(
                        'Carga de data para la cotización corporativa de Dress Taylor'
                    )
                    ->modalIcon('heroicon-m-shield-check')
                    ->modalWidth(Width::ExtraLarge)
                    ->form([
                        Fieldset::make()
                            ->columnSpanFull()
                            ->schema([
                                FileUpload::make('data_doc')
                                    ->label('Población')
                                    ->required()
                                    ->visibility('public')
                                    ->helperText('La carga permite archivos .xlsx, .xls, .csv, .txt, .doc, .docx, .pdf, .jpg, .jpeg, .png')
                            ])->columns(1)
                    ])
                    ->action(function (array $data, $record): void {

                        $record->update([
                            'status' => 'APROBADA-DATA-ENVIADA',
                            'data_doc' => $data['data_doc'],
                        ]);

                        Notification::make()
                            ->title('lLa data fue cargada de forma exitosa.')
                            ->success()
                            ->send();

                        $recipient = User::where('is_admin', 1)->get();
                        foreach ($recipient as $user) {
                            $recipient_for_user = User::find($user->id);
                            Notification::make()
                                ->title('COTIZACION CORPORATIVA')
                                ->body('El agente ' . Auth::user()->name . ' cargo el modelo de data para la cotización Nro. ' . $record->code)
                                ->icon('heroicon-m-tag')
                                ->iconColor('success')
                                ->success()
                                ->actions([
                                    Action::make('view')
                                        ->label('Ver Cotización Corporativa')
                                        ->button()
                                        ->url(CorporateQuoteResource::getUrl('edit', ['record' => $record->id], panel: 'admin')),
                                ])
                                ->sendToDatabase($recipient_for_user);
                        }

                        //Notificacion por whatsapp
                        NotificationController::sendUploadDataCorporate(Auth::user()->name, $record->code);

                        /**
                         * Notificacion via email
                         * JOB
                         */
                        SendNotificacionUploadDataCorporate::dispatch($record->data_doc, Auth::user()->name, $record->code);
                    })
                    ->hidden(fn($record): bool => $record->status == 'APROBADA-DATA-ENVIADA' || $record->status == 'APROBADA' || $record->observation_dress_tailor == null),

                    Action::make('aproved')
                        ->label('Aprobar')
                        ->icon('heroicon-m-shield-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('APROBACIÓN DIRECTA PARA PRE-AFILIACIÓN')
                        ->modalDescription(
                            new HtmlString(
                                Blade::render(
                                    <<<BLADE
                                        <div class="fi-section-header-description mt-10">
                                            Por favor cargue la data de la población y a continuación haga click en Confirmar. 
                                            <br>
                                            <br>
                                            💡 Si desea agilizar la gestión puede descargar un archivo de ejemplo haciendo click en los
                                            <strong class="text-gray-900">tres puntos verticales (⋮) de Estatus</strong> 
                                            y seleccionando la opción <strong class="text-gray-900">Formato Data de Población.</strong>
                                            <br>
                                        </div>
                                    BLADE
                                ))
                        )
                        ->modalIcon('heroicon-m-shield-check')
                        ->modalWidth(Width::ExtraLarge)
                        ->form([
                            Fieldset::make()
                                ->columnSpanFull()
                                ->schema([
                                    FileUpload::make('data_doc')
                                        ->label('Población')
                                        ->required()
                                        ->visibility('public')
                                        ->helperText('La carga permite archivos .xlsx, .xls, .csv, .txt, .doc, .docx, .pdf, .jpg, .jpeg, .png')
                                ])->columns(1)
                            ])
                        ->action(function (array $data, $record): void {
                            
                            $record->update([
                                'status' => 'APROBADA-DATA-ENVIADA',
                                'data_doc' => $data['data_doc'],
                            ]);
                            
                            Notification::make()
                                ->title('lLa data fue cargada de forma exitosa.')
                                ->success()
                                ->send();

                            $recipient = User::where('is_admin', 1)->get();
                            foreach ($recipient as $user) {
                                $recipient_for_user = User::find($user->id);
                                Notification::make()
                                    ->title('COTIZACION CORPORATIVA')
                                    ->body('El agente '.Auth::user()->name.' cargo el modelo de data para la cotización Nro. ' . $record->code)
                                    ->icon('heroicon-m-tag')
                                    ->iconColor('success')
                                    ->success()
                                    ->actions([
                                        Action::make('view')
                                            ->label('Ver Cotización Corporativa')
                                            ->button()
                                            ->url(CorporateQuoteResource::getUrl('edit', ['record' => $record->id], panel: 'admin')),
                                    ])
                                    ->sendToDatabase($recipient_for_user);
                            }

                            //Notificacion por whatsapp
                            NotificationController::sendUploadDataCorporate(Auth::user()->name, $record->code);

                            /**
                             * Notificacion via email
                             * JOB
                             */
                            SendNotificacionUploadDataCorporate::dispatch($record->data_doc, Auth::user()->name, $record->code);
                            
                        })
                        ->hidden(fn ($record): bool => $record->status == 'APROBADA-DATA-ENVIADA' || $record->status == 'APROBADA' || $record->observation_dress_tailor != null),

                    /**REEN\VIO DE COTIZACION CORPORATIVA */
                    Action::make('forward')
                        ->label('Reenviar')
                        ->icon('fluentui-document-arrow-right-20')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalIcon('fluentui-document-arrow-right-20')
                        ->modalHeading('Reenvío de Cotización')
                        ->modalDescription('La propuesta será enviada por correo electrónico y/o teléfono!')
                        ->modalWidth(Width::ExtraLarge)
                        ->form([
                            Section::make()
                                // ->heading('Informacion')
                                // ->description('El link puede sera enviado por email y/o telefono!')
                                ->schema([
                                    TextInput::make('email')
                                        ->label('Correo electrónico')
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
                        })
                        ->hidden(fn($record): bool => $record->observation_dress_tailor != null),

                    /**DESCARGA DE COTIZACION */
                    Action::make('download')
                        ->label('Descargar Cotización')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->requiresConfirmation()
                        ->modalHeading('DESCARGAR COTIZACION')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-s-arrow-down-on-square-stack')
                        ->modalDescription('Descargará un archivo PDF al hacer clic en confirmar!.')
                        ->action(function (CorporateQuote $record, array $data) {

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
                        ->hidden(fn($record): bool => $record->observation_dress_tailor != null),

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
                    ->action(function (CorporateQuote $record, array $data) {

                        try {

                            $email = null;
                            $phone = null;
                            $link = env('APP_URL') . '/in/' . Crypt::encryptString($record->id) . '/w';

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
                    })
                    ->hidden(fn($record): bool => $record->observation_dress_tailor != null),

                /**OBSERVACIONES */
                Action::make('observations')
                        ->label('Agregar Observaciones')
                        ->icon('heroicon-s-hand-raised')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('OBSERVACIONES DEL AGENTE')
                        ->modalIcon('heroicon-s-hand-raised')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalDescription('Envíanos su inquietud o comentarios!')
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

                                $notoficationWp = NotificationController::saddObervationToCorporateQuote($record->code, Auth::user()->name, $data['description']);
                                
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

                    Action::make('download_file')
                        ->label('Formato Data de Población')
                        ->icon('fluentui-document-arrow-down-20')
                        ->color('info')
                        ->action(function (CorporateQuote $record, array $data) {
                            $path = public_path('storage/files/poblacion_ejemplo.xlsx');
                            return response()->download($path);
                        })
                        ->hidden(fn($record): bool => $record->observation_dress_tailor != null),
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
            ])->striped();
    }
}