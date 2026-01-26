<?php

namespace App\Filament\Marketing\Resources\Affiliations\Tables;

use App\Models\DataNotification;
use App\Models\Event;
use App\Models\MassNotification;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AffiliationsTable
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

                //...
                ColumnGroup::make('Información del Titular', [
                    TextColumn::make('full_name_ti')
                        ->label('Nombre titular')
                        ->badge()
                        ->color('azulOscuro')
                        ->searchable(),
                    TextInputColumn::make('birth_date_ti')
                        ->label('Fecha de nacimiento')
                        ->prefixIcon('heroicon-m-calendar')
                        ->searchable(),
                    TextInputColumn::make('phone_ti')
                        ->label('Telefono titular')
                        ->prefixIcon('heroicon-m-phone')
                        ->searchable(),
                    TextInputColumn::make('email_ti')
                        ->label('Email titular')
                        ->prefixIcon('fontisto-email')
                        ->searchable(),
                ]),
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
                                $dataInfo->fullName             = $info[$i]['full_name_ti'];
                                $dataInfo->email                = $info[$i]['email_ti'];
                                $dataInfo->phone                = $info[$i]['phone_ti'];
                                $dataInfo->save();
                            }

                            Notification::make()
                                ->title('Información asociada')
                                ->body(count($info) . ' afiliados asociados correctamente.')
                                ->success()
                                ->send();

                            $id = $data['mass_notification_id'];

                            return redirect()->route('filament.marketing.resources.mass-notifications.view', ['record' => $id]);

                        } )
                        ->requiresConfirmation()
                        ->color('primary'),
                ]),
            ]);
    }
}