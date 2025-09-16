<?php

namespace App\Filament\Marketing\Resources\Agents\Tables;

use Filament\Tables\Table;
use App\Models\DataNotification;
use App\Models\MassNotification;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Database\Eloquent\Collection;

class AgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->columns([
                TextColumn::make('id')
                    ->label('Código de agente')
                    ->prefix('AGT-000')
                    ->alignCenter()
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('typeAgent.definition')
                    ->label('Tipo de Agente')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('name')
                    ->label('Razon Social')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('ci')
                    ->label('CI:')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('address')
                    ->label('Direccion')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('phone')
                    ->label('Número de Teléfono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('user_instagram')
                    ->label('Usuario de Instagram')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('country.name')
                    ->label('País')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('region')
                    ->label('Región')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                //
            ])
            ->recordActions([

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
                                $dataInfo->fullName             = $info[$i]['name'];
                                $dataInfo->email                = $info[$i]['email'];
                                $dataInfo->phone                = $info[$i]['phone'];
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