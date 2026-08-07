<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\HelpdeskExportCsvController;
use App\Models\HelpDesk;
use App\Support\ProjectManagement\ToBacklogSession;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;

final class HelpdeskTableConfigurator
{
    /**
     * @param  class-string  $modalActionsClass  Clase con makeAddNoteAction(), IOS_SUCCESS_BTN, etc.
     * @param  class-string  $helpdeskResourceClass  HelpdeskResource del panel (currentUserIsHelpdeskTicketCreator).
     */
    public static function configure(
        Table $table,
        string $exportRouteName,
        string $modalActionsClass,
        string $helpdeskResourceClass,
        bool $includeRecordEditAction = false,
    ): Table {
        return $table
            ->query(function (): Builder {
                return HelpdeskTicketVisibility::constrainVisible(
                    HelpDesk::query()->with(['rrhhColaboradores'])
                )
                    ->orderByRaw(self::statusOrderByCaseSql())
                    ->orderByDesc('updated_at');
            })
            ->columns(self::columns($modalActionsClass))
            ->recordClasses(function (HelpDesk $record): array {
                if (in_array($record->status, HelpdeskTaskStatusOptions::terminalStatuses(), true)) {
                    $classes = [];
                } else {
                    $classes = [self::recordPriorityRowClass($record->priority)];
                }

                $unreadClass = HelpdeskUnreadNoteTracker::recordRowClass($record);
                if ($unreadClass !== '') {
                    $classes[] = $unreadClass;
                }

                if (HelpdeskSla::badgeLabel($record) === 'SLA vencido') {
                    $classes[] = 'fi-helpdesk-ta-sla-breached';
                }

                return $classes;
            })
            ->filters(self::filters())
            ->deferFilters(false)
            ->recordActions(self::recordActions(
                $modalActionsClass,
                $helpdeskResourceClass,
                $includeRecordEditAction,
            ))
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('toBacklog')
                        ->label('Para BackLog')
                        ->icon('heroicon-o-queue-list')
                        ->color('primary')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos un ticket')
                                    ->body('Marca tickets en estatus EN PROCESO para enviarlos al backlog.')
                                    ->send();

                                return;
                            }

                            $result = ToBacklogSession::addFromRecords($records);

                            if ($result['added'] === 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Ningún ticket agregado')
                                    ->body('Solo se pueden agregar tickets en estatus EN PROCESO.')
                                    ->send();

                                return;
                            }

                            $body = $result['added'] === 1
                                ? '1 ticket listo para asociar en el Backlog.'
                                : $result['added'].' tickets listos para asociar en el Backlog.';

                            if ($result['skipped'] > 0) {
                                $body .= ' Se omitieron '.$result['skipped'].' por no estar EN PROCESO.';
                            }

