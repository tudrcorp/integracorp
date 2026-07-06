<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Tables;

use App\Filament\Marketing\Resources\MassNotifications\Schemas\MassNotificationFolderForm;
use App\Http\Controllers\NotificationController;
use App\Models\DataNotification;
use App\Models\MassNotificationFolder;
use App\Support\MassNotificationDispatchService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MassNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                            ->color(fn ($record) => match ($record->status) {
                                'APROBADA' => 'success',
                                'POR-APROBAR' => 'warning',
                                default => 'danger',
                            })
                            ->weight(FontWeight::Bold),
                    ]),
                    Stack::make([
                        TextColumn::make('data')
                            ->badge()
                            ->color(fn ($record) => match ($record->status) {
                                'APROBADA' => 'success',
                                'POR-APROBAR' => 'warning',
                                default => 'danger',
                            })
                            ->default(fn ($record) => 'Total: '.self::getDataCount($record).' destinatarios')
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
                                ->helperText('Opcional! solo si es personalizada'),
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
                                ->helperText('Opcional! solo si es personalizada'),
                        ])
                        ->action(function ($record, $data) {
                            try {
                                $success = NotificationController::sendNotificationEmailSingle($record, $data);

                                Notification::make()
                                    ->body($success
                                        ? 'La notificación de prueba fue enviada exitosamente.'
                                        : 'No se pudo enviar la prueba. Revisa los logs del sistema.')
                                    ->{$success ? 'success' : 'danger'}()
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
                                $result = MassNotificationDispatchService::dispatch($record);

                                Notification::make()
                                    ->body($result->message)
                                    ->{$result->success ? 'success' : 'warning'}()
                                    ->send();
                            } catch (\Throwable $th) {
                                Log::error($th);

                                Notification::make()
                                    ->body('No se pudo encolar el envío masivo. Revisa los logs del sistema.')
                                    ->danger()
                                    ->send();
                            }
                        }),

                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->color('azulOscuro')
                    ->button(),
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

    public static function makeCreateFolderAction(): Action
    {
        return Action::make('create_mass_notification_folder')
            ->label('Crear carpeta')
            ->icon('heroicon-o-folder-plus')
            ->modalHeading('Crear carpeta')
            ->modalDescription('Organiza tus notificaciones masivas en carpetas. Podrás abrirlas desde el listado principal.')
            ->modalSubmitActionLabel('Guardar carpeta')
            ->modalWidth('3xl')
            ->form(MassNotificationFolderForm::createComponents())
            ->action(function (array $data): void {
                MassNotificationFolder::query()->create([
                    'name' => trim($data['name']),
                    'is_default' => false,
                ]);

                Notification::make()
                    ->title('Carpeta creada')
                    ->success()
                    ->send();
            });
    }
}
