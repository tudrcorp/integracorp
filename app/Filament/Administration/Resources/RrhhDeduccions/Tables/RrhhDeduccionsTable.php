<?php

namespace App\Filament\Administration\Resources\RrhhDeduccions\Tables;

use App\Models\RrhhDeduccion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RrhhDeduccionsTable
{
    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Deducciones RRHH')
            ->description('Gestión de deducciones monetarias por departamento o colaborador.')
            ->emptyStateHeading('No hay deducciones registradas')
            ->emptyStateDescription('Crea una deducción para controlar descuentos por departamento o colaborador.')
            ->emptyStateIcon('heroicon-o-minus-circle')
            ->modifyQueryUsing(fn ($query) => $query->with(['departamento', 'colaborador', 'cargo']))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('tipo_valor')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'porcentaje' => 'warning',
                        'monto' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'porcentaje' => 'Porcentaje',
                        'monto' => 'Monto fijo',
                        default => (string) ($state ?? '—'),
                    }),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->state(fn (RrhhDeduccion $record): string => $record->valorLabel())
                    ->color('warning'),
                TextColumn::make('aplicacion')
                    ->label('Aplicación')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'departamento' => 'info',
                        'colaborador' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'departamento' => 'Departamento',
                        'colaborador' => 'Colaborador',
                        default => (string) ($state ?? '—'),
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        'departamento' => 'heroicon-o-building-office-2',
                        'colaborador' => 'heroicon-o-user',
                        default => 'heroicon-o-clipboard-document',
                    }),
                TextColumn::make('destino')
                    ->label('Destino')
                    ->state(fn (RrhhDeduccion $record): string => $record->destinoLabel())
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($query) use ($search): void {
                            $query->whereHas('departamento', fn ($q) => $q->where('description', 'like', "%{$search}%"))
                                ->orWhereHas('colaborador', fn ($q) => $q->where('fullName', 'like', "%{$search}%"))
                                ->orWhereHas('cargo', fn ($q) => $q->where('description', 'like', "%{$search}%"));
                        });
                    })
                    ->badge()
                    ->icon('heroicon-o-clipboard-document'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square')
                    ->color('primary')
                    ->extraAttributes([
                        'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                    ], merge: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->extraAttributes([
                            'class' => self::IOS_DANGER_BUTTON_CLASS,
                        ], merge: true),
                ]),
            ])
            ->striped();
    }
}
