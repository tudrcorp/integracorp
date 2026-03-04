<?php

namespace App\Filament\Operations\Resources\OperationInventories\Schemas;

use App\Models\OperationInventory;
use App\Models\OperationInventoryCategory;
use App\Models\OperationInventoryPrincipleActive;
use App\Models\OperationInventoryType;
use App\Models\OperationInventoryUbication;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class OperationInventoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacion del Medicamento')
                    ->description('Informacion principal del medicamento')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Fieldset::make('Producto/Medicamento')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Descripción del Producto/Medicamento')
                                            ->afterStateUpdatedJs(<<<'JS'
                                                $set('name', $state.toUpperCase());
                                            JS)
                                            ->required(),
                                        TextInput::make('concentration')
                                            ->label('Concentración')
                                            ->helperText('Concentración del medicamento, ejemplo: 50mg/ml, 100mg/g, 150mg/l, etc.')
                                            ->required()
                                            ->afterStateUpdatedJs(<<<'JS'
                                                $set('concentration', $state.toUpperCase());
                                            JS)
                                            ->placeholder('50mg/ml'),
                                        TextInput::make('laboratory')
                                            ->label('Laboratorio')
                                            ->helperText('Laboratorio del medicamento, ejemplo: BAYER, MERCK, FARMALAB, etc.'),
                                        TextInput::make('unit')
                                            ->label('Unidad de Medida')
                                            ->helperText('Unidad de medida del medicamento, ejemplo: UNIDAD, TABLETAS, GOTAS, AMPOLLAS, etc.')
                                            ->afterStateUpdatedJs(<<<'JS'
                                                $set('unit', $state.toUpperCase());
                                            JS)
                                            ->required(),
                                        Select::make('operation_inventory_type_id')
                                            ->label('Tipo')
                                            ->searchable()
                                            ->preload()
                                            ->options(OperationInventoryType::where('is_active', true)->pluck('name', 'id'))
                                            ->required(),
                                        Select::make('operation_inventory_principle_active_id')
                                            ->label('Principio Activo')
                                            ->searchable()
                                            ->preload()
                                            ->options(OperationInventoryPrincipleActive::where('is_active', true)->pluck('name', 'id'))
                                            ->required(),
                                        Select::make('operation_inventory_category_id')
                                            ->label('Categoría')
                                            ->searchable()
                                            ->preload()
                                            ->options(OperationInventoryCategory::where('is_active', true)->pluck('name', 'id')),
                                        TextInput::make('cost')
                                            ->label('Costo del Medicamento')
                                            ->helperText('Costo del medicamento en dolares, para uso de la parte administrativa')
                                            ->numeric()
                                            ->default(0.0)
                                            ->prefix('$'),
                                        TextInput::make('existence')
                                            ->label('Existencia')
                                            ->numeric()
                                            ->required()
                                            ->placeholder('5')
                                            ->hidden('edit'),
                                        TextInput::make('barcode')
                                            ->label('Código de Barras')
                                            ->helperText('Código de barras del medicamento, para uso de la parte administrativa')
                                            ->numeric()
                                            ->placeholder('1234567890'),
                                        TextInput::make('min_stock')
                                            ->label('Stock Mínimo')
                                            ->helperText('Stock mínimo del medicamento, para uso de la parte administrativa')
                                            ->numeric()
                                            ->required()
                                            ->placeholder('5')
                                            ->suffix('Unidades'),
                                        Select::make('ubication')
                                            ->label('Almacén')
                                            ->helperText('Esta información es para el manejo del inventario en diferentes almacenes')
                                            ->searchable()
                                            ->preload()
                                            ->options(OperationInventoryUbication::where('is_active', true)->pluck('name', 'name'))
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label('Nombre de la ubicación')
                                                    ->required(),
                                                Hidden::make('created_by')->default(Auth::user()->name),
                                                Hidden::make('is_active')->default(true),
                                            ])
                                            ->live()
                                            ->required(),
                                        Hidden::make('created_by')->default(Auth::user()->name),
                                        Hidden::make('is_active')->default(true),
                                        Hidden::make('code')->default(function () {
                                            if (OperationInventory::max('id') == null) {
                                                $parte_entera = 0;
                                            } else {
                                                $parte_entera = OperationInventory::max('id');
                                            }
        
                                            return 'INV-000'.$parte_entera + 1;
                                        }),
                                    ])->columnSpanFull()
                            ])->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }
}
