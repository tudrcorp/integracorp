<?php

namespace App\Filament\Marketing\Resources\AffiliationCorporates\Tables;

use App\Models\DataNotification;
use App\Models\MassNotification;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AffiliationCorporatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agency.name_corporative')
                    ->label('Agencia')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif')
                    ->prefix('J-')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextInputColumn::make('activated_at')
                    ->label('Fecha de nacimiento')
                    ->prefixIcon('heroicon-m-calendar')
                    ->searchable(),
                TextInputColumn::make('email')
                    ->label('Email contratante')
                    ->prefixIcon('fontisto-email')
                    ->searchable(),
                TextInputColumn::make('phone')
                    ->label('Telefono contratante')
                    ->prefixIcon('heroicon-m-phone')
                    ->searchable(),
            ])
            ->filters([
                //
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
                                $dataInfo->fullName             = $info[$i]['name_corporate'];
                                $dataInfo->email                = $info[$i]['email'];
                                $dataInfo->phone                = $info[$i]['phone'];
                                $dataInfo->save();
                            }

                            Notification::make()
                                ->title('Información asociada')
                                ->body(count($info) . ' afiliados corporativos asociados correctamente.')
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