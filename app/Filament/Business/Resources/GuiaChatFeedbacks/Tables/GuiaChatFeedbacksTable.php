<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\GuiaChatFeedbacks\Tables;

use App\Filament\Business\Resources\GuiaChatFeedbacks\GuiaChatFeedbackResource;
use App\Models\GuiaChatFeedback;
use App\Support\GuiaChat\GuiaChatFeedbackType;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuiaChatFeedbacksTable
{
    public static function countByType(?GuiaChatFeedbackType $type = null): int
    {
        return GuiaChatFeedback::query()
            ->when(
                $type !== null,
                fn (Builder $query): Builder => $query->where('type', $type->value),
            )
            ->count();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('GUIA-CHAT · Sugerencias y reportes')
            ->description('Entradas enviadas desde el chat público: sugerencias de mejora y reportes de fallas.')
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->deferFilters(false)
            ->searchPlaceholder('Buscar por mensaje o nombre del reportante…')
            ->recordUrl(fn (GuiaChatFeedback $record): string => GuiaChatFeedbackResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Sin registros de GUIA-CHAT')
            ->emptyStateDescription('Cuando un usuario envíe una sugerencia o reporte desde el chat público, aparecerá aquí.')
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight)
            ->columns([
                ColumnGroup::make('Registro', [
                    TextColumn::make('created_at')
                        ->label('Fecha')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (GuiaChatFeedback $record): ?string => $record->created_at?->diffForHumans())
                        ->sortable(),
                    TextColumn::make('type')
                        ->label('Tipo')
                        ->formatStateUsing(fn (string $state): string => GuiaChatFeedbackType::tryFromString($state)?->label() ?? $state)
                        ->icon(fn (string $state): Heroicon => self::typeIcon($state))
                        ->badge()
                        ->color(fn (string $state): string => GuiaChatFeedbackType::tryFromString($state)?->filamentColor() ?? 'gray')
                        ->sortable(),
                ]),
                ColumnGroup::make('Contenido', [
                    TextColumn::make('reporter_full_name')
                        ->label('Reportado por')
                        ->icon(Heroicon::OutlinedUser)
                        ->state(fn (GuiaChatFeedback $record): ?string => self::reporterLabel($record))
                        ->badge(fn (GuiaChatFeedback $record): bool => $record->requiresReporterName())
                        ->color(fn (GuiaChatFeedback $record): string => match (true) {
                            ! $record->requiresReporterName() => 'gray',
                            filled($record->reporterFullName()) => 'primary',
                            default => 'warning',
                        })
                        ->placeholder('—')
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            return $query->where(function (Builder $builder) use ($search): void {
                                $builder
                                    ->where('reporter_first_name', 'like', "%{$search}%")
                                    ->orWhere('reporter_last_name', 'like', "%{$search}%");
                            });
                        }),
                    TextColumn::make('message')
                        ->label('Detalle')
                        ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                        ->limit(70)
                        ->wrap()
                        ->tooltip(fn (GuiaChatFeedback $record): string => $record->message)
                        ->searchable()
                        ->weight('medium'),
                ]),
                ColumnGroup::make('Técnico', [
                    TextColumn::make('public_token')
                        ->label('Sesión')
                        ->icon(Heroicon::OutlinedFingerPrint)
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->copyable()
                        ->copyMessage('Token copiado')
                        ->placeholder('—'),
                    TextColumn::make('ip_address')
                        ->label('IP')
                        ->icon(Heroicon::OutlinedGlobeAlt)
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->placeholder('—'),
                ]),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(GuiaChatFeedbackType::options())
                    ->native(false),
                Filter::make('created_at')
                    ->label('Rango de fecha')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['desde'] ?? null)) {
                            $indicators['desde'] = 'Desde '.Carbon::parse($data['desde'])->format('d/m/Y');
                        }

                        if (filled($data['hasta'] ?? null)) {
                            $indicators['hasta'] = 'Hasta '.Carbon::parse($data['hasta'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle')
                    ->icon(Heroicon::OutlinedEye),
            ]);
    }

    private static function reporterLabel(GuiaChatFeedback $record): ?string
    {
        if (! $record->requiresReporterName()) {
            return null;
        }

        return $record->reporterFullName() ?? 'Sin nombre';
    }

    private static function typeIcon(string $state): Heroicon
    {
        return match (GuiaChatFeedbackType::tryFromString($state)) {
            GuiaChatFeedbackType::ServiceSuggestion => Heroicon::OutlinedLightBulb,
            GuiaChatFeedbackType::GuiaChatBug => Heroicon::OutlinedBugAnt,
            GuiaChatFeedbackType::IntegracorpBug => Heroicon::OutlinedExclamationTriangle,
            default => Heroicon::OutlinedChatBubbleLeftRight,
        };
    }
}
