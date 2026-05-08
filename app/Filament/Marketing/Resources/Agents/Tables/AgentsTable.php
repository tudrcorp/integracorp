<?php

namespace App\Filament\Marketing\Resources\Agents\Tables;

use App\Models\DataNotification;
use App\Models\MassNotification;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
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
                TextInputColumn::make('email')
                    ->label('Correo electrónico')
                    ->prefixIcon('fontisto-email')
                    ->searchable(),
                TextInputColumn::make('phone')
                    ->label('Número de Teléfono')
                    ->prefixIcon('heroicon-m-phone')
                    ->searchable(),
                TextInputColumn::make('user_instagram')
                    ->label('Usuario de Instagram')
                    ->prefixIcon('fontisto-instagram'),
            ])
            ->filters([
                //
            ])
            ->recordActions([

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('associateInfo')
                        ->label('Asociar información')
                        ->icon('heroicon-s-link')
                        ->form([
                            Fieldset::make('Asociar a notificación masiva')
                                ->columns(1)
                                ->schema([
                                    Select::make('mass_notification_id')
                                        ->label('Notificación')
                                        ->options(fn (): array => MassNotification::query()->orderBy('title')->pluck('title', 'id')->all())
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->native(false),
                                ]),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $rows = $records->all();

                            foreach ($rows as $row) {
                                $dataInfo = new DataNotification;
                                $dataInfo->mass_notification_id = $data['mass_notification_id'];
                                $dataInfo->fullName = $row->name;
                                $dataInfo->email = $row->email;
                                $dataInfo->phone = $row->phone;
                                $dataInfo->save();
                            }

                            Notification::make()
                                ->title('Información asociada')
                                ->body(count($rows).' '.(count($rows) === 1 ? 'agente asociado' : 'agentes asociados').' correctamente.')
                                ->success()
                                ->send();

                            return redirect()->route('filament.marketing.resources.mass-notifications.view', ['record' => $data['mass_notification_id']]);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Asociar a notificación')
                        ->modalDescription('Los agentes seleccionados se vincularán a la notificación masiva elegida.')
                        ->modalSubmitActionLabel('Asociar')
                        ->color('primary'),
                ]),
            ]);
    }
}
