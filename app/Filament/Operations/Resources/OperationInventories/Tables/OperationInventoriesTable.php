<?php

namespace App\Filament\Operations\Resources\OperationInventories\Tables;

use App\Models\OperationInventory;
use App\Models\OperationInventoryEntry;
use App\Models\OperationInventoryProductCategory;
use App\Models\OperationInventoryProductStock;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OperationInventoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Inventario general')
            ->description('Existencias por producto y almacén en Diagnomovil.')
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->circular()
                    ->disk('public')
                    ->visibility('public')
                    ->imageHeight(40)
                    ->imageWidth(40)
                    ->defaultImageUrl(fn (OperationInventory $record): string => 'https://ui-avatars.com/api/?name='.urlencode($record->name ?? 'N').'&background=0D8ABC&color=fff&size=96')
                    ->toggleable(),
                TextColumn::make('product.code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (OperationInventory $record): string => $record->product?->code
                        ?? $record->barcode
                        ?? ('INV-'.str_pad((string) $record->id, 5, '0', STR_PAD_LEFT))),
                TextColumn::make('name')
                    ->label('Producto')
                    ->icon('heroicon-o-cube')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (OperationInventory $record): string => trim((string) $record->name)),
                TextColumn::make('ubicationRelation.name')
                    ->label('Almacén')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-building-storefront')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (OperationInventory $record): string => $record->ubicationRelation?->name
                        ?? $record->ubication
                        ?? '—'),
                TextColumn::make('existence')
                    ->label('Existencia')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (OperationInventory $record): string => match (true) {
                        $record->existence <= 0 => 'gray',
                        $record->existence <= ($record->min_stock ?: 5) => 'danger',
                        default => 'success',
                    })
                    ->icon(fn (OperationInventory $record): string => match (true) {
                        $record->existence <= 0 => 'heroicon-o-minus-circle',
                        $record->existence <= ($record->min_stock ?: 5) => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-check-circle',
                    })
                    ->suffix(' und.'),
                TextColumn::make('unit')
                    ->label('Unidad')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('cost')
                    ->label('Costo')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventory $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('operation_inventory_ubication_id')
                    ->label('Almacén')
                    ->relationship('ubicationRelation', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('product_category')
                    ->label('Categoría')
                    ->options(fn (): array => OperationInventoryProductCategory::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q): Builder => $q->whereHas(
                            'product',
                            fn (Builder $productQuery): Builder => $productQuery->where(
                                'operation_inventory_product_category_id',
                                $data['value']
                            )
                        )
                    ))
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->placeholder('Todos'),
                TernaryFilter::make('low_stock')
                    ->label('Stock bajo')
                    ->trueLabel('Solo stock bajo')
                    ->falseLabel('Stock normal')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('existence', '<=', 5),
                        false: fn (Builder $query): Builder => $query->where('existence', '>', 5),
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                EditAction::make()->label('Editar'),
                ActionGroup::make([
                    Action::make('add_operation_inventory_entry')
                        ->icon('heroicon-o-plus-circle')
                        ->label('Registrar entrada')
                        ->color('success')
                        ->modalHeading('Registrar entrada de inventario')
                        ->modalWidth('lg')
                        ->form([
                            Section::make('Reposición')
                                ->description(fn (OperationInventory $record): string => 'Producto: '.$record->name.' · Almacén: '.($record->ubicationRelation?->name ?? $record->ubication ?? '—'))
                                ->icon('heroicon-o-plus-circle')
                                ->schema([
                                    TextInput::make('quantity')
                                        ->label('Cantidad entrante')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required()
                                        ->suffix('und.'),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->action(function (OperationInventory $record, array $data): void {
                            try {
                                DB::transaction(function () use ($record, $data): void {
                                    $quantity = max(1, (int) $data['quantity']);
                                    $userName = Auth::user()?->name ?? 'system';

                                    $record->existence += $quantity;
                                    $record->updated_by = $userName;
                                    $record->save();

                                    OperationInventoryEntry::query()->create([
                                        'operation_inventory_id' => $record->id,
                                        'operation_inventory_product_id' => $record->operation_inventory_product_id,
                                        'operation_inventory_ubication_id' => $record->operation_inventory_ubication_id,
                                        'operation_inventory_type_id' => $record->operation_inventory_type_id,
                                        'quantity' => $quantity,
                                        'type_entry' => 'REPOSICIÓN DE INVENTARIO',
                                        'created_by' => $userName,
                                    ]);

                                    if ($record->operation_inventory_product_id && $record->operation_inventory_ubication_id) {
                                        $stock = OperationInventoryProductStock::query()->firstOrNew([
                                            'operation_inventory_product_id' => $record->operation_inventory_product_id,
                                            'operation_inventory_ubication_id' => $record->operation_inventory_ubication_id,
                                        ]);

                                        if (! $stock->exists) {
                                            $stock->created_by = $userName;
                                            $stock->existence = 0;
                                        }

                                        $stock->existence = (int) $stock->existence + $quantity;
                                        $stock->updated_by = $userName;
                                        $stock->save();
                                    }
                                });

                                Notification::make()
                                    ->title('Entrada registrada')
                                    ->body('La existencia se actualizó correctamente.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Error al registrar entrada')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])->icon('heroicon-o-ellipsis-vertical')->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Eliminar seleccionados'),
                ]),
            ]);
    }
}
