<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\DataChangeAudits\Tables;

use App\Models\DataChangeAudit;
use App\Models\User;
use App\Support\Audit\DataChangeAuditPresenter;
use App\Support\Audit\DataChangeAuditRegistry;
use App\Support\Audit\DataChangeReverter;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

/**
 * Tabla de la auditoría de cambios: liviana (solo las columnas del índice, paginada) y con
 * los filtros que sirven para investigar: código, usuario, entidad, tipo de cambio, campo,
 * panel, operación y «solo lo que afecta cálculos».
 */
final class DataChangeAuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->searchPlaceholder('Código, nombre, usuario u operación')
            ->recordAction('detail')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y h:i:s A')
                    ->sortable(),
                TextColumn::make('user_name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->description(fn (DataChangeAudit $record): string => \Illuminate\Support\Str::limit(DataChangeAuditPresenter::origin($record), 70))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('entity_label')
                    ->label('Registro')
                    ->formatStateUsing(fn (DataChangeAudit $record): string => ($record->entity_label ?: '#'.$record->entity_id))
                    ->description(fn (DataChangeAudit $record): string => $record->entityLabel().($record->parent_label ? ' · '.$record->parent_label : '').' · #'.$record->entity_id)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->where('entity_label', 'like', "%{$search}%")
                        ->orWhere('parent_label', 'like', "%{$search}%")
                        ->orWhere('batch_id', $search)))
                    ->wrap(),
                TextColumn::make('event')
                    ->label('Cambio')
                    ->badge()
                    ->formatStateUsing(fn (DataChangeAudit $record): string => $record->eventLabel())
                    ->color(fn (string $state): string => match ($state) {
                        DataChangeAudit::EVENT_CREATED => 'success',
                        DataChangeAudit::EVENT_DELETED => 'danger',
                        DataChangeAudit::EVENT_REVERTED => 'warning',
                        DataChangeAudit::EVENT_RESTORED => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('fields')
                    ->label('Campos')
                    ->formatStateUsing(fn (DataChangeAudit $record): string => DataChangeAuditPresenter::fieldsSummary((array) $record->changes))
                    ->description(fn (DataChangeAudit $record): ?string => $record->reverted_by_audit_id !== null ? 'Revertido después (#'.$record->reverted_by_audit_id.')' : null)
                    ->wrap(),
                IconColumn::make('affects_calculations')
                    ->label('Afecta cálculos')
                    ->boolean()
                    ->trueIcon('heroicon-s-exclamation-triangle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('from')->label('Desde')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->where('created_at', '<=', Carbon::parse($date)->endOfDay())))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        'from' => ($data['from'] ?? null) ? 'Desde '.Carbon::parse($data['from'])->format('d/m/Y') : null,
                        'until' => ($data['until'] ?? null) ? 'Hasta '.Carbon::parse($data['until'])->format('d/m/Y') : null,
                    ])),
                SelectFilter::make('entity')
                    ->label('Entidad')
                    ->options(DataChangeAuditRegistry::entityOptions())
                    ->multiple(),
                SelectFilter::make('event')
                    ->label('Tipo de cambio')
                    ->options(DataChangeAudit::EVENT_LABELS)
                    ->multiple(),
                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orderBy('name')
                        ->limit(30)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => User::query()->whereKey($value)->value('name')),
                TernaryFilter::make('affects_calculations')
                    ->label('Afecta cálculos')
                    ->trueLabel('Solo lo que afecta cálculos')
                    ->falseLabel('Solo lo que no afecta cálculos')
                    ->placeholder('Todos'),
                Filter::make('field')
                    ->label('Campo')
                    ->schema([
                        TextInput::make('value')
                            ->label('Campo cambiado')
                            ->placeholder('Ej.: agent_id, code_agency, status, plan_id')
                            ->helperText('El nombre técnico del campo; en el detalle se ve con su nombre legible.'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereRaw('FIND_IN_SET(?, fields) > 0', [trim((string) $data['value'])]),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null) ? 'Campo: '.$data['value'] : null),
                SelectFilter::make('source')
                    ->label('Origen')
                    ->options(['web' => 'Pantallas (usuarios)', 'cola' => 'Procesos en cola', 'consola' => 'Comandos del sistema']),
                SelectFilter::make('panel')
                    ->label('Panel')
                    ->options([
                        'business' => 'Negocios',
                        'administration' => 'Administración',
                        'operations' => 'Operaciones',
                        'admin' => 'Admin',
                        'agents' => 'Agentes',
                        'master' => 'Agencia master',
                        'general' => 'Agencia general',
                    ]),
                Filter::make('record')
                    ->label('Registro')
                    ->schema([
                        Select::make('entity')->label('Entidad')->options(DataChangeAuditRegistry::entityOptions()),
                        TextInput::make('id')->label('Id del registro')->numeric(),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['entity'] ?? null) && filled($data['id'] ?? null), fn (Builder $query): Builder => $query
                            ->where('entity', $data['entity'])
                            ->where('entity_id', (int) $data['id'])))
                    ->indicateUsing(fn (array $data): ?string => filled($data['entity'] ?? null) && filled($data['id'] ?? null)
                        ? 'Historia de '.DataChangeAuditRegistry::entityLabel((string) $data['entity']).' #'.$data['id']
                        : null),
                Filter::make('batch')
                    ->label('Operación')
                    ->schema([TextInput::make('id')->label('Operación (id)')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(filled($data['id'] ?? null), fn (Builder $query): Builder => $query->where('batch_id', trim((string) $data['id']))))
                    ->indicateUsing(fn (array $data): ?string => filled($data['id'] ?? null) ? 'Misma operación' : null),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                Action::make('detail')
                    ->label('Ver detalle')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalHeading(fn (DataChangeAudit $record): string => $record->eventLabel().' · '.$record->entityLabel().' '.($record->entity_label ?: '#'.$record->entity_id))
                    ->modalDescription(fn (DataChangeAudit $record): string => 'Movimiento #'.$record->getKey().' · '.$record->created_at?->format('d/m/Y h:i:s A'))
                    ->modalContent(fn (DataChangeAudit $record) => view('filament.administration.data-change-audits.detail', ['audit' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('revert')
                    ->label('Revertir')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (DataChangeAudit $record): bool => self::canRevert()
                        && in_array($record->event, [DataChangeAudit::EVENT_UPDATED, DataChangeAudit::EVENT_DELETED, DataChangeAudit::EVENT_REVERTED], true)
                        && $record->reverted_by_audit_id === null)
                    ->modalHeading(fn (DataChangeAudit $record): string => 'Revertir el movimiento #'.$record->getKey())
                    ->modalDescription('Los campos vuelven a su valor anterior (o el registro se restaura). La reversión queda registrada con su razón y se puede revertir a su vez.')
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalContent(fn (DataChangeAudit $record) => view('filament.administration.data-change-audits.revert-preview', ['preview' => DataChangeReverter::preview($record)]))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Razón de la reversión')
                            ->helperText('Qué salió mal y por qué se revierte. Queda en la auditoría.')
                            ->required()
                            ->minLength(DataChangeReverter::MIN_REASON_LENGTH)
                            ->maxLength(2000)
                            ->rows(3),
                    ])
                    ->modalSubmitActionLabel('Revertir el cambio')
                    ->action(function (DataChangeAudit $record, array $data, Action $action): void {
                        $user = Auth::user();

                        if (! $user instanceof User || ! self::canRevert()) {
                            Notification::make()->title('Sin permiso')->body('No tiene permiso para revertir cambios.')->danger()->send();

                            return;
                        }

                        try {
                            $revert = DataChangeReverter::revert($record, (string) ($data['reason'] ?? ''), $user);
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title('No se revirtió')->body($exception->getMessage())->warning()->persistent()->send();
                            $action->halt();

                            return;
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->title('No se revirtió')->body('Ocurrió un error y no se cambió nada. Intente nuevamente.')->danger()->send();
                            $action->halt();

                            return;
                        }

                        Notification::make()
                            ->title('Cambio revertido')
                            ->body('Quedó registrado como movimiento #'.$revert->getKey().'. Revise los documentos relacionados que se indicaron.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function canRevert(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REVERT_DATA_CHANGES);
    }
}
