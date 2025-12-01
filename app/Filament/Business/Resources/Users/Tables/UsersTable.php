<?php

namespace App\Filament\Business\Resources\Users\Tables;

use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextInputColumn;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextInputColumn::make('name')
                    ->label('Nombre y Apellido')
                    ->searchable()
                    ->afterStateUpdated(function ($record, $state) {
                        // Runs after the state is saved to the database.
                        Log::info("Usuario: ID {$record->id} name changed to: {$state}");
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                TextInputColumn::make('phone')
                        ->label('Telefono')
                        ->searchable()
                        ->afterStateUpdated(function ($record, $state) {
                            // Runs after the state is saved to the database.
                            Log::info("Usuario: ID {$record->id} phone changed to: {$state}");
                            $record->updated_by = Auth::user()->name;
                            $record->save();
                        }),
                TextInputColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->searchable()
                    ->afterStateUpdated(function ($record, $state) {
                        // Runs after the state is saved to the database.
                        Log::info("Usuario: ID {$record->id} birth_date changed to: {$state}");
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                TextInputColumn::make('email')
                    ->label('Correo Electronico')
                    ->searchable()
                    ->afterStateUpdated(function ($record, $state) {
                        // Runs after the state is saved to the database.
                        Log::info("Usuario: ID {$record->id} email changed to: {$state}");
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                    
                TextColumn::make('code_agency')
                    ->label('UID Agencia')
                    ->default(fn($record) => $record->code_agency == null ? '-----' : $record->code_agency)
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('agency_type')
                    ->label('Tipo de agencia')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('code_agent')
                    ->label('UID Agente')
                    ->default(fn($record) => $record->code_agent == null ? '-----' : $record->code_agent)
                    ->searchable(),
                TextColumn::make('departament')
                    ->label('Departamento')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('doctor_id')
                    ->label('ID Doctor')
                    ->default(fn($record) => $record->code_agent == null ? 'N/A' : $record->code_agent)
                    ->numeric()
                    ->sortable(),
                

                ColumnGroup::make('Roles de Usuario')
                    ->columns([
                        // ...
                        IconColumn::make('is_admin')
                            ->label('Administrador')
                            ->boolean(),
                        IconColumn::make('is_agency')
                            ->label('Agencia')
                            ->boolean(),
                        IconColumn::make('is_agent')
                            ->label('Agente')
                            ->boolean(),
                        IconColumn::make('is_subagent')
                            ->label('Subagente')
                            ->boolean(),
                        
                        IconColumn::make('is_doctor')
                            ->label('Doctor')
                            ->boolean(),
                        IconColumn::make('is_designer')
                            ->label('Diseñador')
                            ->boolean(),
                        IconColumn::make('is_accountManagers')
                            ->label('Managers')
                            ->boolean(),
                        IconColumn::make('is_superAdmin')
                            ->label('SuperAdmin')
                            ->boolean(),
                        IconColumn::make('is_business_admin')
                            ->label('BusinessAdmin')
                            ->boolean(),
                    ])
                    ->alignCenter()
                    ->wrapHeader(),
                
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
                    BulkAction::make('resetPassword')
                        ->label('Resetear Contraseña')
                        ->icon('heroicon-s-key')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->color('warning')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                $record->password = Hash::make('12345678');
                                $record->updated_by = Auth::user()->name;
                                $record->save();

                                Log::info("Usuario: ID {$record->id} contraseña reseteada");
                            }
                            Notification::make()
                                ->success()
                                ->title('Contraseña reseteada')
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}