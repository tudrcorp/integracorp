<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Tables;


use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TelemedicineDoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Tabla principal de Doctores')   
            ->description('...')
            ->columns([
                TextColumn::make('full_name')
                    ->Label('Nombre y Apellido')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('code_cm')
                    ->label('Código CM')
                    ->searchable(),
                TextColumn::make('code_mpps')
                    ->label('Código MPPS')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('specialty')
                    ->label('Especialidad')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creado')
                    ->dateTime()
                    ->sortable(),            
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                    ->label('Eliminar Registro(s)')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Eliminación')
                    ->modalDescription('¿Está seguro de que desea eliminar el/los registro(s) seleccionado(s)?. Esta accion eliminara el registro en la tabla de usuarios de sistema. ESTA OPERACION NO PODRA SER REVERSADA')
                    ->modalSubmitActionLabel('Eliminar Registro(s)')
                    ->modalCancelActionLabel('Cancelar')
                    ->action(function (Collection $records) {
                        foreach($records as $record){
                            Log::info('OPERACIONES: El usuario ' . Auth::user()->name . ' elimino al doctor: ' . $record->full_name);
                            Log::info('OPERACIONES: El usuario ' . Auth::user()->name . 'elimino registro en la tabla de usuarios de sistema: ' . $record->email);
                            User::where('email', $record->email)->delete();
                            $record->delete();
                        }
                    })

                ]),
            ]);
    }
}