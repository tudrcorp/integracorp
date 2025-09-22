<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use App\Http\Controllers\NotificationController;

class BirthdayNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Listado de Notificaciones de Cumpleaños Grupales(Masivas)')
            ->description('Aquí puedes ver y gestionar las notificaciones que se han enviado por cumpleaños grupales.')
            ->columns([
                Stack::make([
                    ImageColumn::make('file')
                        ->imageHeight('auto')
                        ->imageWidth('70%')
                        ->visibility('public'),
                    
                    Stack::make([
                        TextColumn::make('data_type')
                            ->suffix (function ($record) {
                                if($record->data_type == 'users'){
                                    Log::info($record->data_type);
                                    return ' - Colaboradores/Empleados';
                                }
                                if($record->data_type == 'suppliers'){
                                    return ' - Proveedores';
                                }
                                if($record->data_type == 'affiliations'){
                                    return ' - Afiliados/Clientes';
                                }
                                if($record->data_type == 'capemiacs'){
                                    return ' - CAPEMIAC';
                                }
                                if($record->data_type == 'agents'){
                                    return ' - Agentes';
                                }
                            })
                            ->badge()
                        ->color('primary')->weight(FontWeight::Bold),
                    ]),
                    Stack::make([
                        TextColumn::make('status')
                            ->badge()
                            ->color(fn($record) => match ($record->status) {
                                'APROBADA' => 'success',
                                'POR-APROBAR' => 'warning',
                                default => 'danger',
                            })->weight(FontWeight::Bold),
                    ]),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->recordActions([
                ActionGroup::make([

                    Action::make('change_status')
                        ->label('Aprobar Notificación')
                        ->icon('heroicon-o-power')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('APROBAR NOTIFICACIÓN')
                        ->hidden(function ($record) {
                            return $record->status == 'APROBADA';
                        })
                        ->action(function ($record) {
                            $record->status = 'APROBADA';
                            $record->approved_by = Auth::user()->id;
                            $record->save();
                            Notification::make()
                                ->body('El estado de la notificación fue cambiado exitosamente.')
                                ->success()
                                ->send();
                        }),

                    Action::make('test_notification_wp')
                        ->label('Test(Via WhatsApp)')
                        ->icon('heroicon-c-device-phone-mobile')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('ENVIAR NOTIFICACIÓN')
                        ->hidden(function ($record) {
                            return $record->status == 'POR-APROBAR';
                        })
                        ->form([
                            TextInput::make('phone')
                                ->label('Número de Teléfono')
                                ->helperText('Debe agregar los códigos de area. Ejemplo: +56, +57, +58, +1, etc. Si es un numero local: +58412, +58414, +58424, +58426, +58212, etc.')
                                ->tel()
                                ->required(),
                            TextInput::make('name')
                                ->label('Nombre y Apellido')
                                ->helperText('Opcional! solo si es personalizada')
                        ])
                        ->action(function ($record, $data) {
                            try {

                                NotificationController::sendNotificationWpSingle($record, $data);

                                Notification::make()
                                    ->body('La notificación fue enviada exitosamente.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $th) {
                                Log::error($th);
                            }
                        }),

                    Action::make('test_notification_email')
                        ->label('Test(Via Email)')
                        ->icon('heroicon-c-at-symbol')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('ENVIAR NOTIFICACIÓN')
                        ->hidden(function ($record) {
                            return $record->status == 'POR-APROBAR';
                        })
                        ->form([
                            TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->required(),
                            TextInput::make('name')
                                ->label('Nombre y Apellido')
                                ->helperText('Opcional! solo si es personalizada')
                        ])
                        ->action(function ($record, $data) {

                            try {

                                NotificationController::sendNotificationEmailSingle($record, $data);

                                Notification::make()
                                    ->body('La notificación fue enviada exitosamente.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $th) {
                                Log::error($th);
                            }
                        }),

                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->color('azulOscuro')
                    ->button()
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}