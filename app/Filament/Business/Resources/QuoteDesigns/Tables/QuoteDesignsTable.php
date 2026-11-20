<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\QuoteDesigns\Tables;

use App\Models\QuoteDesign;
use App\Support\Quotes\Design\QuoteDesignPersistence;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class QuoteDesignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->heading('Diseños de cotización')
            ->description('Colores, logo, textos y páginas fijas de la propuesta económica por plan. Un plan sin diseño activo sale con la propuesta de siempre.')
            ->emptyStateHeading('Todavía no hay diseños')
            ->emptyStateDescription('Cree el primero con «Nuevo diseño»: importe el HTML que envió diseño, suba las imágenes y asígnelo a sus planes.')
            ->emptyStateIcon(Heroicon::OutlinedPaintBrush)
            ->striped()
            ->columns([
                ColorColumn::make('theme.primary')
                    ->label('Color')
                    ->tooltip('Color principal'),
                TextColumn::make('name')
                    ->label('Diseño')
                    ->weight('semibold')
                    ->description(fn (QuoteDesign $record): ?string => filled($record->description) ? str((string) $record->description)->limit(80)->toString() : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plans.description')
                    ->label('Planes')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sin planes')
                    ->limitList(3)
                    ->expandableLimitedList(),
                TextColumn::make('pages')
                    ->label('Páginas fijas')
                    ->state(fn (QuoteDesign $record): string => count((array) $record->pages_before).' antes · '.count((array) $record->pages_after).' después')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state): string => $state === QuoteDesign::STATUS_ACTIVE ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('version')
                    ->label('Versión')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Última edición')
                    ->since()
                    ->dateTimeTooltip('d/m/Y h:i A')
                    ->description(fn (QuoteDesign $record): ?string => $record->updated_by)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        QuoteDesign::STATUS_ACTIVE => 'Activo',
                        QuoteDesign::STATUS_INACTIVE => 'Inactivo',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Editar'),
                    Action::make('activate')
                        ->label('Activar')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (QuoteDesign $record): bool => ! $record->isActive())
                        ->requiresConfirmation()
                        ->modalHeading('Activar diseño')
                        ->modalDescription('Las cotizaciones nuevas de sus planes saldrán con este diseño desde ahora. Las propuestas ya emitidas no cambian.')
                        ->action(fn (QuoteDesign $record) => self::changeStatus($record, QuoteDesign::STATUS_ACTIVE)),
                    Action::make('deactivate')
                        ->label('Inactivar')
                        ->icon(Heroicon::OutlinedPauseCircle)
                        ->color('danger')
                        ->visible(fn (QuoteDesign $record): bool => $record->isActive())
                        ->requiresConfirmation()
                        ->modalHeading('Inactivar diseño')
                        ->modalDescription('Las cotizaciones nuevas de sus planes volverán a la propuesta de siempre. Las propuestas ya emitidas con este diseño lo conservan aunque se regeneren.')
                        ->action(fn (QuoteDesign $record) => self::changeStatus($record, QuoteDesign::STATUS_INACTIVE)),
                ]),
            ]);
    }

    private static function changeStatus(QuoteDesign $record, string $status): void
    {
        try {
            QuoteDesignPersistence::setStatus($record, $status);
        } catch (ValidationException $exception) {
            Notification::make()
                ->warning()
                ->title('No se pudo activar')
                ->body(collect($exception->errors())->flatten()->first())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($status === QuoteDesign::STATUS_ACTIVE ? 'Diseño activado' : 'Diseño inactivado')
            ->send();
    }
}
