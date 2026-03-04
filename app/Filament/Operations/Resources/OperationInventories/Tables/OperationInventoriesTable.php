<?php

namespace App\Filament\Operations\Resources\OperationInventories\Tables;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use App\Models\OperationInventoryEntry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OperationInventoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('INVENTARIOS')
            ->description('Lista de inventarios')
            // ->modifyQueryUsing(fn ($query) => $query->with('operationInventoryType', 'operationInventoryCategory'))
            ->columns([
                TextColumn::make('id')
                    ->label('Código')
                    ->prefix('INV-000')
                    ->badge()
                    ->color('azulOscuro')
                    ->icon('heroicon-s-circle-stack')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('unit')
                    ->label('Unidad')
                    ->searchable(),
                TextColumn::make('operationInventoryType.name')
                    ->label('Tipo')
                    ->badge()
                    ->color('azulOscuro')
                    ->icon('heroicon-o-document-check')
                    ->searchable(),
                TextColumn::make('operationInventoryCategory.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('azulOscuro')
                    ->icon('heroicon-s-tag')
                    ->searchable(),
                TextColumn::make('existence')
                    ->label('Existencia')
                    ->numeric()
                    ->badge()
                    ->color(fn ($record) => $record->existence <= 5 ? 'danger' : 'success')
                    ->icon(fn ($record) => $record->existence <= 5 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->iconColor(fn ($record) => $record->existence <= 5 ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('ubication')
                    ->label('Ubicación')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-map-pin')
                    ->searchable(),
                TextColumn::make('cost')
                    ->label('Costo')
                    ->money()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('add_operation_inventory_entry')
                        ->icon('heroicon-o-plus-circle')
                        ->label('Registro de Entrada')
                        ->color('success')
                        ->modalWidth('lg')
                        ->form([
                            Section::make('Agregar entrada de inventario')
                                ->description(fn ($record) => 'PRODUCTO: '.$record->name.' - TIPO: '.$record->operationInventoryType->name)
                                ->icon('heroicon-o-plus-circle')
                                ->schema([
                                    TextInput::make('quantity')
                                        ->label('Cantidad')
                                        ->numeric()
                                        ->required(),
                                ])->columnSpanFull()->columns(1),
                        ])
                        ->action(function ($record, $data) {

                            try {

                                DB::transaction(function () use ($record, $data) {

                                    $record->existence += $data['quantity'];
                                    $record->save();

                                    OperationInventoryEntry::create([
                                        'operation_inventory_id' => $record->id,
                                        'operation_inventory_type_id' => $record->operation_inventory_type_id,
                                        'quantity' => $data['quantity'],
                                        'type_entry' => 'REPOSICIÓN DE INVENTARIO',
                                        'created_by' => Auth::user()->name,
                                    ]);
                                });

                                Notification::make()
                                    ->title('ENTRADA DE INVENTARIO CREADA')
                                    ->body('La entrada de inventario se ha creado correctamente.')
                                    ->icon('heroicon-m-check-circle')
                                    ->iconColor('success')
                                    ->success()
                                    ->seconds(5)
                                    ->send();

                                return redirect()->to(OperationInventoryResource::getUrl('index'));

                            } catch (\Throwable $e) {

                                Notification::make()
                                    ->title('Error al crear entrada de inventario')
                                    ->body('No se pudo registrar la entrada. '.$e->getMessage())
                                    ->icon('heroicon-m-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->seconds(5)
                                    ->send();
                            }
                        }),
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
