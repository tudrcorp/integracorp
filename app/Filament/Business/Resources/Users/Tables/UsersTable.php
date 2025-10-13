<?php

namespace App\Filament\Business\Resources\Users\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->searchable(),
                TextInputColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextInputColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->searchable(),
                TextInputColumn::make('email')
                    ->label('Correo Electronico')
                    ->searchable(),
                    
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}