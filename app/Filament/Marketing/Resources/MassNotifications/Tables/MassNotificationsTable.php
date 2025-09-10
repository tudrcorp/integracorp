<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
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
                    ImageColumn::make('image')
                        ->imageWidth(250)
                        ->imageHeight(250)
                        ->visibility('public'),
                    Stack::make([
                        TextColumn::make('status')
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
                                $send = NotificationController::massNotificacionSend($record);
                                if ($send) {
                                    Notification::make()
                                        ->body('Las notificaciones fueron enviadas exitosamente.')
                                        ->success()
                                        ->send();
                                }
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
}