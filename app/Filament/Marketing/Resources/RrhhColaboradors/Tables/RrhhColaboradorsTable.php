<?php

namespace App\Filament\Marketing\Resources\RrhhColaboradors\Tables;

use App\Models\DataNotification;
use App\Models\MassNotification;
use App\Models\RrhhCargo;
use App\Models\RrhhDepartamento;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class RrhhColaboradorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fullName')
                    ->searchable(),
                SelectColumn::make('departamento_id')
                    ->options(RrhhDepartamento::all()->pluck('description', 'id')->toArray()),
                SelectColumn::make('cargo_id')
                    ->options(RrhhCargo::all()->pluck('description', 'id')->toArray()),
                TextInputColumn::make('fechaNacimiento')
                    ->searchable(),
                TextInputColumn::make('fechaIngreso')
                    ->searchable(),
                TextInputColumn::make('telefono')
                    ->searchable(),
                TextInputColumn::make('telefonoCorporativo')
                    ->searchable(),
                TextInputColumn::make('emailCorporativo')
                    ->searchable(),
            ])
            ->filters([
                //
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
                                $dataInfo->fullName = $row->fullName;
                                $dataInfo->email = filled($row->emailCorporativo)
                                    ? $row->emailCorporativo
                                    : $row->emailPersonal;
                                $dataInfo->phone = filled($row->telefonoCorporativo)
                                    ? $row->telefonoCorporativo
                                    : $row->telefono;
                                $dataInfo->save();
                            }

                            Notification::make()
                                ->title('Información asociada')
                                ->body(count($rows).' '.(count($rows) === 1 ? 'colaborador asociado' : 'colaboradores asociados').' correctamente.')
                                ->success()
                                ->send();

                            return redirect()->route('filament.marketing.resources.mass-notifications.view', ['record' => $data['mass_notification_id']]);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Asociar a notificación')
                        ->modalDescription('Los colaboradores seleccionados se vincularán a la notificación masiva elegida.')
                        ->modalSubmitActionLabel('Asociar')
                        ->color('primary'),
                ]),
            ]);
    }
}
