<?php

namespace App\Filament\Resources\MassNotifications\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use App\Http\Controllers\NotificationController;

class MassNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagen')
                    ->imageSize(250),
                TextColumn::make('content')
                    ->label('Contenido(copy)')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->size(TextSize::Small)
                    ->wrap()
                    // ->limit(30, end: '...')
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        // Only render the tooltip if the column contents exceeds the length limit.
                        return $state;
                    }),
                    
                TextColumn::make('status')
                    ->badge()
                    ->color(function ($record) {
                        return $record->status == 'APROBADA' ? 'success' : 'danger';
                    })
                    ->icon(function ($record) {
                        return $record->status == 'APROBADA' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
                    })
                    ->searchable()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    
                    Action::make('change_status')
                        ->label('Aprobar Notificación')
                        ->icon('heroicon-o-check-circle')
                        ->color('verdeOpaco')
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
                    Action::make('send_notification_one_person')
                        ->label('Enviar Notificación')
                        ->icon('fluentui-attach-arrow-right-24')
                        ->color('verdeOpaco')
                        ->requiresConfirmation()
                        ->modalHeading('ENVIAR NOTIFICACIÓN')
                        ->hidden(function ($record) {
                            return $record->status == 'POR-APROBAR';
                        })
                        ->action(function ($record) {
                            try {
                                // dd($record);
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
                    
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}