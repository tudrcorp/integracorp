<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\RrhhNominas\RelationManagers;

use App\Models\RrhhDetalleNomina;
use App\Models\RrhhNomina;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DetallesRelationManager extends RelationManager
{
    protected static string $relationship = 'detalleNomina';

    protected static ?string $title = 'Detalle por colaborador';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->schema([
                        TextEntry::make('colaborador_nombre')
                            ->label('Colaborador')
                            ->state(fn (RrhhDetalleNomina $record): string => $record->nombreColaborador()),
                        TextEntry::make('colaborador_cedula')
                            ->label('Cédula')
                            ->state(fn (RrhhDetalleNomina $record): string => $record->cedulaColaborador())
                            ->placeholder('—'),
                        TextEntry::make('departamento_nombre')
                            ->label('Departamento')
                            ->state(fn (RrhhDetalleNomina $record): string => $record->nombreDepartamento())
                            ->placeholder('—'),
                        TextEntry::make('cargo_nombre')
                            ->label('Cargo')
                            ->state(fn (RrhhDetalleNomina $record): string => $record->nombreCargo())
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Sueldo base')
                    ->schema([
                        TextEntry::make('salario')
                            ->label('USD$')
                            ->money('USD'),
                        TextEntry::make('salario_ves')
                            ->label('VES')
                            ->state(fn (RrhhDetalleNomina $record): float => $this->vesAmount($record, 'salario', 'salario_ves'))
                            ->numeric(decimalPlaces: 2),
                    ])
                    ->columns(2),
                Section::make('Asignaciones aplicadas')
                    ->schema([
                        RepeatableEntry::make('detalle_asignaciones')
                            ->label('')
                            ->schema([
                                TextEntry::make('name')->label('Nombre'),
                                TextEntry::make('aplicacion')->label('Aplicación')->badge(),
                                TextEntry::make('tipo_valor')->label('Tipo')->badge(),
                                TextEntry::make('valor_referencia')->label('Referencia'),
                                TextEntry::make('monto_calculado')->label('Monto USD$')->money('USD'),
                            ])
                            ->columns(5)
                            ->placeholder('Sin asignaciones'),
                        TextEntry::make('monto_bono')
                            ->label('Total asignaciones USD$')
                            ->money('USD')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('monto_bono_ves')
                            ->label('Total asignaciones VES')
                            ->state(fn (RrhhDetalleNomina $record): float => $this->vesAmount($record, 'monto_bono', 'monto_bono_ves'))
                            ->numeric(decimalPlaces: 2)
                            ->weight(FontWeight::Bold),
                    ]),
                Section::make('Deducciones aplicadas')
                    ->schema([
                        RepeatableEntry::make('detalle_descuentos')
                            ->label('')
                            ->schema([
                                TextEntry::make('name')->label('Nombre'),
                                TextEntry::make('aplicacion')->label('Aplicación')->badge(),
                                TextEntry::make('tipo_valor')->label('Tipo')->badge(),
                                TextEntry::make('valor_referencia')->label('Referencia'),
                                TextEntry::make('monto_calculado')->label('Monto USD$')->money('USD'),
                            ])
                            ->columns(5)
                            ->placeholder('Sin deducciones'),
                        TextEntry::make('monto_descuento')
                            ->label('Total deducciones USD$')
                            ->money('USD')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('monto_descuento_ves')
                            ->label('Total deducciones VES')
                            ->state(fn (RrhhDetalleNomina $record): float => $this->vesAmount($record, 'monto_descuento', 'monto_descuento_ves'))
                            ->numeric(decimalPlaces: 2)
                            ->weight(FontWeight::Bold),
                    ]),
                Section::make('Préstamos descontados')
                    ->schema([
                        RepeatableEntry::make('detalle_prestamos')
                            ->label('')
                            ->schema([
                                TextEntry::make('descripcion')->label('Descripción'),
                                TextEntry::make('monto_prestamo')->label('Préstamo USD$')->money('USD'),
                                TextEntry::make('interes')->label('% Desc.')->suffix('%'),
                                TextEntry::make('nro_cuotas')->label('Cuotas'),
                                TextEntry::make('monto_cuota')->label('Cuota USD$')->money('USD'),
                            ])
                            ->columns(5)
                            ->placeholder('Sin préstamos activos'),
                        TextEntry::make('monto_prestamo')
                            ->label('Total préstamos USD$')
                            ->money('USD')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('monto_prestamo_ves')
                            ->label('Total préstamos VES')
                            ->state(fn (RrhhDetalleNomina $record): float => $this->vesAmount($record, 'monto_prestamo', 'monto_prestamo_ves'))
                            ->numeric(decimalPlaces: 2)
                            ->weight(FontWeight::Bold),
                    ]),
                Section::make('Neto a pagar')
                    ->schema([
                        TextEntry::make('monto_total')
                            ->label('Neto USD$')
                            ->money('USD')
                            ->weight(FontWeight::Bold)
                            ->color('success'),
                        TextEntry::make('monto_total_ves')
                            ->label('Neto VES')
                            ->state(fn (RrhhDetalleNomina $record): float => $this->vesAmount($record, 'monto_total', 'monto_total_ves'))
                            ->numeric(decimalPlaces: 2)
                            ->weight(FontWeight::Bold)
                            ->color('success'),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'colaborador.departamento',
                'colaborador.cargo',
                'nomina',
            ]))
            ->recordTitleAttribute('colaborador_nombre')
            ->heading('Detalle del cálculo por colaborador')
            ->description('Desglose completo de sueldo, asignaciones, deducciones y préstamos en USD$ y VES.')
            ->emptyStateHeading('Sin detalle de colaboradores')
            ->emptyStateDescription('Este cálculo no tiene líneas de detalle registradas.')
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->striped()
            ->defaultSort('colaborador_id')
            ->paginated([10, 25, 50, 100])
            ->columns([
                TextColumn::make('colaborador_nombre')
                    ->label('Colaborador')
                    ->state(fn (RrhhDetalleNomina $record): string => $record->nombreColaborador())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner
                                ->where('colaborador_nombre', 'like', "%{$search}%")
                                ->orWhereHas('colaborador', fn (Builder $colaborador): Builder => $colaborador
                                    ->where('fullName', 'like', "%{$search}%"));
                        });
                    })
                    ->description(fn (RrhhDetalleNomina $record): string => $record->cedulaColaborador())
                    ->wrap(),
                TextColumn::make('departamento_nombre')
                    ->label('Departamento')
                    ->state(fn (RrhhDetalleNomina $record): string => $record->nombreDepartamento())
                    ->placeholder('—')
                    ->toggleable()
                    ->wrap(),
                TextColumn::make('cargo_nombre')
                    ->label('Cargo')
                    ->state(fn (RrhhDetalleNomina $record): string => $record->nombreCargo())
                    ->placeholder('—')
                    ->toggleable()
                    ->wrap(),
                TextColumn::make('salario')
                    ->label('Sueldo')
                    ->state(fn (RrhhDetalleNomina $record): string => self::usd($record->salario))
                    ->description(fn (RrhhDetalleNomina $record): string => self::ves($this->vesAmount($record, 'salario', 'salario_ves')))
                    ->sortable(),
                TextColumn::make('detalle_asignaciones')
                    ->label('Asignaciones')
                    ->state(fn (RrhhDetalleNomina $record): string => $record->conceptosLabel($record->detalle_asignaciones))
                    ->description(fn (RrhhDetalleNomina $record): string => 'Total: '.self::usd($record->monto_bono).' / '.self::ves($this->vesAmount($record, 'monto_bono', 'monto_bono_ves')))
                    ->wrap()
                    ->color('success'),
                TextColumn::make('detalle_descuentos')
                    ->label('Deducciones')
                    ->state(fn (RrhhDetalleNomina $record): string => $record->conceptosLabel($record->detalle_descuentos))
                    ->description(fn (RrhhDetalleNomina $record): string => 'Total: '.self::usd($record->monto_descuento).' / '.self::ves($this->vesAmount($record, 'monto_descuento', 'monto_descuento_ves')))
                    ->wrap()
                    ->color('danger'),
                TextColumn::make('detalle_prestamos')
                    ->label('Préstamos')
                    ->state(fn (RrhhDetalleNomina $record): string => $record->conceptosLabel($record->detalle_prestamos))
                    ->description(fn (RrhhDetalleNomina $record): string => 'Total: '.self::usd($record->monto_prestamo).' / '.self::ves($this->vesAmount($record, 'monto_prestamo', 'monto_prestamo_ves')))
                    ->wrap()
                    ->color('warning'),
                TextColumn::make('monto_total')
                    ->label('Neto a pagar')
                    ->state(fn (RrhhDetalleNomina $record): string => self::usd($record->monto_total))
                    ->description(fn (RrhhDetalleNomina $record): string => self::ves($this->vesAmount($record, 'monto_total', 'monto_total_ves')))
                    ->sortable()
                    ->weight(FontWeight::Bold),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make()
                    ->label('Ver desglose')
                    ->icon('heroicon-m-eye')
                    ->modalHeading(fn (RrhhDetalleNomina $record): string => 'Desglose · '.$record->nombreColaborador())
                    ->modalWidth('7xl')
                    ->extraAttributes([
                        'class' => 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]',
                    ], merge: true),
            ]);
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var RrhhNomina $ownerRecord */
        $count = $ownerRecord->detalleNomina()->count();

        return $count > 0 ? (string) $count : null;
    }

    private function vesAmount(RrhhDetalleNomina $record, string $usdAttribute, string $vesAttribute): float
    {
        /** @var RrhhNomina $nomina */
        $nomina = $this->getOwnerRecord();

        return $record->montoVes($usdAttribute, $vesAttribute, (float) ($nomina->tasa_bcv ?? 0));
    }

    private static function usd(mixed $amount): string
    {
        return 'USD$ '.number_format((float) $amount, 2, '.', ',');
    }

    private static function ves(mixed $amount): string
    {
        return 'VES '.number_format((float) $amount, 2, '.', ',');
    }
}
