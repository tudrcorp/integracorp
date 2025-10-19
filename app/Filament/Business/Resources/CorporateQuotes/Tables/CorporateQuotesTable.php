<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Tables;

use Carbon\Carbon;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\CorporateQuote;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Radio;
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
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Jobs\ResendEmailPropuestaEconomica;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Http\Controllers\NotificationController;
use App\Jobs\SendNotificacionUploadDataCorporate;
use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;

class CorporateQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->query(CorporateQuote::query()->where('ownerAccountManagers', Auth::user()->id))
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    return CorporateQuote::query()->where('ownerAccountManagers', Auth::user()->id);
                }
                return CorporateQuote::query();
            })
            ->defaultSort('created_at', 'desc')
            ->heading('COTIZACIONES CORPORATIVAS')
            ->description('Lista de cotizaciones corporativas generadas por el agente')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('accountManager.name')
                    ->label('Account Manager')
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->default(fn($record): string => $record->accountManager ? $record->accountManager : '-----')
                    ->color(function (string $state): string {
                        return match ($state) {
                            '-----' => 'info',
                            default => 'success',
                        };
                    }),
                TextColumn::make('agent.name')
                    ->label('Agente')
                    ->badge()
                    ->default(fn($record): string => $record->agent_id ? $record->agent_id : '-----')
                    ->color(function (string $state): string {
                        return match ($state) {
                            '-----' => 'info',
                            default => 'success',
                        };
                    })
                    ->icon('heroicon-m-user')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Solicitada por:')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif:')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->badge()
                    ->default(fn($record): string => $record->email ? $record->email : '-----')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Nro. de Teléfono')
                    ->badge()
                    ->default(fn($record): string => $record->phone ? $record->phone : '-----')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Generada el:')
                    ->description(fn($record): string => Carbon::parse($record->created_at)->diffForHumans())
                    ->dateTime()
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
                            default => 'azul',
                        };
                    })
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA'  => 'heroicon-c-information-circle',
                            'APROBADA'      => 'heroicon-s-check-circle',
                            'ANULADA'       => 'heroicon-s-exclamation-circle',
                            'DECLINADA'     => 'heroicon-c-x-circle',
                            default     => 'heroicon-c-information-circle',
                        };
                    })
                    ->searchable(),
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
                SelectFilter::make('status')
                    ->options([
                        'PRE-APROBADA'  => 'PRE-APROBADA',
                        'APROBADA'      => 'APROBADA',
                        'EJECUTADA'       => 'EJECUTADA',
                    ]),
                SelectFilter::make('plan')
                    ->options([
                        1       => 'Plan Inicial',
                        2       => 'Plan Ideal',
                        3       => 'Plan Especial',
                        'CM'    => 'MultiPlan',
                    ])
                    ->label('Tipo de Plan')
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
                                            ->url(CorporateQuoteResource::getUrl('edit', ['record' => $record->id], panel: 'business')),
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
                                )
                            )
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
                        ->hidden(fn($record): bool => $record->status == 'APROBADA-DATA-ENVIADA' || $record->status == 'APROBADA' || $record->observation_dress_tailor != null),

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
                                            ->options(fn() => UtilsController::getCountries())
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
            ]);
    }
}