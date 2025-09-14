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
use App\Jobs\SendNotificationMasive;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use App\Http\Controllers\UtilsController;
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
                        ->imageWidth(250)
                        ->imageHeight(250)
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

                Action::make('details')
                    ->label('Ver Copy')
                    ->icon('fontisto-info')
                    ->color('primary')
                    ->modalHeading('Copy de la notificación')
                    ->modalIcon('fontisto-info')
                    ->modalWidth(Width::ExtraLarge)
                    ->modalSubmitAction(false)
                    ->form([
                        Textarea::make('content')
                            ->label('Copy')
                            ->disabled()
                            ->autoSize()
                            ->default(fn($record) => $record->content)
                            ->required(),
                    ]),

                Action::make('change_status')
                    ->label('Aprobar Notificación')
                    ->icon('fontisto-question')
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
                    
                Action::make('send_notification')
                    ->label('Enviar Notificación')
                    ->icon('fontisto-rss')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('ENVIAR NOTIFICACIÓN')
                    ->hidden(function ($record) {
                        return $record->status == 'POR-APROBAR';
                    })
                    ->action(function ($record) {
                        try {
                            
                            $users = User::where('is_designer', 1)->where('departament', 'MARKETING')->get();
                            // SendNotificationMasive::dispatch($record, $users)->onQueue('system');
                            UtilsController::send($record);
                            
                        } catch (\Throwable $th) {
                            dd($th);
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