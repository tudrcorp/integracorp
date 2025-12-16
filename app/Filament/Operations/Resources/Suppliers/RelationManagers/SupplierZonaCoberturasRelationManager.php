<?php

namespace App\Filament\Operations\Resources\Suppliers\RelationManagers;

use BackedEnum;
use App\Models\City;
use App\Models\State;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use App\Models\SupplierClasificacion;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Actions\DissociateBulkAction;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\RelationManagers\RelationManager;

class SupplierZonaCoberturasRelationManager extends RelationManager
{
    protected static string $relationship = 'SupplierZonaCoberturas';

    protected static ?string $title = 'Zonas de Cobertura';

    protected static string|BackedEnum|null $icon = 'heroicon-o-globe-europe-africa';
    
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type_service')
                    ->label('Clasificación del Proveedor')
                    ->searchable()
                    ->options(SupplierClasificacion::all()->pluck('description', 'description'))
                    ->preload()
                    ->searchable(),
                Select::make('state_id')
                    ->options(State::all()->pluck('definition', 'id'))
                    // ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                    ->label('Estado')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('city_id')
                    ->options(fn(Get $get) => City::where('state_id', $get('state_id'))->pluck('definition', 'id'))
                    ->label('Ciudad')
                    ->live()
                    ->searchable()
                    ->preload()
                    ->required(),
                Hidden::make('created_by')->default(Auth::user()->name),
                Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('supplier_id')
            ->columns([
                TextColumn::make('supplierClasificacion.description')
                    ->label('Clasificacion del Proveedor')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('type_service')
                    ->label('Tipo de Servicio')
                    ->badge()
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado Por')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // CreateAction::make(),
                // AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                // DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}