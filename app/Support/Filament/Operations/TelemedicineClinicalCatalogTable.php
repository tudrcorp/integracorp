<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class TelemedicineClinicalCatalogTable
{
    public static function configure(
        Table $table,
        string $heading,
        string $description,
        DeleteAction $deleteAction,
        string $nameIcon,
        string $emptyHeading,
        string $emptyDescription,
        string $searchPlaceholder,
    ): Table {
        return $table
            ->heading($heading)
            ->description($description)
            ->defaultSort('name')
            ->striped()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->searchPlaceholder($searchPlaceholder)
            ->emptyStateHeading($emptyHeading)
            ->emptyStateDescription($emptyDescription)
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->recordActionsColumnLabel('')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->icon($nameIcon)
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->copyable()
                    ->copyMessage('Nombre copiado')
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('type')
                    ->label('Cobertura')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $state !== null && $state !== '' ? $state : '—')
                    ->color(fn (?string $state): string => match (mb_strtoupper(trim((string) ($state ?? '')))) {
                        'CUBIERTO' => 'success',
                        'NO CUBIERTO' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match (mb_strtoupper(trim((string) ($state ?? '')))) {
                        'CUBIERTO' => 'heroicon-m-check-circle',
                        'NO CUBIERTO' => 'heroicon-m-exclamation-triangle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Model $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Model $record): string => $record->updated_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Cobertura')
                    ->options(TelemedicineClinicalCatalogForm::typeOptions())
                    ->native(false),
            ])
            ->filtersTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('Filtros')
                    ->icon(Heroicon::OutlinedFunnel),
            )
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ]),
                $deleteAction->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                ]),
            ])
            ->toolbarActions([
                // Sin bulk delete: la eliminación exige motivo individual en auditoría.
            ]);
    }
}
