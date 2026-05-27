<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers;

use App\Http\Controllers\OperationServiceOrderController;
use App\Models\OperationInventoryUbication;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePriority;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TelemedicinePatientMedicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientMedications';

    protected static ?string $title = 'Medicamentos e Indicaciones';

    protected static string|BackedEnum|null $icon = 'healthicons-f-blister-pills-oval-x14';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Medicamentos Solicitados')
            ->description(fn (RelationManager $livewire): string => 'Indicador por el Dr(a): '.$livewire->ownerRecord->telemedicineDoctor->full_name.'. Solo se pueden seleccionar medicamentos con existencia > 0 en inventario y que no estén en estatus EN GESTION.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('operationInventory'))
            ->checkIfRecordIsSelectableUsing(function (TelemedicinePatientMedications $record): bool {
                if ($record->status === 'EN GESTION') {
                    return false;
                }

                if ($record->operation_inventory_id === null) {
                    return true;
                }

                return (int) ($record->operationInventory?->existence ?? 0) > 0;
            })
            ->columns([
                TextColumn::make('medicine')
                    ->label('Medicamento')
                    ->searchable(),
                TextColumn::make('indications')
                    ->label('Indicaciones')
                    ->searchable(),
                TextColumn::make('operationInventory.existence')
                    ->label('Existencia en inventario')
                    ->description(fn (TelemedicinePatientMedications $record): string => $record->operation_inventory_id
                        ? 'Unidades disponibles en inventario'
                        : 'Sin ítem de inventario asociado')
                    ->badge()
                    ->color(fn (TelemedicinePatientMedications $record): string => match (true) {
                        $record->operation_inventory_id === null => 'gray',
                        (int) ($record->operationInventory?->existence ?? 0) === 0 => 'danger',
                        default => 'success',
                    })
                    ->icon(fn (TelemedicinePatientMedications $record): string => match (true) {
                        $record->operation_inventory_id === null => 'heroicon-o-question-mark-circle',
                        (int) ($record->operationInventory?->existence ?? 0) === 0 => 'heroicon-o-x-circle',
                        default => 'heroicon-o-check-circle',
                    })
                    ->placeholder('-'),
                TextColumn::make('is_covered')
                    ->label('Está cubierto?')
                    // ->description('Indica si el medicamento está cubierto por el plan o convenio.')
                    ->getStateUsing(function (TelemedicinePatientMedications $record) {
                        if ($record->operation_inventory_id && $record->operationInventory) {
                            return $record->operationInventory->is_covered ?? null;
                        }

                        return $record->is_covered ?? null;
                    })
                    ->formatStateUsing(fn ($state): string => $state ? 'Cubierto' : 'No cubierto')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->icon(fn ($state): string => $state ? 'heroicon-o-shield-check' : 'heroicon-o-exclamation-triangle')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Fecha de Solicitud')
                    ->description(fn (TelemedicinePatientMedications $record): string => $record->created_at->diffForHumans())
                    ->sortable()
                    ->badge()
                    ->date('d/m/Y')
                    ->color('primary')
                    ->icon('heroicon-s-calendar')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->searchable()
                    ->badge()
                    ->color(fn ($record): string => $record->status == 'EN GESTION' ? 'warning' : 'danger')
                    ->icon(fn ($record): string => $record->status == 'EN GESTION' ? 'heroicon-s-check' : 'heroicon-s-x-mark')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('create_service_order')
                        ->label('Crear Orden de Servicio')
                        ->color('success')
                        ->icon('heroicon-s-plus')
                        ->modalHeading('Crear Orden de Servicio')
                        ->modalDescription('Se creara una orden de servicio para los medicamentos seleccionados')
                        ->modalSubmitActionLabel('Crear Orden de Servicio')
                        ->modalCancelActionLabel('Cancelar')
                        ->modalWidth(Width::SixExtraLarge)
                        ->form(function (Collection $records): array {
                            $records->load(['operationInventory.operationInventoryType']);
                            $medicationsList = $records->map(function (TelemedicinePatientMedications $rec) {
                                $inv = $rec->operationInventory;
                                $isCovered = $inv?->is_covered ?? $rec->is_covered ?? false;

                                return [
                                    'name' => $inv?->name ?? $rec->medicine ?? '-',
                                    'medicine_name' => $inv?->name ?? $rec->medicine ?? 'Medicamento',
                                    'unit' => $inv?->unit ?? '-',
                                    'operation_inventory_type_id' => $inv?->operation_inventory_type_id,
                                    'type_name' => $inv?->operationInventoryType?->name ?? '-',
                                    'is_covered_label' => $isCovered ? 'Cubierto' : 'No cubierto',
                                    'quantity' => 1,
                                    'indications' => $rec->indications ?? '',
                                    'links_inventory' => $rec->operation_inventory_id !== null,
                                    'max_stock_for_validation' => $rec->operation_inventory_id !== null
                                        ? max(0, (int) ($inv?->existence ?? 0))
                                        : 0,
                                ];
                            })->values()->toArray();

                            return [
                                Section::make('Medicamentos seleccionados')
                                    ->description('Listado de medicamentos incluidos en esta orden (datos desde operation_inventories).')
                                    ->icon(Heroicon::OutlinedListBullet)
                                    ->schema([
                                        Repeater::make('medications_list')
                                            ->label('')
                                            ->default($medicationsList)
                                            ->collapsible()
                                            ->collapsed()
                                            ->itemLabel(fn (array $state): string => (string) ($state['medicine_name'] ?? $state['name'] ?? 'Medicamento'))
                                            ->schema([
                                                Grid::make(4)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label('Nombre')
                                                            ->disabled()
                                                            ->dehydrated(false)
                                                            ->columnSpan(4),
                                                        TextInput::make('unit')
                                                            ->label('Unidad')
                                                            ->disabled()
                                                            ->dehydrated(false),
                                                        TextInput::make('type_name')
                                                            ->label('Tipo de inventario')
                                                            ->disabled()
                                                            ->dehydrated(false),
                                                        TextInput::make('is_covered_label')
                                                            ->label('Cubierto')
                                                            ->disabled()
                                                            ->dehydrated(false),
                                                        TextInput::make('quantity')
                                                            ->label('Cantidad')
                                                            ->numeric()
                                                            ->integer()
                                                            ->minValue(1)
                                                            ->default(1)
                                                            ->required()
                                                            ->live(onBlur: false)
                                                            ->helperText(function (Get $get): string {
                                                                if (! $get('links_inventory')) {
                                                                    return 'Unidades a solicitar (sin inventario vinculado; no aplica tope de existencia).';
                                                                }

                                                                $max = (int) $get('max_stock_for_validation');

                                                                return $max > 0
                                                                    ? "Disponible en inventario: {$max} unidad(es). No puede superar este límite."
                                                                    : 'Unidades a solicitar';
                                                            })
                                                            ->rule(function (Get $get): \Closure {
                                                                return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                                                    if (! filter_var($get('links_inventory'), FILTER_VALIDATE_BOOLEAN)) {
                                                                        return;
                                                                    }

                                                                    $max = (int) $get('max_stock_for_validation');
                                                                    if ($max <= 0) {
                                                                        return;
                                                                    }

                                                                    $qty = (int) $value;
                                                                    if ($qty > $max) {
                                                                        $fail("La cantidad no puede superar la existencia en inventario (máximo {$max} unidades).");
                                                                    }
                                                                };
                                                            }),
                                                    ])
                                                    ->columnSpanFull(),
                                                Textarea::make('indications')
                                                    ->label('Indicaciones')
                                                    ->placeholder('Dosis, frecuencia, duración, etc.')
                                                    ->rows(3)
                                                    ->required()
                                                    ->maxLength(2000)
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->columnSpanFull(),
                                                Hidden::make('operation_inventory_type_id')
                                                    ->dehydrated(false),
                                                Hidden::make('medicine_name')
                                                    ->dehydrated(true),
                                                Hidden::make('links_inventory')
                                                    ->dehydrated(true),
                                                Hidden::make('max_stock_for_validation')
                                                    ->dehydrated(true),
                                            ])
                                            ->columns(1)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Ubicación de despacho y proveedor')
                                    ->description('Define desde qué sede o bodega de inventario propio se despacharán los medicamentos de esta orden, y los datos del proveedor.')
                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                    ->schema([
                                        Fieldset::make('Inventario propio — origen del despacho')
                                            ->schema([
                                                Select::make('operation_inventory_ubication_id')
                                                    ->label('Despachar medicamentos desde')
                                                    ->placeholder('Seleccione la ubicación de inventario')
                                                    ->options(function (): array {
                                                        return OperationInventoryUbication::query()
                                                            ->where('is_active', true)
                                                            ->orderBy('name')
                                                            ->get()
                                                            ->mapWithKeys(function (OperationInventoryUbication $ubication): array {
                                                                $label = $ubication->name;
                                                                if (filled($ubication->address)) {
                                                                    $label .= ' — '.$ubication->address;
                                                                }

                                                                return [$ubication->id => $label];
                                                            })
                                                            ->all();
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->helperText('Todos los medicamentos seleccionados en esta orden se asocian a la misma ubicación de inventario propio.')
                                                    ->hint('Obligatorio para trazabilidad del despacho')
                                                    ->hintColor('primary')
                                                    ->prefixIcon(Heroicon::OutlinedMapPin)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                        Fieldset::make('Proveedor TDG y numeración')
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('order_number')
                                                            ->label('Número de orden')
                                                            ->default(function (): string {
                                                                $next = (int) (OperationServiceOrder::max('id') ?? 0) + 1;

                                                                return 'ORD-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
                                                            })
                                                            ->required()
                                                            ->prefixIcon(Heroicon::OutlinedHashtag)
                                                            ->helperText('Se genera automáticamente; puede ajustarlo si su proceso lo requiere.'),
                                                        Select::make('telemedicine_priority_id')
                                                            ->label('Prioridad de la orden')
                                                            ->options(TelemedicinePriority::query()->orderBy('name')->pluck('name', 'id'))
                                                            ->default(fn (RelationManager $livewire): string => $livewire->ownerRecord->telemedicine_priority_id)
                                                            ->dehydrated()
                                                            ->disabled()
                                                            ->prefixIcon(Heroicon::OutlinedBolt),
                                                        Select::make('supplier_id')
                                                            ->label('Proveedor TDG')
                                                            ->options(Supplier::query()->orderBy('name')->pluck('name', 'id'))
                                                            ->searchable()
                                                            ->preload()
                                                            ->placeholder('Seleccione proveedor interno')
                                                            ->prefixIcon(Heroicon::OutlinedBuildingOffice2),
                                                        TextInput::make('supplier_external')
                                                            ->label('Proveedor No Convenido')
                                                            ->placeholder('Nombre si el suministro es externo')
                                                            ->prefixIcon(Heroicon::OutlinedBuildingOffice2),
                                                    ]),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),

                                Section::make('Detalle del servicio')
                                    ->description('Describe el servicio solicitado para los medicamentos seleccionados.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->schema([
                                        TextInput::make('service_type')
                                            ->label('Tipo de servicio')
                                            ->disabled()
                                            ->dehydrated()
                                            ->prefixIcon(Heroicon::OutlinedSquares2x2)
                                            ->default('MEDICAMENTOS'),
                                        TextInput::make('description')
                                            ->label('Descripción')
                                            ->required()
                                            ->maxLength(500)
                                            ->columnSpanFull()
                                            ->prefixIcon(Heroicon::OutlinedDocumentText),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),

                                Section::make('Estatus de la orden')
                                    ->description('Estatus de la orden de servicio. El estado inicia en EN GESTION.')
                                    ->icon(Heroicon::OutlinedCreditCard)
                                    ->schema([
                                        TextInput::make('status')
                                            ->label('Estado')
                                            ->default('EN GESTION')
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->prefixIcon(Heroicon::OutlinedClock),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),

                                Section::make('Observaciones')
                                    ->description('Notas adicionales sobre la orden (opcional).')
                                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                                    ->schema([
                                        Textarea::make('observations')
                                            ->label('Observaciones')
                                            ->placeholder('Ej.: contactar al paciente antes de despacho, horario preferido, etc.')
                                            ->rows(4)
                                            ->maxLength(2000)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Hidden::make('created_by')->default(Auth::user()->name),
                                Hidden::make('updated_by')->default(Auth::user()->name),
                            ];
                        })
                        ->action(function (Collection $records, array $data, RelationManager $livewire) {

                            // dd($data, $records, $livewire->ownerRecord);
                            $ownerRecord = $livewire->ownerRecord->toArray();
                            // dd($data, $ownerRecord, $records->toArray());
                            // 1- Crear la orden de servicio y los items de la orden de servicio
                            OperationServiceOrderController::create($data, $ownerRecord, $records);

                            // 2- Actualizar el estatus de los medicamentos solicitados
                            foreach ($records as $record) {
                                $record->update(['status' => 'EN GESTION']);
                            }

                            // 3. Actualizar el estatus de la orden de servicio
                            $livewire->ownerRecord->status = 'EN GESTION';
                            $livewire->ownerRecord->updated_by = Auth::user()->name;
                            $livewire->ownerRecord->save();

                            // Si el proveedor es FARMADOC, crear la orden de servicio en FarmasysDoc
                            if ($data['supplier_id'] == 336) {

                                $response = OperationServiceOrderController::createOrderServiceInFramasysDoc($data, $ownerRecord, $records);

                                $response = json_decode(json_encode($response), true);

                                if ($response['original']['http_status'] == 200 || $response['original']['http_status'] == 201) {
                                    Notification::make()
                                        ->title('FARMASYSDOC')
                                        ->body($response['original']['response']['message'] ?? 'Orden de servicio creada exitosamente')
                                        ->icon('heroicon-s-check-circle')
                                        ->iconColor('success')
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('FARMASYSDOC')
                                        ->body($response['original']['response']['message'] ?? 'Error al crear la orden de servicio')
                                        ->icon('heroicon-s-x-circle')
                                        ->iconColor('danger')
                                        ->danger()
                                        ->send();
                                }

                            }
                        }),
                ]),
            ]);
    }
}
