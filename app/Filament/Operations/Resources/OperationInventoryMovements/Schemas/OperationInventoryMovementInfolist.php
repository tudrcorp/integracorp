<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Schemas;

use App\Models\OperationInventoryMovement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OperationInventoryMovementInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('operationInventoryMovementInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Resumen')
                            ->icon(Heroicon::ArrowsRightLeft)
                            ->schema([
                                Section::make('Movimiento de inventario')
                                    ->description('Producto, cantidad y estado del despacho o movimiento.')
                                    ->icon(Heroicon::ArrowsRightLeft)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Producto y almacén')
                                            ->schema([
                                                TextEntry::make('operationInventory.product.code')
                                                    ->label('Código')
                                                    ->badge()
                                                    ->color('primary')
                                                    ->state(fn (OperationInventoryMovement $record): string => $record->operationInventory?->product?->code
                                                        ?? $record->operationInventory?->barcode
                                                        ?? '—'),
                                                TextEntry::make('operationInventory.name')
                                                    ->label('Producto')
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                                TextEntry::make('operationInventory.ubicationRelation.name')
                                                    ->label('Almacén')
                                                    ->badge()
                                                    ->color('info')
                                                    ->state(fn (OperationInventoryMovement $record): string => $record->operationInventory?->ubicationRelation?->name
                                                        ?? $record->operationInventory?->ubication
                                                        ?? '—'),
                                            ])
                                            ->columns(2),
                                        Fieldset::make('Detalle del movimiento')
                                            ->schema([
                                                TextEntry::make('quantity')
                                                    ->label('Cantidad')
                                                    ->badge()
                                                    ->color('warning')
                                                    ->formatStateUsing(fn (mixed $state, OperationInventoryMovement $record): string => number_format((float) $state).' '.(filled($record->unit) ? $record->unit : 'und.')),
                                                TextEntry::make('unit')
                                                    ->label('Unidad')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('type')
                                                    ->label('Tipo')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('status')
                                                    ->label('Estado')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => match (mb_strtoupper(trim((string) ($state ?? '')))) {
                                                        'ACTIVO', 'COMPLETADO', 'FINALIZADO', 'DESPACHADO' => 'success',
                                                        'PENDIENTE' => 'warning',
                                                        'ANULADO', 'CANCELADO' => 'danger',
                                                        default => 'gray',
                                                    })
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Telemedicina')
                            ->icon(Heroicon::Heart)
                            ->schema([
                                Section::make('Vinculación clínica')
                                    ->description('Paciente, caso, consulta y médico asociados al movimiento.')
                                    ->icon(Heroicon::Heart)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Referencias')
                                            ->schema([
                                                TextEntry::make('telemedicinePatient.full_name')
                                                    ->label('Paciente')
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicineCase.code')
                                                    ->label('Caso')
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicineConsultation.id')
                                                    ->label('Consulta')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicineDoctor.full_name')
                                                    ->label('Doctor')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Negocio')
                            ->icon(Heroicon::BuildingOffice2)
                            ->schema([
                                Section::make('Unidad de negocio')
                                    ->description('Unidad y línea de negocio asociadas al movimiento.')
                                    ->icon(Heroicon::BuildingOffice2)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Asignación comercial')
                                            ->schema([
                                                TextEntry::make('businessUnit.code')
                                                    ->label('Código unidad')
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—'),
                                                TextEntry::make('businessUnit.definition')
                                                    ->label('Unidad de negocio')
                                                    ->placeholder('—'),
                                                TextEntry::make('businessLine.code')
                                                    ->label('Código línea')
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('businessLine.definition')
                                                    ->label('Línea de negocio')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Registro')
                            ->icon(Heroicon::Clock)
                            ->schema([
                                Section::make('Auditoría')
                                    ->description('Quién registró el movimiento y cuándo se guardó.')
                                    ->icon(Heroicon::Clock)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Trazabilidad')
                                            ->schema([
                                                TextEntry::make('created_by')
                                                    ->label('Registrado por')
                                                    ->placeholder('—'),
                                                TextEntry::make('created_at')
                                                    ->label('Fecha de registro')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('—'),
                                                TextEntry::make('updated_at')
                                                    ->label('Última actualización')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
