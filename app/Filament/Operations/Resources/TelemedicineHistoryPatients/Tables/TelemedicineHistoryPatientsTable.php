<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Tables;

use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineHistoryPatient;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use League\CommonMark\Extension\DescriptionList\Node\Description;

class TelemedicineHistoryPatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Tabla de Historias Clínicas')
            ->description('ESta tabla muestra una informacion resumida del paciente, si desea ver el detalle por favor haga click en el boton "Ver"')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    // ->badge()
                    ->icon('heroicon-o-hashtag')
                    ->formatStateUsing(fn(string $state): string => strtoupper($state))
                    ->extraAttributes(function ($record) {
                        /**
                         * Generamos un color único basado en el valor del código para evitar repeticiones aleatorias.
                         * Usamos crc32 para obtener un número entero a partir del string del código.
                         */
                        $codeValue = $record->code ?? 'default';
                        $hash = crc32($codeValue);

                        // El tono (Hue) se deriva del hash (0-360)
                        $h = abs($hash % 360);

                        // Saturación y Luminosidad fijas para mantener el efecto pastel
                        $s = 70;
                        $l = 85;

                        $backgroundColor = "hsl({$h}, {$s}%, {$l}%)";
                        $textColor = "hsl({$h}, {$s}%, 25%)";

                        return [
                            'style' => "
                                --c-50: {$backgroundColor};
                                --c-400: {$textColor};
                                --c-600: {$textColor};
                                background-color: {$backgroundColor} !important;
                                color: {$textColor} !important;
                                // border: 1px solid rgba(0,0,0,0.1);
                                border-radius: 9999px;
                                padding: 0.1rem 0.6rem;
                                font-weight: 600;
                                box-shadow: 0px 0px 0px 1px rgb(0 0 0 / 0.06),
                                0px 1px 1px -0.5px rgb(0 0 0 / 0.06),
                                0px 3px 3px -1.5px rgb(0 0 0 / 0.06), 
                                0px 6px 6px -3px rgb(0 0 0 / 0.06),
                                0px 12px 12px -6px rgb(0 0 0 / 0.06),
                                0px 24px 24px -12px rgb(0 0 0 / 0.06);
                            ",
                        ];
                    })
                    ->searchable(),
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Doctor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creacion')
                    ->dateTime()
                    ->sortable()
                    ->description(fn (TelemedicineHistoryPatient $record) => $record->created_at->diffForHumans()),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualizacion')
                    ->dateTime()
                    ->sortable()
                    ->description(fn(TelemedicineHistoryPatient $record) => $record->updated_at->diffForHumans()),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                ->label('Ver Detalle')
                ->color('success')
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar Historia')
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Historia de Paciente')
                        ->modalDescription('¿Estas seguro de eliminar la historia del paciente?. ESTA OOPERACION NO PODRA SER REVERSADA.')
                        ->modalIcon('heroicon-o-trash')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                Log::info('OPERACIONES: El usuario ' . Auth::user()->name . ' elimino la historia clinica del paciente: ' . $record->telemedicinePatient->full_name);
                                $record->delete();
                            }
                        })
                ]),
            ])
            ->striped();
    }
}
