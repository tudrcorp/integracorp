<?php

namespace App\Filament\Marketing\Resources\Affiliations\Tables;

use Closure;
use App\Models\Event;
use Filament\Tables\Table;
use App\Models\DataNotification;
use App\Models\MassNotification;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Database\Eloquent\Collection;
use Filament\Schemas\Components\Utilities\Get;

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
                ColumnGroup::make('Plan Afiliado', [
                    TextColumn::make('plan.description')
                        ->label('Plan')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('coverage.price')
                        ->label('Covertura')
                        ->alignCenter()
                        ->numeric()
                        ->badge()
                        ->color('success')
                        ->suffix(' US$')
                        ->searchable(),
                    TextColumn::make('payment_frequency')
                        ->label('Frecuencia de pago')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('family_members')
                        ->label('Poblacion')
                        ->alignCenter()
                        ->suffix(' persona(s)')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                ]),

                //...
                ColumnGroup::make('Información del Titular', [
                    TextColumn::make('full_name_ti')
                        ->label('Nombre titular')
                        ->badge()
                        ->color('azulOscuro')
                        ->searchable(),
                    TextColumn::make('nro_identificacion_ti')
                        ->label('CI. titular')
                        ->badge()
                        ->color('azulOscuro')
                        ->searchable(),
                    TextColumn::make('sex_ti')
                        ->label('Sexo')
                        ->searchable(),
                    TextColumn::make('birth_date_ti')
                        ->label('Fecha de nacimiento')
                        ->searchable(),
                    TextColumn::make('phone_ti')
                        ->label('Telefono titular')
                        ->icon('heroicon-m-phone')
                        ->searchable(),
                    TextColumn::make('email_ti')
                        ->label('Email titular')
                        ->icon('fontisto-email')
                        ->searchable(),
                    TextColumn::make('adress_ti')
                        ->label('Direccion')
                        ->icon('fontisto-map-marker-alt')
                        ->searchable(),
                    TextColumn::make('city.definition')
                        ->label('Ciudad')
                        ->searchable(),
                    TextColumn::make('state.definition')
                        ->label('Estado')
                        ->searchable(),
                    TextColumn::make('region_ti')
                        ->label('Region')
                        ->searchable(),
                    TextColumn::make('country.name')
                        ->label('Pais')
                        ->searchable(),
                ]),

                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'PRE-APROBADA'          => 'success',
                            'ACTIVA'                => 'success',
                            'PENDIENTE'             => 'warning',
                            'EXCLUIDO'              => 'danger',
                        };
                    })
                    ->searchable()
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA'          => 'heroicon-c-information-circle',
                            'ACTIVA'                => 'heroicon-s-check-circle',
                            'PENDIENTE'             => 'heroicon-s-exclamation-circle',
                            'EXCLUIDO'              => 'heroicon-c-x-circle',
                        };
                    }),
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

                        } )
                        ->requiresConfirmation()
                        ->color('primary'),
                ]),
            ]);
    }
}