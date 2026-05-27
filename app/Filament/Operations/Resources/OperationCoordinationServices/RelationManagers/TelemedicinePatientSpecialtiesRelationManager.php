<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers;

use App\Http\Controllers\OperationServiceOrderController;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Models\TelemedicinePatientSpecialty;
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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class TelemedicinePatientSpecialtiesRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientSpecialties';

    protected static ?string $title = 'Especialidades';

    protected static string|BackedEnum|null $icon = 'healthicons-f-stethoscope';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Especialidades solicitadas')
            ->description(fn (RelationManager $livewire): string => 'Indicador por el Dr(a): '.$livewire->ownerRecord->telemedicineDoctor->full_name.'. No se pueden seleccionar registros con estatus EN GESTION.')
            ->checkIfRecordIsSelectableUsing(fn ($record): bool => ($record->status ?? null) !== 'EN GESTION')
            ->columns([
                TextColumn::make('specialty')
                    ->label('Especialidad')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($record): string => $record->type == 'CUBIERTO' ? 'success' : 'danger')
                    ->icon(fn ($record): string => $record->type == 'CUBIERTO' ? 'heroicon-m-check' : 'heroicon-o-x-mark')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn ($record): string => match ($record->status) {
                        'EN GESTION' => 'warning',
                        'FINALIZADO' => 'success',
                        'CANCELADA' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn ($record): string => match ($record->status) {
                        'EN GESTION' => 'heroicon-m-clock',
                        'FINALIZADO' => 'heroicon-m-check-circle',
                        'CANCELADA' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Solicitud')
                    ->description(fn (TelemedicinePatientSpecialty $record): string => $record->created_at->diffForHumans())
                    ->badge()
                    ->date('d/m/Y')
                    ->color('primary')
                    ->icon('heroicon-s-calendar')
                    ->searchable(),
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
                        ->modalDescription('Se creara una orden de servicio para las especialidades seleccionadas')
                        ->modalSubmitActionLabel('Crear Orden de Servicio')
                        ->modalCancelActionLabel('Cancelar')
                        ->modalWidth(Width::SixExtraLarge)
                        ->form(function (Collection $records): array {
                            $specialtiesList = $records->map(function (TelemedicinePatientSpecialty $record): array {
                                return [
                                    'specialty' => $record->specialty ?? '-',
                                    'type' => $record->type ?? '-',
                                    'status' => $record->status ?? '-',
                                ];
                            })->values()->toArray();

                            return [
                                Section::make('Especialidades seleccionadas')
                                    ->description('Listado de especialidades incluidas en esta orden.')
                                    ->icon(Heroicon::OutlinedListBullet)
                                    ->schema([
                                        Repeater::make('specialties_list')
                                            ->label('')
                                            ->default($specialtiesList)
                                            ->schema([
                                                Grid::make(3)
                                                    ->schema([
                                                        TextInput::make('specialty')
                                                            ->label('Especialidad')
                                                            ->disabled()
                                                            ->dehydrated(false)
                                                            ->columnSpan(2),
                                                        TextInput::make('type')
                                                            ->label('Tipo')
                                                            ->disabled()
                                                            ->dehydrated(false),
                                                    ])
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Proveedor y prioridad')
                                    ->description('Asigna el proveedor TDG y la prioridad de la orden. El número de orden se genera automáticamente.')
                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                    ->schema([
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
                                    ->description('Describe el servicio solicitado para las especialidades seleccionadas.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->schema([
                                        TextInput::make('service_type')
                                            ->label('Tipo de servicio')
                                            ->disabled()
                                            ->dehydrated()
                                            ->prefixIcon(Heroicon::OutlinedSquares2x2)
                                            ->default('ESPECIALISTA'),
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
                                            ->placeholder('Ej.: tomar muestra en ayunas, horario sugerido, etc.')
                                            ->rows(4)
                                            ->maxLength(2000)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Hidden::make('created_by')
                                    ->default(Auth::user()->name)
                                    ->dehydrated(),
                                Hidden::make('updated_by')
                                    ->default(Auth::user()->name)
                                    ->dehydrated(),
                            ];
                        })
                        ->action(function (Collection $records, array $data, RelationManager $livewire): void {
                            $ownerRecord = $livewire->ownerRecord->toArray();

                            OperationServiceOrderController::create($data, $ownerRecord, $records);

                            foreach ($records as $record) {
                                $record->update(['status' => 'EN GESTION']);
                            }

                            $livewire->ownerRecord->status = 'EN GESTION';
                            $livewire->ownerRecord->updated_by = Auth::user()->name;
                            $livewire->ownerRecord->save();
                        }),
                ]),
            ]);
    }
}