                            Notification::make()
                                ->success()
                                ->title('Tickets preparados para BackLog')
                                ->body($body)
                                ->send();
                        }),
                    BulkAction::make('exportCsvController')
                        ->label('Exportar CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) use ($exportRouteName): void {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos un ticket')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $ids = $records->pluck('id')->all();
                            $token = HelpdeskExportCsvController::storeIdsAndGetToken($ids);

                            redirect()->route($exportRouteName, ['token' => $token]);
                        }),
                ]),
            ]);
    }

    /**
     * @return array<string, Tab>
     */
    public static function tabs(): array
    {
        $tabs = [
            'todos' => Tab::make('Todos'),
        ];

        if (HelpdeskTicketVisibility::canViewGlobalQueue()) {
            $tabs['mios'] = Tab::make('Míos')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    $user = Auth::user();

                    if (! $user instanceof \App\Models\User) {
                        return $query->whereRaw('0 = 1');
                    }

                    return HelpdeskTicketVisibility::constrainToMine($query, $user);
                });

            $tabs['sin_asignar'] = Tab::make('Sin asignar')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => HelpdeskTicketVisibility::constrainUnassigned($query)
                );
        }

        foreach (self::statusTabDefinitions() as $key => [$status, $label]) {
            $tabs[$key] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }

    /**
     * @return array<int, SelectFilter>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Estatus')
                ->options(HelpdeskTaskStatusOptions::all())
                ->multiple()
                ->searchable(),
            SelectFilter::make('priority')
                ->label('Prioridad')
                ->options([
                    'BAJA' => 'Baja',
                    'MEDIA' => 'Media',
                    'ALTA' => 'Alta',
                ])
                ->multiple(),
            SelectFilter::make('ticket_type')
                ->label('Tipo')
                ->options(
                    collect(HelpdeskTicketType::catalog())
                        ->mapWithKeys(static fn (array $item, string $key): array => [
                            $key => $item['title'],
                        ])
                        ->all()
                )
                ->multiple()
                ->searchable(),
            SelectFilter::make('rrhhColaboradores')
                ->label('Asignado')
                ->relationship('rrhhColaboradores', 'fullName')
                ->multiple()
                ->searchable()
                ->preload(),
            SelectFilter::make('created_by')
                ->label('Creado por')
                ->options(fn (): array => HelpDesk::query()
                    ->whereNotNull('created_by')
                    ->where('created_by', '!=', '')
                    ->distinct()
                    ->orderBy('created_by')
                    ->pluck('created_by', 'created_by')
                    ->all())
                ->searchable(),
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function statusTabDefinitions(): array
    {
        $keys = [
            HelpdeskTaskStatusOptions::STATUS_PENDING => 'pendiente_por_iniciar',
            HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS => 'en_proceso',
            HelpdeskTaskStatusOptions::STATUS_IN_ANALYSIS => 'en_analisis',
            HelpdeskTaskStatusOptions::STATUS_PLANNED => 'planificado',
            HelpdeskTaskStatusOptions::STATUS_IN_DEVELOPMENT => 'en_desarrollo',
            HelpdeskTaskStatusOptions::STATUS_QA => 'pruebas_qa',
            HelpdeskTaskStatusOptions::STATUS_WAITING => 'esperando_terceros',
            HelpdeskTaskStatusOptions::STATUS_DONE => 'terminado',
            HelpdeskTaskStatusOptions::STATUS_CANCELLED => 'cancelado',
        ];

        $definitions = [];

        foreach (HelpdeskTaskStatusOptions::all() as $status => $label) {
            $definitions[$keys[$status]] = [$status, $label];
        }

        return $definitions;
    }

    public static function recordPriorityRowClass(?string $priority): string
    {
        $readableText = ' text-gray-950 dark:text-gray-100';

        return match ($priority) {
            'BAJA' => 'fi-helpdesk-ta-priority--baja bg-green-50 dark:bg-green-950/30 border-l-4 border-green-500'.$readableText,
            'MEDIA' => 'fi-helpdesk-ta-priority--media bg-amber-50 dark:bg-amber-950/30 border-l-4 border-amber-500'.$readableText,
            'ALTA' => 'fi-helpdesk-ta-priority--alta bg-red-50 dark:bg-red-950/30 border-l-4 border-red-500'.$readableText,
            default => 'fi-helpdesk-ta-priority--default border-l-4 border-gray-200 dark:border-gray-700',
        };
    }

    public static function statusOrderByCaseSql(): string
    {
        $cases = [];
        $order = 1;

        foreach (self::statusTabDefinitions() as [$status]) {
            $cases[] = "WHEN '".str_replace("'", "''", $status)."' THEN ".$order;
            $order++;
        }

        return 'CASE status
                            '.implode("\n                            ", $cases).'
                            ELSE '.$order.'
                        END';
    }

    /**
     * @param  class-string  $modalActionsClass
     * @return array<int, mixed>
     */
    private static function columns(string $modalActionsClass): array
    {
        $columns = [
            TextColumn::make('id')
                ->label('ID')
                ->icon('heroicon-m-hashtag')
                ->searchable(isIndividual: true)
                ->sortable()
                ->copyable()
                ->weight('semiBold')
                ->color('primary')
                ->toggleable()
                ->action(self::makeViewTimelineAction($modalActionsClass)),
            TextColumn::make('description')
                ->label('Descripción')
                ->icon('heroicon-m-document-text')
                ->formatStateUsing(fn (?string $state): string => HelpdeskPlainText::fromHtml($state))
                ->searchable(isIndividual: true)
                ->limit(40)
                ->extraAttributes(fn (HelpDesk $record): array => filled($description = HelpdeskPlainText::fromHtml($record->description))
                    ? [
                        'x-tooltip' => '{ content: '.Js::from($description).', theme: $store.theme, delay: [1000, 0], maxWidth: 520 }',
                    ]
                    : []),
            HelpdeskTableTicketTypeColumn::make(individualSearch: true),
            TextColumn::make('priority')
                ->label('Prioridad')
                ->icon(fn (?string $state): ?string => match ($state) {
                    'BAJA' => 'heroicon-m-arrow-trending-down',
                    'MEDIA' => 'heroicon-m-minus',
                    'ALTA' => 'heroicon-m-arrow-trending-up',
                    default => null,
                })
                ->iconColor(fn (?string $state): ?string => match ($state) {
                    'BAJA' => 'success',
                    'MEDIA' => 'warning',
                    'ALTA' => 'danger',
                    default => null,
                })
                ->badge()
                ->color(function ($record) {
                    return match ($record->priority) {
                        'BAJA' => 'success',
                        'MEDIA' => 'warning',
                        'ALTA' => 'danger',
                    };
                })
                ->searchable(isIndividual: true),
            TextColumn::make('rrhhColaboradores.fullName')
                ->label('Asignados')
                ->icon('heroicon-m-user')
                ->listWithLineBreaks()
                ->searchable(isIndividual: true),
        ];

        $columns = [
            ...$columns,
            TextColumn::make('created_by')
                ->label('Creado por')
                ->icon('heroicon-m-user-circle')
                ->searchable(isIndividual: true),
            TextColumn::make('status')
                ->label('Estatus')
                ->formatStateUsing(fn (?string $state): string => HelpdeskTaskStatusOptions::all()[$state] ?? (string) $state)
                ->icon(fn (?string $state): ?string => HelpdeskTaskStatusOptions::icon($state))
                ->iconColor(fn (?string $state): ?string => HelpdeskTaskStatusOptions::iconColor($state))
                ->badge()
                ->color(fn (HelpDesk $record): string => HelpdeskTaskStatusOptions::badgeColor($record->status))
                ->searchable(isIndividual: true),
            TextColumn::make('sla_badge')
                ->label('SLA')
                ->state(fn (HelpDesk $record): ?string => HelpdeskSla::badgeLabel($record))
                ->badge()
                ->color(fn (HelpDesk $record): string => HelpdeskSla::badgeColor($record))
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('created_at')
                ->label('Fecha de Creación')
                ->icon('heroicon-m-calendar')
                ->description(fn ($record) => $record->created_at->diffForHumans())
                ->dateTime()
                ->sortable()
                ->searchable(),
            TextColumn::make('updated_at')
                ->label('Fecha de Actualización')
                ->icon('heroicon-m-calendar-days')
                ->description(fn ($record) => $record->updated_at->diffForHumans())
                ->dateTime()
                ->sortable()
                ->searchable(),
        ];

        return $columns;
    }

    /**
     * @param  class-string  $modalActionsClass
     * @param  class-string  $helpdeskResourceClass
     * @return array<int, mixed>
     */
    private static function recordActions(
        string $modalActionsClass,
        string $helpdeskResourceClass,
        bool $includeRecordEditAction,
    ): array {
        $actions = [];

        if ($includeRecordEditAction) {
            $actions[] = EditAction::make()
                ->visible(fn (HelpDesk $record): bool => $helpdeskResourceClass::currentUserIsHelpdeskTicketCreator($record));
        }

        $actions[] = Action::make('previewDocuments')
            ->label('Documentos')
            ->icon('heroicon-m-document-magnifying-glass')
            ->color('info')
            ->visible(fn (HelpDesk $record): bool => count(HelpdeskDocumentPaths::paths($record)) > 0)
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Documentos del ticket')
            ->modalDescription(fn (HelpDesk $record): string => 'Vista previa de archivos · Ticket #'.$record->getKey().' · '.$record->created_by)
            ->modalContent(fn (HelpDesk $record) => view('filament.business.helpdesks.documents-preview-modal', [
                'record' => $record,
                'documents' => HelpdeskDocumentPaths::forPublicDisk($record),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(
                Action::make('dismissDocumentsPreview')
                    ->label('Listo')
                    ->extraAttributes([
                        'class' => $modalActionsClass::IOS_SUCCESS_BTN,
                    ]),
            )
            ->action(fn (): null => null);

        $actions[] = ActionGroup::make([
            $modalActionsClass::makeAddNoteAction(),
            $modalActionsClass::makeUpdateStatusAction(),
            $modalActionsClass::makeUpdatePriorityAction(),
            self::makeViewNotesAction($modalActionsClass),
        ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro');

        return $actions;
    }

    /**
     * @param  class-string  $modalActionsClass
     */
    private static function makeViewNotesAction(string $modalActionsClass): Action
    {
        return Action::make('viewNotes')
            ->label('Ver notas')
            ->icon('heroicon-m-clipboard-document-list')
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Notas del ticket')
            ->modalDescription(fn (HelpDesk $record): string => 'Seguimiento interno · Ticket #'.$record->getKey())
            ->modalContent(function (HelpDesk $record) {
                $updated = $record->updated_at
                    ? Carbon::parse($record->updated_at)->timezone(config('app.timezone'))
                    : now()->timezone(config('app.timezone'));

                $daysElapsed = (int) $updated->copy()->startOfDay()->diffInDays(now()->copy()->startOfDay());

                return view('filament.business.helpdesks.notes-modal', [
                    'record' => $record,
                    'observation' => $record->observation,
                    'updatedAtFormatted' => $updated->format('d/m/Y H:i'),
                    'updatedRelative' => $updated->diffForHumans(),
                    'daysElapsed' => $daysElapsed,
                    'updatedBy' => $record->updated_by,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelAction(
                Action::make('dismissNotes')
                    ->label('Listo')
                    ->extraAttributes([
                        'class' => $modalActionsClass::IOS_SUCCESS_BTN,
                    ]),
            )
            ->action(function (HelpDesk $record): void {
                HelpdeskUnreadNoteTracker::markAsRead($record, Auth::user());
            });
    }

    /**
     * @param  class-string  $modalActionsClass
     */
    private static function makeViewTimelineAction(string $modalActionsClass): Action
    {
        return Action::make('viewTimeline')
            ->label('Bitácora')
            ->icon('heroicon-m-clock')
            ->slideOver()
            ->formWrapper(false)
            ->modalWidth(Width::FiveExtraLarge)
            ->extraModalWindowAttributes([
                'class' => 'fi-helpdesk-timeline-modal-window',
            ])
            ->modalHeading(fn (HelpDesk $record): string => 'Bitácora del ticket #'.$record->getKey())
            ->modalDescription(fn (HelpDesk $record): string => 'Traza completa · '.$record->created_by)
            ->modalContent(fn (HelpDesk $record) => view('filament.business.helpdesks.timeline-modal', [
                'record' => $record,
                'timeline' => HelpdeskTimelineBuilder::fromTicket($record),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cerrar')
                    ->extraAttributes([
                        'class' => $modalActionsClass::IOS_SUCCESS_BTN,
                    ])
            )
            ->action(function (HelpDesk $record): void {
                HelpdeskUnreadNoteTracker::markAsRead($record, Auth::user());
            });
    }
}
