<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Tables;

use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Http\Controllers\CorporateQuoteExportCsvController;
use App\Http\Controllers\CorporateQuotePopulationExportCsvController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Jobs\ResendEmailPropuestaEconomica;
use App\Jobs\SendNotificacionUploadDataCorporate;
use App\Mail\MailLinkIndividualQuote;
use App\Models\CorporateQuote;
use App\Models\User;
use App\Support\CorporateQuotePdfGenerator;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class CorporateQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    return CorporateQuote::query()->where('ownerAccountManagers', Auth::user()->id);
                }

                return CorporateQuote::query();
            })
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'accountManager',
                'agent',
            ]))
            ->defaultSort('created_at', 'desc')
            ->deferFilters(false)
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Cotizaciones corporativas')
            ->description('Filtre por fecha, estatus o plan; use copiar en correo y teléfono. Las acciones de carga, reenvío y enlaces están en el menú «Más acciones» (oculto si la cotización está anulada o declinada).')
            ->emptyStateHeading('Sin cotizaciones corporativas')
            ->emptyStateDescription('Las propuestas corporativas generadas por los agentes aparecerán aquí. Puede crear una nueva desde el recurso correspondiente.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->weight('font-semibold'),
                TextColumn::make('accountManager.name')
                    ->label('Account Manager')
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->placeholder('—')
                    ->default(fn (CorporateQuote $record): string => $record->accountManager?->name ?? '—')
                    ->color(function (string $state): string {
                        return match ($state) {
                            '—' => 'gray',
                            default => 'success',
                        };
                    })
                    ->hidden(fn (): bool => ! Auth::user()->is_business_admin)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agent.name')
                    ->label('Agente')
                    ->badge()
                    ->placeholder('—')
                    ->default(fn (CorporateQuote $record): string => $record->agent?->name ?? '—')
                    ->color(function (string $state): string {
                        return match ($state) {
                            '—' => 'gray',
                            default => 'success',
                        };
                    })
                    ->icon('heroicon-m-user')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('full_name')
                    ->label('Solicitante')
                    ->icon(Heroicon::OutlinedUser)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->grow(),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->badge()
                    ->color('gray')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('plan')
                    ->label('Tipo de plan')
                    ->formatStateUsing(fn (mixed $state): string => self::planLabel($state))
                    ->badge()
                    ->color(fn (mixed $state): string => self::planColor(self::planLabel($state)))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(28)
                    ->tooltip(fn (CorporateQuote $record): ?string => $record->email),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Generada el')
                    ->description(fn (CorporateQuote $record): string => Carbon::parse($record->created_at)->diffForHumans())
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'verdeOpaco',
                            'APROBADA' => 'success',
                            'APROBADA-DATA-ENVIADA' => 'info',
                            'ANULADA' => 'warning',
                            'DECLINADA' => 'danger',
                            'EJECUTADA' => 'azul',
                            default => 'azulOscuro',
                        };
                    })
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA' => 'heroicon-c-information-circle',
                            'APROBADA' => 'heroicon-s-check-circle',
                            'APROBADA-DATA-ENVIADA' => 'heroicon-s-arrow-up-tray',
                            'ANULADA' => 'heroicon-s-exclamation-circle',
                            'DECLINADA' => 'heroicon-c-x-circle',
                            'EJECUTADA' => 'heroicon-s-check-circle',
                            default => 'heroicon-c-information-circle',
                        };
                    })
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Fecha de cotización')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Cotización desde '.Carbon::parse($data['desde'])->translatedFormat('d M Y');
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Cotización hasta '.Carbon::parse($data['hasta'])->translatedFormat('d M Y');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'PRE-APROBADA' => 'PRE-APROBADA',
                        'APROBADA' => 'APROBADA',
                        'APROBADA-DATA-ENVIADA' => 'APROBADA-DATA-ENVIADA',
                        'ANULADA' => 'ANULADA',
                        'DECLINADA' => 'DECLINADA',
                        'EJECUTADA' => 'EJECUTADA',
                    ])
                    ->searchable()
                    ->preload()
                    ->indicator('Estatus'),
                SelectFilter::make('plan')
                    ->label('Tipo de plan')
                    ->options([
                        1 => 'Plan Inicial',
                        2 => 'Plan Ideal',
                        3 => 'Plan Especial',
                        'CM' => 'MultiPlan',
                    ])
                    ->searchable()
                    ->preload()
                    ->indicator('Plan'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),
                EditAction::make()
                    ->label('Editar'),
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
                                        ->helperText('La carga permite archivos .xlsx, .xls, .csv, .txt, .doc, .docx, .pdf, .jpg, .jpeg, .png'),
                                ])->columns(1),
                        ])
                        ->action(function (array $data, $record): void {
                            try {
                                $record->update([
                                    'status' => 'APROBADA-DATA-ENVIADA',
                                    'data_doc' => $data['data_doc'],
                                ]);

                                Notification::make()
                                    ->title('Data cargada')
                                    ->body('La data se registró correctamente.')
                                    ->success()
                                    ->send();

                                $recipient = User::where('is_admin', 1)->get();
                                foreach ($recipient as $user) {
                                    Notification::make()
                                        ->title('COTIZACION CORPORATIVA')
                                        ->body('El agente '.Auth::user()->name.' cargo el modelo de data para la cotización Nro. '.$record->code)
                                        ->icon('heroicon-m-tag')
                                        ->iconColor('success')
                                        ->success()
                                        ->actions([
                                            Action::make('view')
                                                ->label('Ver Cotización Corporativa')
                                                ->button()
                                                ->url(CorporateQuoteResource::getUrl('edit', ['record' => $record->id], panel: 'business')),
                                        ])
                                        ->sendToDatabase($user);
                                }

                                NotificationController::sendUploadDataCorporate(Auth::user()->name, $record->code);
                                SendNotificacionUploadDataCorporate::dispatch($record->data_doc, Auth::user()->name, $record->code);

                                SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_DATA_UPLOADED', 'business.corporate-quotes.upload-data', [
                                    'panel' => 'business',
                                    'corporate_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'status' => $record->status,
                                    'data_doc' => $record->data_doc,
                                ]);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_DATA_UPLOAD_FAILED', 'business.corporate-quotes.upload-data', [
                                    'panel' => 'business',
                                    'corporate_quote_id' => $record->id ?? null,
                                    'code' => $record->code ?? null,
                                    'reason' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(fn ($record): bool => $record->status == 'APROBADA-DATA-ENVIADA' || $record->status == 'APROBADA' || $record->observation_dress_tailor == null),

                    // Action::make('aproved')
                    //     ->label('Aprobar')
                    //     ->icon('heroicon-m-shield-check')
                    //     ->color('success')
                    //     ->requiresConfirmation()
                    //     ->modalHeading('APROBACIÓN DIRECTA PARA PRE-AFILIACIÓN')
                    //     ->modalDescription(
                    //         new HtmlString(
                    //             Blade::render(
                    //                 <<<BLADE
                    //                     <div class="fi-section-header-description mt-10">
                    //                         Por favor cargue la data de la población y a continuación haga click en Confirmar.
                    //                         <br>
                    //                         <br>
                    //                         💡 Si desea agilizar la gestión puede descargar un archivo de ejemplo haciendo click en los
                    //                         <strong class="text-gray-900">tres puntos verticales (⋮) de Estatus</strong>
                    //                         y seleccionando la opción <strong class="text-gray-900">Formato Data de Población.</strong>
                    //                         <br>
                    //                     </div>
                    //                 BLADE
                    //             )
                    //         )
                    //     )
                    //     ->modalIcon('heroicon-m-shield-check')
                    //     ->modalWidth(Width::ExtraLarge)
                    //     ->form([
                    //         Fieldset::make()
                    //             ->columnSpanFull()
                    //             ->schema([
                    //                 FileUpload::make('data_doc')
                    //                     ->label('Población')
                    //                     ->required()
                    //                     ->visibility('public')
                    //                     ->helperText('La carga permite archivos .xlsx, .xls, .csv, .txt, .doc, .docx, .pdf, .jpg, .jpeg, .png')
                    //             ])->columns(1)
                    //     ])
                    //     ->action(function (array $data, $record): void {

                    //         $record->update([
                    //             'status' => 'APROBADA-DATA-ENVIADA',
                    //             'data_doc' => $data['data_doc'],
                    //         ]);

                    //         Notification::make()
                    //             ->title('lLa data fue cargada de forma exitosa.')
                    //             ->success()
                    //             ->send();

                    //         $recipient = User::where('is_admin', 1)->get();
                    //         foreach ($recipient as $user) {
                    //             $recipient_for_user = User::find($user->id);
                    //             Notification::make()
                    //                 ->title('COTIZACION CORPORATIVA')
                    //                 ->body('El agente ' . Auth::user()->name . ' cargo el modelo de data para la cotización Nro. ' . $record->code)
                    //                 ->icon('heroicon-m-tag')
                    //                 ->iconColor('success')
                    //                 ->success()
                    //                 ->actions([
                    //                     Action::make('view')
                    //                         ->label('Ver Cotización Corporativa')
                    //                         ->button()
                    //                         ->url(CorporateQuoteResource::getUrl('edit', ['record' => $record->id], panel: 'admin')),
                    //                 ])
                    //                 ->sendToDatabase($recipient_for_user);
                    //         }

                    //         //Notificacion por whatsapp
                    //         NotificationController::sendUploadDataCorporate(Auth::user()->name, $record->code);

                    //         /**
                    //          * Notificacion via email
                    //          * JOB
                    //          */
                    //         SendNotificacionUploadDataCorporate::dispatch($record->data_doc, Auth::user()->name, $record->code);
                    //     })
                    //     ->hidden(fn($record): bool => $record->status == 'APROBADA-DATA-ENVIADA' || $record->status == 'APROBADA' || $record->observation_dress_tailor != null),

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

                                $path = public_path('storage/quotes/'.$record->code.'.pdf');

                                if (! file_exists($path) && ! CorporateQuotePdfGenerator::regenerateIfMissing($record)) {
                                    SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_PDF_DOWNLOAD_FAILED', 'business.corporate-quotes.download', [
                                        'panel' => 'business',
                                        'corporate_quote_id' => $record->id,
                                        'code' => $record->code,
                                        'reason' => 'file_not_found',
                                    ]);

                                    Notification::make()
                                        ->title('NOTIFICACIÓN')
                                        ->body('El documento asociado a la cotización no se encuentra disponible. Verifique que la cotización tenga detalles y tarifas configuradas.')
                                        ->icon('heroicon-s-x-circle')
                                        ->iconColor('warning')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                return redirect()->route('business.corporate-quotes.pdf.download', [
                                    'corporateQuote' => $record->id,
                                ]);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_PDF_DOWNLOAD_FAILED', 'business.corporate-quotes.download', [
                                    'panel' => 'business',
                                    'corporate_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'reason' => $th->getMessage(),
                                ]);

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
                        ->hidden(fn ($record): bool => $record->observation_dress_tailor != null),

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
                                                    $set('phone', $countryCode.$cleanNumber);
                                                }
                                            }),
                                    ]),
                                ]),
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
                                    SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_FORWARD_SENT', 'business.corporate-quotes.forward', [
                                        'panel' => 'business',
                                        'corporate_quote_id' => $record->id,
                                        'code' => $record->code,
                                        'email' => $email,
                                        'phone' => $phone,
                                    ]);

                                    Notification::make()
                                        ->title('RE-ENVIADO EXITOSO')
                                        ->body('La información fue reenviada correctamente.')
                                        ->icon('heroicon-s-check-circle')
                                        ->iconColor('verde')
                                        ->success()
                                        ->send();
                                }
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_FORWARD_FAILED', 'business.corporate-quotes.forward', [
                                    'panel' => 'business',
                                    'corporate_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'email' => $data['email'] ?? null,
                                    'phone' => $data['phone'] ?? null,
                                    'reason' => $th->getMessage(),
                                ]);

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
                        ->hidden(fn ($record): bool => $record->observation_dress_tailor != null),

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
                                            ->options(fn () => UtilsController::getCountries())
                                            ->searchable()
                                            ->default('+58')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ]),
                                        TextInput::make('phone')
                                            ->prefixIcon('heroicon-s-phone')
                                            ->tel()
                                            ->label('Número de teléfono')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $countryCode = $get('country_code');
                                                if ($countryCode) {
                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                    $set('phone', $countryCode.$cleanNumber);
                                                }
                                            }),
                                    ]),
                                ]),
                        ])
                        ->action(function (CorporateQuote $record, array $data) {

                            try {

                                $email = null;
                                $phone = null;
                                $link = config('parameters.INTEGRACORP_URL').'/in/'.Crypt::encryptString($record->id).'/w';

                                if (isset($data['email'])) {
                                    $email = $data['email'];
                                    Mail::to($email)->send(new MailLinkIndividualQuote($link));

                                    SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_INTERACTIVE_LINK_EMAIL_SENT', 'business.corporate-quotes.interactive-link', [
                                        'panel' => 'business',
                                        'corporate_quote_id' => $record->id,
                                        'code' => $record->code,
                                        'email' => $email,
                                        'link' => $link,
                                    ]);

                                    Notification::make()
                                        ->title('ENVIADO EXITOSO')
                                        ->body('El link fue enviado por email exitosamente.')
                                        ->icon('heroicon-s-check-circle')
                                        ->iconColor('verde')
                                        ->success()
                                        ->send();
                                }

                                if (isset($data['phone'])) {
                                    $phone = $data['phone'];
                                    $wp = NotificationController::sendLinkIndividualQuote($phone, $link);
                                    if ($wp) {
                                        SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_INTERACTIVE_LINK_WHATSAPP_SENT', 'business.corporate-quotes.interactive-link', [
                                            'panel' => 'business',
                                            'corporate_quote_id' => $record->id,
                                            'code' => $record->code,
                                            'phone' => $phone,
                                            'link' => $link,
                                        ]);

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

                                        SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_INTERACTIVE_LINK_WHATSAPP_FAILED', 'business.corporate-quotes.interactive-link', [
                                            'panel' => 'business',
                                            'corporate_quote_id' => $record->id,
                                            'code' => $record->code,
                                            'phone' => $phone,
                                            'link' => $link,
                                            'reason' => 'whatsapp_delivery_failed',
                                        ]);
                                    }
                                }
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_INTERACTIVE_LINK_FAILED', 'business.corporate-quotes.interactive-link', [
                                    'panel' => 'business',
                                    'corporate_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'email' => $data['email'] ?? null,
                                    'phone' => $data['phone'] ?? null,
                                    'reason' => $th->getMessage(),
                                ]);

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
                        ->hidden(fn ($record): bool => $record->observation_dress_tailor != null),

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
                                ->rows(5),
                        ])
                        ->action(function (CorporateQuote $record, array $data) {

                            try {

                                $record->observations = $data['description'];
                                $record->save();

                                SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_OBSERVATION_ADDED', 'business.corporate-quotes.observations', [
                                    'panel' => 'business',
                                    'corporate_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'description' => $data['description'] ?? null,
                                ]);

                                Notification::make()
                                    ->body('Las observaciones fueron registradas exitosamente.')
                                    ->success()
                                    ->send();

                                $notoficationWp = NotificationController::saddObervationToCorporateQuote($record->code, Auth::user()->name, $data['description']);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_OBSERVATION_ADD_FAILED', 'business.corporate-quotes.observations', [
                                    'panel' => 'business',
                                    'corporate_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'reason' => $th->getMessage(),
                                ]);

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
                            SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_POPULATION_FORMAT_DOWNLOADED', 'business.corporate-quotes.download-population-format', [
                                'panel' => 'business',
                                'corporate_quote_id' => $record->id,
                                'code' => $record->code,
                                'path' => $path,
                            ]);

                            return response()->download($path);
                        })
                        ->hidden(fn ($record): bool => $record->observation_dress_tailor != null),
                ])
                    ->label('Más acciones')
                    ->tooltip('Cargar data, reenviar, descargar PDF, link interactivo y más')
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->iconButton()
                    ->color('azulOscuro')
                    ->hidden(fn (CorporateQuote $record): bool => in_array($record->status, ['ANULADA', 'DECLINADA'], true)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportCsvController')
                        ->label('Exportar CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos una cotización')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $ids = $records->pluck('id')->all();
                            $token = CorporateQuoteExportCsvController::storeIdsAndGetToken($ids);

                            return redirect()->route('business.corporate-quotes.export-csv', ['token' => $token]);
                        }),
                    BulkAction::make('exportPopulationCsv')
                        ->label('Exportar CSV con población')
                        ->icon('heroicon-o-users')
                        ->color('info')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos una cotización')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $ids = $records->pluck('id')->all();
                            $token = CorporateQuotePopulationExportCsvController::storeIdsAndGetToken($ids);

                            return redirect()->route('business.corporate-quotes.export-population-csv', ['token' => $token]);
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }

    private static function planLabel(mixed $plan): string
    {
        return match (true) {
            $plan === '1' || $plan === 1 => 'Plan Inicial',
            $plan === '2' || $plan === 2 => 'Plan Ideal',
            $plan === '3' || $plan === 3 => 'Plan Especial',
            $plan === 'CM' => 'MultiPlan',
            $plan === null, $plan === '' => '—',
            default => (string) $plan,
        };
    }

    private static function planColor(string $label): string
    {
        return match ($label) {
            'Plan Inicial' => 'azulClaro',
            'Plan Ideal' => 'azulOscuro',
            'Plan Especial' => 'verde',
            'MultiPlan' => 'warning',
            '—' => 'gray',
            default => 'info',
        };
    }
}
