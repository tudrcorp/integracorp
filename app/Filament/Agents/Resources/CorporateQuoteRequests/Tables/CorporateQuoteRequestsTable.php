<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
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
use App\Http\Controllers\UtilsController;
use App\Jobs\ResendEmailPropuestaEconomica;
use Filament\Schemas\Components\Utilities\Get;

class CorporateQuoteRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(CorporateQuoteRequest::query()->where('agent_id', Auth::user()->agent_id))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Codigo')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Solicitante')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Número de teléfono')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Región')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([

                    Action::make('view')
                        ->label('Ver Detalles')
                        ->color('success')
                        ->icon('fontisto-info')
                        ->modalHeading('Detalles de la Cotización')
                        ->modalIcon('fontisto-info')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalSubmitAction(false)
                        ->form([
                            Textarea::make('observations')
                                ->label('Descripción:')
                                ->disabled()
                                ->autoSize()
                                ->default(fn (CorporateQuoteRequest $record) => $record->observations)
                                ->required(),
                        ]),

                    /**FORWARD */
                    Action::make('forward')
                        ->hidden(fn($record) => $record->document_file == null)
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
                        ->action(function (CorporateQuoteRequest $record, array $data) {

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
                        ->hidden(fn($record) => $record->document_file == null)
                        ->label('Descargar Cotización')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->requiresConfirmation()
                        ->modalHeading('DESCARGAR COTIZACION')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-s-arrow-down-on-square-stack')
                        ->modalDescription('Descargará un archivo PDF al hacer clic en confirmar!.')
                        ->action(function (CorporateQuoteRequest $record, array $data) {

                            try {

                                if (!file_exists(public_path('storage/' . $record->document_file))) {

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
                                $path = public_path('storage/' . $record->document_file);
                                return response()->download($path);

                            } catch (\Throwable $th) {
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
                        ->action(function (CorporateQuoteRequest $record, array $data) {

                            // try {

                            //     $bitacora = new Bitacora();
                            //     $bitacora->individual_quote()->associate($record);
                            //     $bitacora->user()->associate(Auth::user());
                            //     $bitacora->details = $data['description'];
                            //     $bitacora->save();

                            //     Notification::make()
                            //         ->body('Las observaciones fueron registradas exitosamente.')
                            //         ->success()
                            //         ->send();

                            //     $notoficationWp = NotificationController::saddObervationToIndividualQuote($record->code, Auth::user()->name, $data['description']);
                            // } catch (\Throwable $th) {
                            //     LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                            //     Notification::make()
                            //         ->title('ERROR')
                            //         ->body($th->getMessage())
                            //         ->icon('heroicon-s-x-circle')
                            //         ->iconColor('danger')
                            //         ->danger()
                            //         ->send();
                            // }
                        }),
                ])
                ->icon('heroicon-c-ellipsis-vertical')
                ->color('azulOscuro')
                ->hidden(function (CorporateQuoteRequest $record) {
                    return $record->status == 'ANULADA' || $record->status == 'DECLINADA';
                })
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }
}