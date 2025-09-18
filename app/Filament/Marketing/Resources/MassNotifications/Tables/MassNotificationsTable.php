<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Tables;

use App\Models\User;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\DataNotification;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendNotificationMasive;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use App\Http\Controllers\UtilsController;
use App\Jobs\SendNotificationMasiveEmail;
use Filament\Tables\Columns\Layout\Stack;
use App\Http\Controllers\NotificationController;

class MassNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->heading('Listado de Notificaciones Individuales y Grupales(Masivas)')
        ->description('Aquí puedes ver y gestionar las notificaciones que se han enviado a los agentes y clientes')
        ->columns([
                Stack::make([
                    ImageColumn::make('file')
                        ->imageHeight('auto')
                        ->imageWidth('70%')
                        ->visibility('public'),
                    Stack::make([
                        TextColumn::make('status')
                            ->badge()
                            ->color(fn($record) => match ($record->status) {
                                'APROBADA' => 'success',
                                'POR-APROBAR' => 'warning',
                                default => 'danger',
                            })
                            ->weight(FontWeight::Bold),
                    ]),
                    Stack::make([
                        TextColumn::make('data')
                            ->badge()
                            ->color(fn($record) => match ($record->status) {
                                'APROBADA' => 'success',
                                'POR-APROBAR' => 'warning',
                                default => 'danger',
                            })
                            ->default(fn($record) => 'Total: ' . self::getDataCount($record) . ' destinatarios')
                            ->weight(FontWeight::Bold),
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

                    
                Action::make('send_notification')
                    ->label('Envío Masivo')
                    ->icon('heroicon-s-megaphone')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('ENVIAR NOTIFICACIÓN')
                    ->hidden(function ($record) {
                        return $record->status == 'POR-APROBAR';
                    })
                    ->action(function ($record) {
                        
                        try {

                            $users = User::where('is_designer', 1)->where('departament', 'MARKETING')->get();

                            $array_channels = $record->channels;

                            for ($i = 0; $i < count($array_channels); $i++) {
                                if($array_channels[$i] == 'whatsapp') {
                                    SendNotificationMasive::dispatch($record, $users)->onQueue('system');
                                }
                                if ($array_channels[$i] == 'email') {
                                    SendNotificationMasiveEmail::dispatch($record, $users)->onQueue('system');
                                }
                            }

                            Notification::make()
                                ->body('La notificación fue enviada exitosamente. Integracorp le notificara cuando el proceso finalice.')
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

    public static function getDataCount($record)
    {
        $count = DataNotification::where('mass_notification_id', $record->id)->count();
        return $count;
    }
}