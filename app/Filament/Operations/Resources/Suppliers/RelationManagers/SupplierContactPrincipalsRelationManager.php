<?php

namespace App\Filament\Operations\Resources\Suppliers\RelationManagers;

use BackedEnum;
use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\RelationManagers\RelationManager;

class SupplierContactPrincipalsRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierContactPrincipals';

    protected static ?string $title = 'Contactos Principales';

    protected static string|BackedEnum|null $icon = 'heroicon-o-users';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Formulario de Contactos')
                    ->schema([
                        TextInput::make('departament')
                            ->label('Departamento'),
                        TextInput::make('position')
                            ->label('Cargo'),
                        TextInput::make('name')
                            ->label('Nombre y Apellido')
                            ->required(),
                        TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email(),
                        TextInput::make('personal_phone')
                            ->label('Teléfono Celular')
                            ->helpertext('Formato de teléfono: 04122346790, sin espacios( ), sin guiones(-).')
                            ->required()
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('local_phone')
                            ->label('Teléfono Local')
                            ->helperText('Formato de teléfono: 02124357898, sin espacios( ), sin guiones(-).')
                            ->required()
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('created_by')
                            ->disabled()
                            ->dehydrated()
                            ->default(Auth::User()->name)
                        
                    ])->columnSpanFull()->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Contactos Principales')
            ->description('Lista de contactos principales relacionados con el proveedor')
            ->recordTitleAttribute('supplier_id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('departament')
                    ->label('Departamento')
                    ->searchable(),
                TextColumn::make('position')
                    ->label('Cargo')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre y Apellido')
                    ->searchable(),
                TextColumn::make('personal_phone')
                    ->label('Telofono Celular')
                    ->searchable(),
                TextColumn::make('local_phone')
                    ->label('Telefono Local')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->searchable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
            ->headerActions([
                // CreateAction::make()
                //     ->modalHeading('')
                //     ->createAnother(false)
                //     ->label('Agregar Contacto')
                //     ->icon('heroicon-o-plus'),
                // AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}