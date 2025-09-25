<?php

namespace App\Filament\Marketing\Resources\Capemiacs\Tables;

use Filament\Tables\Table;
use App\Models\DataNotification;
use App\Models\MassNotification;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextInputColumn;
use Illuminate\Database\Eloquent\Collection;

class CapemiacsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cliente')
                    ->searchable(),
                TextColumn::make('segmento')
                    ->searchable(),
                TextColumn::make('rif')
                    ->searchable(),
                TextInputColumn::make('telefonoUno')
                    ->searchable(),
                TextInputColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('fecha_registro')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('associateInfo')
                        ->label('Asociar informacion')
                        ->icon('heroicon-s-link')
                        ->form([
                            Fieldset::make('Asociar Información')
                                ->columns(1)
                                ->schema([
                                    Select::make('mass_notification_id')
                                        ->label('Asociar Notificación')
                                        ->options(MassNotification::all()->pluck('title', 'id'))
                                        ->required(),
                                ])
                        ])
                        ->action(function (Collection $records, $data) {

                            $info = $records->toArray();

                            for ($i = 0; $i < count($info); $i++) {
                                $dataInfo = new DataNotification();
                                $dataInfo->mass_notification_id = $data['mass_notification_id'];
                                $dataInfo->fullName             = $info[$i]['cliente'];
                                $dataInfo->email                = $info[$i]['email'];
                                $dataInfo->phone                = $info[$i]['telefonoUno'];
                                $dataInfo->save();
                            }

                            Notification::make()
                                ->title('Información asociada')
                                ->body(count($info) . ' agencias asociados correctamente.')
                                ->success()
                                ->send();

                            $id = $data['mass_notification_id'];

                            return redirect()->route('filament.marketing.resources.mass-notifications.view', ['record' => $id]);
                        })
                        ->requiresConfirmation()
                        ->color('primary'),
                ]),
            ]);
    }
}