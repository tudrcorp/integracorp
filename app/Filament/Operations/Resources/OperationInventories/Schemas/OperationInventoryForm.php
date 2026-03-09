<?php

namespace App\Filament\Operations\Resources\OperationInventories\Schemas;

use App\Models\OperationInventory;
use App\Models\OperationInventoryCategory;
use App\Models\OperationInventoryPrincipleActive;
use App\Models\OperationInventoryType;
use App\Models\OperationInventoryUbication;
use Filament\Forms\Components\FileUpload;
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
                Section::make('Imagen del producto')
                    ->description('Foto o imagen del medicamento o producto para identificación visual')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Imagen del producto/medicamento')
                            ->image()
                            ->directory('operation-inventories-images')
                            ->visibility('public')
                            ->imagePreviewHeight('240')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Formatos: JPG, PNG o WebP. Máximo 2 MB.'),
                    ])
                    ->columnSpanFull(),

                Section::make('Información del medicamento')
                    ->description('Datos principales del producto o medicamento')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Fieldset::make('Datos del producto')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Descripción del producto/medicamento')
                                            ->afterStateUpdatedJs(<<<'JS'
                                                $set('name', $state.toUpperCase());
                                            JS)
                                            ->required(),
                                        TextInput::make('concentration')
                                            ->label('Concentración')
                                            ->helperText('Ej: 50mg/ml, 100mg/g')
                                            ->required()
                                            ->afterStateUpdatedJs(<<<'JS'
                                                $set('concentration', $state.toUpperCase());
                                            JS)
                                            ->placeholder('50mg/ml'),
                                        TextInput::make('laboratory')
                                            ->label('Laboratorio')
                                            ->helperText('Ej: BAYER, MERCK, FARMALAB'),
                                        TextInput::make('unit')
                                            ->label('Unidad de medida')
                                            ->helperText('Ej: UNIDAD, TABLETAS, GOTAS, AMPOLLAS')
                                            ->afterStateUpdatedJs(<<<'JS'
                                                $set('unit', $state.toUpperCase());
                                            JS)
                                            ->required(),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Fieldset::make('Clasificación')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('operation_inventory_type_id')
                                            ->label('Tipo')
                                            ->searchable()
                                            ->preload()
                                            ->options(OperationInventoryType::where('is_active', true)->pluck('name', 'id'))
                                            ->required(),
                                        Select::make('operation_inventory_principle_active_id')
                                            ->label('Principio activo')
                                            ->searchable()
                                            ->preload()
                                            ->options(OperationInventoryPrincipleActive::where('is_active', true)->pluck('name', 'id'))
                                            ->required(),
                                        Select::make('operation_inventory_category_id')
                                            ->label('Categoría')
                                            ->searchable()
                                            ->preload()
                                            ->options(OperationInventoryCategory::where('is_active', true)->pluck('name', 'id')),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Fieldset::make('Inventario y almacén')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('cost')
                                            ->label('Costo')
                                            ->helperText('Costo en dólares (uso administrativo)')
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
                                            ->label('Código de barras')
                                            ->numeric()
                                            ->placeholder('1234567890'),
                                        TextInput::make('min_stock')
                                            ->label('Stock mínimo')
                                            ->helperText('Unidades mínimas para alertas')
                                            ->numeric()
                                            ->required()
                                            ->placeholder('5')
                                            ->suffix('unidades'),
                                        Select::make('ubication')
                                            ->label('Almacén')
                                            ->helperText('Ubicación para manejo del inventario')
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
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Hidden::make('created_by')->default(Auth::user()->name),
                        Hidden::make('is_active')->default(true),
                        Hidden::make('code')->default(function () {
                            $maxId = OperationInventory::max('id');

                            return 'INV-000'.(($maxId ?? 0) + 1);
                        }),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
