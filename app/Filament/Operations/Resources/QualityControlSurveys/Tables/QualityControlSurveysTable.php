<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys\Tables;

use App\Enums\QualityControlRating;
use App\Enums\QualityControlService;
use App\Filament\Operations\Resources\QualityControlSurveys\QualityControlSurveyResource;
use App\Models\QualityControlSurvey;
use App\Support\Operations\QualityControl\QualityControlSurveyWriter;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class QualityControlSurveysTable
{
    /**
     * @param  class-string<QualityControlSurveyResource>  $resource
     */
    public static function configure(Table $table, string $resource): Table
    {
        return $table
            ->heading('Encuestas de Quality Control')
            ->description('Una fila por respuesta del paciente, de la más reciente a la más antigua. En verde las calificaciones satisfactorias, en ámbar las neutras y en rojo las insatisfechas.')
            ->defaultSort('responded_at', 'desc')
            ->searchPlaceholder('Buscar por paciente o cédula')
            ->searchDebounce('350ms')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->emptyStateHeading('Aún no hay encuestas')
            ->emptyStateDescription('Registre una encuesta con «Nueva encuesta» o cargue el Excel de respuestas con «Importar Excel».')
            ->columns([
                TextColumn::make('responded_at')
                    ->label('Respuesta')
                    ->dateTime('d/m/Y')
                    ->description(fn (QualityControlSurvey $record): string => $record->responded_at?->format('h:i A') ?? '')
                    ->sortable(),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (QualityControlSurvey $record): string => collect([
                        'C.I. '.$record->patient_document,
                        $record->affiliate_id !== null ? 'Enlazado al afiliado' : null,
                    ])->filter()->implode(' · '))
                    ->searchable(['patient_name', 'patient_document'])
                    ->sortable(),
                TextColumn::make('service_date')
                    ->label('Fecha de servicio')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('services')
                    ->label('Servicio recibido')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => QualityControlService::tryFrom($state)?->label() ?? $state)
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList(),
                ...array_map(
                    fn (string $field, string $label): TextColumn => TextColumn::make($field)
                        ->label($label)
                        ->badge()
                        ->formatStateUsing(fn (?QualityControlRating $state): string => $state?->label() ?? '—')
                        ->color(fn (?QualityControlRating $state): string => $state?->color() ?? 'gray')
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: $field !== 'rating_overall'),
                    array_keys(QualityControlSurvey::RATING_FIELDS),
                    ['Canales de atención', 'Tiempo de respuesta', 'Servicio médico', 'Satisfacción general'],
                ),
                TextColumn::make('observations')
                    ->label('Observación')
                    ->limit(60)
                    ->tooltip(fn (QualityControlSurvey $record): ?string => mb_strlen((string) $record->observations) > 60 ? $record->observations : null)
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('contact_phone')
                    ->label('Contacto')
                    ->icon(Heroicon::OutlinedPhone)
                    ->iconColor('gray')
                    ->placeholder('Sin teléfono')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === QualityControlSurvey::SOURCE_IMPORT ? 'Excel' : 'Formulario')
                    ->color(fn (?string $state): string => $state === QualityControlSurvey::SOURCE_IMPORT ? 'gray' : 'primary')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('responded_at')
                    ->label('Fecha de respuesta')
                    ->schema([
                        DatePicker::make('desde')->label('Respuesta desde')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')->label('Respuesta hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::dateRange($query, 'responded_at', $data))
                    ->indicateUsing(fn (array $data): array => self::dateIndicators('Respuesta', $data)),
                Filter::make('service_date')
                    ->label('Fecha de servicio')
                    ->schema([
                        DatePicker::make('desde')->label('Servicio desde')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')->label('Servicio hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::dateRange($query, 'service_date', $data))
                    ->indicateUsing(fn (array $data): array => self::dateIndicators('Servicio', $data)),
                SelectFilter::make('services')
                    ->label('Servicio recibido')
                    ->options(QualityControlService::options())
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['values'] ?? null)
                        ? $query
                        : $query->where(function (Builder $query) use ($data): void {
                            foreach ($data['values'] as $service) {
                                $query->orWhereJsonContains('services', $service);
                            }
                        })),
                SelectFilter::make('rating_overall')
                    ->label('Satisfacción general')
                    ->options(QualityControlRating::options())
                    ->multiple(),
                Filter::make('low_rating')
                    ->label('Con alguna calificación neutra o insatisfecha')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                        foreach (array_keys(QualityControlSurvey::RATING_FIELDS) as $field) {
                            $query->orWhereIn($field, [QualityControlRating::Neutral->value, QualityControlRating::Dissatisfied->value]);
                        }
                    })),
                TernaryFilter::make('linked')
                    ->label('Enlace con el afiliado')
                    ->placeholder('Todas')
                    ->trueLabel('Enlazadas a un afiliado')
                    ->falseLabel('Sin afiliado en el sistema')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('affiliate_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('affiliate_id'),
                    ),
                SelectFilter::make('source')
                    ->label('Origen')
                    ->options([
                        QualityControlSurvey::SOURCE_FORM => 'Formulario',
                        QualityControlSurvey::SOURCE_IMPORT => 'Importada desde Excel',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Ver encuesta'),
                    EditAction::make()->label('Editar'),
                    self::deleteAction($resource),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::deleteBulkAction($resource),
                ]),
            ]);
    }

    /**
     * @param  class-string<QualityControlSurveyResource>  $resource
     */
    private static function deleteAction(string $resource): Action
    {
        return Action::make('deleteSurvey')
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (QualityControlSurvey $record): bool => $resource::canDelete($record))
            ->requiresConfirmation()
            ->modalHeading('Eliminar encuesta')
            ->modalDescription(fn (QualityControlSurvey $record): string => 'Se eliminará la encuesta de '.$record->patient_name.' del '.$record->responded_at?->format('d/m/Y').'. Dejará de contar en los resultados y no volverá a entrar aunque se reimporte el mismo Excel. Queda registro en la auditoría.')
            ->modalSubmitActionLabel('Sí, eliminar')
            ->action(function (QualityControlSurvey $record) use ($resource): void {
                abort_unless($resource::canDelete($record), 403);

                QualityControlSurveyWriter::delete($record);

                Notification::make()->title('Encuesta eliminada')->success()->send();
            });
    }

    /**
     * @param  class-string<QualityControlSurveyResource>  $resource
     */
    private static function deleteBulkAction(string $resource): BulkAction
    {
        return BulkAction::make('deleteSurveys')
            ->label('Eliminar seleccionadas')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $resource::canDeleteAny())
            ->requiresConfirmation()
            ->modalHeading('Eliminar encuestas seleccionadas')
            ->modalDescription('Las encuestas dejarán de contar en los resultados y no volverán a entrar aunque se reimporte el mismo Excel. Queda registro en la auditoría.')
            ->modalSubmitActionLabel('Sí, eliminar')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($resource): void {
                abort_unless($resource::canDeleteAny(), 403);

                $deleted = DB::transaction(function () use ($records, $resource): int {
                    $count = 0;

                    foreach ($records as $record) {
                        if ($record instanceof QualityControlSurvey && $resource::canDelete($record)) {
                            QualityControlSurveyWriter::delete($record);
                            $count++;
                        }
                    }

                    return $count;
                });

                Notification::make()
                    ->title($deleted === 1 ? 'Se eliminó 1 encuesta' : 'Se eliminaron '.$deleted.' encuestas')
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function dateRange(Builder $query, string $column, array $data): Builder
    {
        return $query
            ->when($data['desde'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '>=', $date))
            ->when($data['hasta'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '<=', $date));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function dateIndicators(string $label, array $data): array
    {
        $indicators = [];

        if ($data['desde'] ?? null) {
            $indicators['desde'] = $label.' desde '.Carbon::parse($data['desde'])->format('d/m/Y');
        }

        if ($data['hasta'] ?? null) {
            $indicators['hasta'] = $label.' hasta '.Carbon::parse($data['hasta'])->format('d/m/Y');
        }

        return $indicators;
    }
}
