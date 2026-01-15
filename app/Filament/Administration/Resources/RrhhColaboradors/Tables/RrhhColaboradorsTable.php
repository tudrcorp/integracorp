<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class RrhhColaboradorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fullName')
                    ->label('Nombre y Apellido')
                    ->searchable(),
                TextColumn::make('departmento_id')
                    ->label('Departamento')
                    ->searchable(),
                TextColumn::make('cargo_id')
                    ->label('Cargo')
                    ->searchable(),
                TextColumn::make('cedula')
                    ->label('Cédula')
                    ->searchable(),
                TextColumn::make('sexo')
                    ->label('Sexo')
                    ->searchable(),
                TextColumn::make('fechaNacimiento')
                    ->label('Fecha de Nacimiento')
                    ->searchable(),
                TextColumn::make('fechaIngreso')
                    ->label('Fecha de Ingreso')
                    ->searchable(),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('telefonoCorporativo')
                    ->label('Teléfono Corporativo')
                    ->searchable(),
                TextColumn::make('emailCorporativo')
                    ->label('Email Corporativo')
                    ->searchable(),
                TextColumn::make('emailAlternativo')
                    ->label('Email Alternativo')
                    ->searchable(),
                TextColumn::make('emailPersonal')
                    ->label('Email Personal')
                    ->searchable(),
                TextColumn::make('direccion')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('nroHijos')
                    ->label('Hijos')
                    ->searchable(),
                TextColumn::make('nroHijoDependiente')
                    ->label('Hijos Dependientes')
                    ->searchable(),
                TextColumn::make('tallaCamisa')
                    ->label('Talla de Camisa')
                    ->searchable(),
                TextColumn::make('banck_id')
                    ->label('Banco')
                    ->searchable(),
                TextColumn::make('nroCta')
                    ->label('Nro de Cuenta')
                    ->searchable(),
                TextColumn::make('codigoCta')
                    ->label('Código de Cuenta')
                    ->searchable(),
                TextColumn::make('tipoCta')
                    ->label('Tipo de Cuenta')
                    ->searchable(),
                TextInputColumn::make('sueldo')
                    ->label('Sueldo US$')
                    ->prefixIcon('heroicon-o-currency-dollar')
                    ->rules(['numeric'])
                    ->validationMessages([
                        'numeric' => 'El sueldo debe ser un número',
                    ])
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->searchable(),
                TextColumn::make('updated_by')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
