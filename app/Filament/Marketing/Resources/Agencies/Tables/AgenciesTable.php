<?php

namespace App\Filament\Marketing\Resources\Agencies\Tables;

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

class AgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('typeAgency.definition')
                    ->label('Tipo agencia')
                    ->searchable()
                    ->badge()
                    ->color('azulOscuro')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('name_corporative')
                    ->label('Razon social')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('rif')
                    ->label('RIF:')
                    ->searchable()
                    ->badge()
                    ->color('verde')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextInputColumn::make('email')
                    ->prefixIcon('fontisto-email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextInputColumn::make('phone')
                    ->prefixIcon('heroicon-m-phone')
                    ->label('Número de Teléfono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextInputColumn::make('user_instagram')
                    ->prefixIcon('fontisto-instagram')
                    ->label('Usuario de Instagram')
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
                                $dataInfo->fullName = $row->name_corporative;
                                $dataInfo->email = $row->email;
                                $dataInfo->phone = $row->phone;
                                $dataInfo->save();
                            }

                            Notification::make()
                                ->title('Información asociada')
                                ->body(count($rows).' '.(count($rows) === 1 ? 'agencia asociada' : 'agencias asociadas').' correctamente.')
                                ->success()
                                ->send();

                            return redirect()->route('filament.marketing.resources.mass-notifications.view', ['record' => $data['mass_notification_id']]);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Asociar a notificación')
                        ->modalDescription('Las agencias seleccionadas se vincularán a la notificación masiva elegida.')
                        ->modalSubmitActionLabel('Asociar')
                        ->color('primary'),
                ]),
            ]);
    }
}
