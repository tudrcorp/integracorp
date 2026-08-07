<?php

namespace App\Filament\Shared\Helpdesks\Actions;

use App\Models\HelpDesk;
use App\Models\HelpDeskCsat;
use App\Models\RrhhColaborador;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\HelpdeskEventRecorder;
use App\Support\HelpdeskObservationAppender;
use App\Support\HelpdeskSla;
use App\Support\HelpdeskStatusChangeNote;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\HelpdeskTicketIdentity;
use App\Support\HelpdeskTicketVisibility;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class HelpdeskTicketModalActions
{
    public const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    public const IOS_SUCCESS_BTN = 'aviso-btn-ios-success shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public const IOS_GRAY_BTN = 'ticket-btn-ios-gray shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function currentUserIsTicketAssignee(HelpDesk $record): bool
    {
        $colaborador = RrhhColaborador::query()
            ->where('user_id', Auth::id())
            ->first();

        if ($colaborador === null) {
            return false;
        }

        $record->loadMissing('rrhhColaboradores');

        return $record->rrhhColaboradores->contains(
            fn (RrhhColaborador $c): bool => (int) $c->getKey() === (int) $colaborador->getKey()
        );
    }

    /**
     * Quien está asignado al ticket solo puede añadir notas cuando el estado es «EN PROCESO».
     * El creador y demás usuarios no asignados no quedan sujetos a esta restricción (salvo ticket cerrado).
     */
    public static function shouldHideAddNoteAction(HelpDesk $record): bool
    {
        if (in_array($record->status, ['TERMINADO', 'CANCELADO'], true)) {
            return true;
        }

        if (self::currentUserIsTicketAssignee($record)) {
            return $record->status !== 'EN PROCESO';
        }

        return false;
    }

    /**
     * @return bool true si puede continuar; false si se mostró aviso y debe abortarse el guardado
     */
    public static function assertMayAddNote(HelpDesk $record): bool
    {
        if (self::currentUserIsTicketAssignee($record) && $record->status !== 'EN PROCESO') {
            Notification::make()
                ->title('Estado requerido')
                ->body('Actualiza el ticket a «En proceso» para poder añadir notas.')
                ->warning()
                ->send();

            return false;
        }

        return true;
    }

    public static function makeAddNoteAction(string $panel = 'business'): Action
    {
        return Action::make('addNote')
            ->label('Añadir nota')
            ->icon('heroicon-m-plus-circle')
            ->color('success')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Añadir nota al ticket')
            ->modalDescription(fn (HelpDesk $record): string => 'Seguimiento interno · Ticket #'.$record->getKey().' · '.$record->created_by)
            ->modalSubmitActionLabel('Guardar nota')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => self::IOS_SUCCESS_BTN,
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cancelar')
                    ->extraAttributes([
                        'class' => self::IOS_GRAY_BTN,
                    ])
            )
            ->form([
                Section::make('Nueva entrada')
                    ->description('Formato enriquecido: negritas, resaltado, colores, títulos y listas. Los emojis se pueden insertar con el teclado (p. ej. en macOS: Ctrl+Cmd+Espacio). El contenido se añade al historial con fecha y tu nombre.')
                    ->icon('heroicon-m-pencil-square')
                    ->schema([
                        RichEditor::make('note')
                            ->label('Nota')
                            ->placeholder('Describe el avance, acuerdos o el siguiente paso…')
                            ->required()
                            ->fileAttachments(false)
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'highlight', 'textColor'],
                                ['h1', 'h2', 'h3'],
                                ['alignStart', 'alignCenter', 'alignEnd'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['link'],
                                ['undo', 'redo'],
                            ])
                            ->extraInputAttributes([
                                'class' => 'min-h-[12rem]',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),
            ])
            ->successNotification(null)
            ->action(function (HelpDesk $record, array $data): void {
                $user = Auth::user();
                if ($user === null) {
                    return;
                }

                if (! self::assertMayAddNote($record)) {
                    return;
                }

                $noteHtml = (string) ($data['note'] ?? '');
                $plainLength = mb_strlen(trim(html_entity_decode(strip_tags($noteHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($plainLength < 3) {
                    SecurityAudit::log('AUDIT_HELPDESK_NOTE_ADD_FAILED', $panel.'.helpdesks.add-note', [
                        'panel' => $panel,
                        'helpdesk_id' => $record->getKey(),
                        'reason' => 'note_too_short',
                    ]);

                    Notification::make()
                        ->title('Nota demasiado corta')
                        ->body('Escribe al menos 3 caracteres de contenido (sin contar solo formato vacío).')
                        ->warning()
                        ->send();

                    return;
                }

                HelpdeskObservationAppender::append($record, $noteHtml, $user->name, user: $user);

                $isCreatorUpdating = HelpdeskTicketIdentity::isCreator($record, $user);
                $whatsAppReport = [
                    'attempted' => 0,
                    'dispatched' => 0,
                    'failed' => 0,
                    'skipped_no_phone' => 0,
                    'failures' => [],
                    'recipient' => null,
                ];

                if (! $isCreatorUpdating) {
                    $whatsAppReport = HelpdeskTicketAssigneeWhatsAppService::dispatchToTicketCreatorWithReport(
                        ticket: $record,
                        requestedByUserId: Auth::id(),
                        panel: $panel,
                        body: HelpdeskTicketAssigneeWhatsAppService::buildNoteAddedBody($record, $user->name, $noteHtml),
                        source: 'helpdesk.ticket.note-added.creator-followup',
                        auditRoute: $panel.'.helpdesks.notifications.whatsapp.note',
                    );
                }

                SecurityAudit::log('AUDIT_HELPDESK_NOTE_ADDED', $panel.'.helpdesks.add-note', [
                    'panel' => $panel,
                    'helpdesk_id' => $record->getKey(),
                    'status' => $record->status,
                    'added_by' => $user->name,
                    'notify_target' => $isCreatorUpdating ? 'none_updater_is_creator' : 'ticket_creator',
                    'whatsapp_dispatched_count' => $whatsAppReport['dispatched'],
                    'whatsapp_failed_count' => $whatsAppReport['failed'],
                    'whatsapp_skipped_no_phone_count' => $whatsAppReport['skipped_no_phone'],
                    'whatsapp_failures' => array_slice($whatsAppReport['failures'], 0, 10),
                ]);

                Notification::make()
                    ->title('Nota guardada')
                    ->body('Se añadió la nota al ticket #'.$record->getKey().'.')
                    ->success()
                    ->send();
            })
            ->hidden(fn (HelpDesk $record): bool => self::shouldHideAddNoteAction($record));
    }

    public static function makeUpdateStatusAction(string $panel = 'business'): Action
    {
        return Action::make('updateStatus')
            ->label('Actualizar estado')
            ->icon('heroicon-m-arrow-path')
            ->color('warning')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Actualizar estado del ticket')
            ->modalDescription(fn (HelpDesk $record): string => 'Ticket #'.$record->getKey().' · '.$record->created_by)
            ->modalSubmitActionLabel('Guardar estado')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => self::IOS_SUCCESS_BTN,
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cancelar')
                    ->extraAttributes([
                        'class' => self::IOS_GRAY_BTN,
                    ])
            )
            ->fillForm(fn (HelpDesk $record): array => [
                'status' => $record->status,
                'cancellation_reason' => $record->cancellation_reason,
                'csat_score' => $record->csat?->score,
                'csat_comment' => $record->csat?->comment,
            ])
            ->form(function (HelpDesk $record): array {
                $isAssignee = self::currentUserIsTicketAssignee($record);
                $isCreator = HelpdeskTicketIdentity::isCreator($record, Auth::user());

                $components = [
                    Section::make('Estado')
                        ->description(fn (HelpDesk $record): string => HelpdeskTaskStatusOptions::statusModalDescription(
                            $record,
                            Auth::user()?->name,
                            $isAssignee
                        ))
                        ->icon('heroicon-m-flag')
                        ->schema([
                            Select::make('status')
                                ->label('Estado')
                                ->prefixIcon('heroicon-m-flag')
                                ->options(fn (HelpDesk $record): array => HelpdeskTaskStatusOptions::forSelect(
                                    $record,
                                    Auth::user()?->name,
                                    $isAssignee
                                ))
                                ->required()
                                ->live()
                                ->native(true)
                                ->extraInputAttributes([
                                    'class' => 'helpdesk-status-native-select w-full max-w-full min-h-11 text-base sm:text-sm',
                                ]),
                        ])
                        ->columns(1)
                        ->columnSpanFull()
                        ->extraAttributes([
                            'class' => self::IOS_SECTION_CLASS,
                        ]),
                ];

                if ($isAssignee) {
                    $components[] = Section::make('Explicación del cambio')
                        ->description('Documente las razones o el contexto del nuevo estado. La nota quedará en el historial del ticket.')
                        ->icon('heroicon-m-chat-bubble-left-right')
                        ->schema(HelpdeskStatusChangeNote::assigneeExplanationEditor())
                        ->columns(1)
                        ->columnSpanFull()
                        ->extraAttributes([
                            'class' => self::IOS_SECTION_CLASS,
                        ]);
                }

                $components[] = Section::make('Motivo de cancelación')
                    ->description('Obligatorio al cancelar el ticket.')
                    ->icon('heroicon-m-x-circle')
                    ->visible(fn (Get $get): bool => $get('status') === HelpdeskTaskStatusOptions::STATUS_CANCELLED)
                    ->schema([
                        Select::make('cancellation_reason')
                            ->label('Motivo')
                            ->options([
                                'duplicado' => 'Ticket duplicado',
                                'no_aplica' => 'Ya no aplica / resuelto por otro canal',
                                'datos_incorrectos' => 'Datos incorrectos o incompletos',
                                'solicitante_desiste' => 'El solicitante desistió',
                                'otro' => 'Otro',
                            ])
                            ->required(fn (Get $get): bool => $get('status') === HelpdeskTaskStatusOptions::STATUS_CANCELLED)
                            ->native(false),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]);

                if ($isCreator) {
                    $components[] = Section::make('Satisfacción (CSAT)')
                        ->description('Al cerrar como Terminado, califique la atención recibida.')
                        ->icon('heroicon-m-face-smile')
                        ->visible(fn (Get $get): bool => $get('status') === HelpdeskTaskStatusOptions::STATUS_DONE)
                        ->schema([
                            Select::make('csat_score')
                                ->label('Calificación')
                                ->options([
                                    5 => '5 — Excelente',
                                    4 => '4 — Buena',
                                    3 => '3 — Aceptable',
                                    2 => '2 — Regular',
                                    1 => '1 — Mala',
                                ])
                                ->required(fn (Get $get): bool => $get('status') === HelpdeskTaskStatusOptions::STATUS_DONE)
                                ->native(false),
                            Textarea::make('csat_comment')
                                ->label('Comentario (opcional)')
                                ->rows(3)
                                ->maxLength(1000),
                        ])
                        ->columns(1)
                        ->columnSpanFull()
                        ->extraAttributes([
                            'class' => self::IOS_SECTION_CLASS,
                        ]);
                }

                return $components;
            })
            ->successNotification(null)
            ->action(function (HelpDesk $record, array $data) use ($panel): void {
                $user = Auth::user();
                if ($user === null) {
                    return;
                }

                $previousStatus = (string) $record->status;
                $newStatus = (string) ($data['status'] ?? $record->status);
                $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave(
                    $record,
                    $newStatus,
                    $user->name,
                    self::currentUserIsTicketAssignee($record)
                );

                if ($sanitized === $record->status) {
                    SecurityAudit::log('AUDIT_HELPDESK_STATUS_UPDATE_SKIPPED', $panel.'.helpdesks.update-status', [
                        'panel' => $panel,
                        'helpdesk_id' => $record->getKey(),
                        'status' => $record->status,
                        'updated_by' => $user->name,
                        'reason' => 'no_changes',
                    ]);

                    Notification::make()
                        ->title('Sin cambios')
                        ->body('El estado del ticket no se modificó.')
                        ->info()
                        ->send();

                    return;
                }

                $isAssignee = self::currentUserIsTicketAssignee($record);
                $isCreatorUpdating = HelpdeskTicketIdentity::isCreator($record, $user);
                $wasTerminal = in_array($previousStatus, HelpdeskTaskStatusOptions::terminalStatuses(), true);
                $isReopen = $wasTerminal && ! in_array($sanitized, HelpdeskTaskStatusOptions::terminalStatuses(), true);

                if ($isAssignee && ! $isReopen) {
                    $explanationError = HelpdeskStatusChangeNote::validateAssigneeExplanation($data['status_explanation'] ?? null);

                    if ($explanationError !== null) {
                        SecurityAudit::log('AUDIT_HELPDESK_STATUS_UPDATE_FAILED', $panel.'.helpdesks.update-status', [
                            'panel' => $panel,
                            'helpdesk_id' => $record->getKey(),
                            'reason' => 'missing_assignee_explanation',
                            'updated_by' => $user->name,
                        ]);

                        Notification::make()
                            ->title($explanationError['title'])
                            ->body($explanationError['body'])
                            ->warning()
                            ->send();

                        return;
                    }
                }

                if ($sanitized === HelpdeskTaskStatusOptions::STATUS_CANCELLED) {
                    $reason = trim((string) ($data['cancellation_reason'] ?? ''));
                    if ($reason === '') {
                        Notification::make()
                            ->title('Motivo requerido')
                            ->body('Seleccione el motivo de cancelación.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $record->cancellation_reason = $reason;
                    HelpdeskSla::markCancelled($record);
                }

                if ($sanitized === HelpdeskTaskStatusOptions::STATUS_DONE) {
                    if ($isCreatorUpdating) {
                        $score = (int) ($data['csat_score'] ?? 0);
                        if ($score < 1 || $score > 5) {
                            Notification::make()
                                ->title('Calificación requerida')
                                ->body('Indique una calificación CSAT del 1 al 5 para cerrar el ticket.')
                                ->warning()
                                ->send();

                            return;
                        }

                        HelpDeskCsat::query()->updateOrCreate(
                            ['help_desk_id' => $record->getKey()],
                            [
                                'user_id' => $user->getAuthIdentifier(),
                                'score' => $score,
                                'comment' => filled($data['csat_comment'] ?? null) ? trim((string) $data['csat_comment']) : null,
                            ],
                        );

                        HelpdeskEventRecorder::record(
                            $record,
                            HelpdeskEventRecorder::TYPE_CSAT,
                            '<p>CSAT: <strong>'.$score.'/5</strong>'
                            .(filled($data['csat_comment'] ?? null) ? ' — '.e(trim((string) $data['csat_comment'])) : '')
                            .'</p>',
                            $user,
                            ['score' => $score],
                        );
                    }

                    HelpdeskSla::markResolved($record);
                }

                if ($isReopen) {
                    HelpdeskSla::markReopened($record);
                }

                $record->status = $sanitized;
                $record->updated_by = $user->name;
                $record->save();
                $record->refresh();
                $statusNote = HelpdeskStatusChangeNote::buildObservationHtml(
                    $previousStatus,
                    $sanitized,
                    $data['status_explanation'] ?? null,
                    $isAssignee,
                );
                HelpdeskObservationAppender::append(
                    $record,
                    $statusNote,
                    $user->name,
                    user: $user,
                    eventType: $isReopen ? HelpdeskEventRecorder::TYPE_REOPEN : HelpdeskEventRecorder::TYPE_STATUS_CHANGE,
                    meta: [
                        'from' => $previousStatus,
                        'to' => $sanitized,
                        'cancellation_reason' => $record->cancellation_reason,
                    ],
                );

                $closedByCreator = $isCreatorUpdating && $sanitized === 'TERMINADO';
                $notifyTarget = $closedByCreator ? 'ticket_assignees' : ($isCreatorUpdating ? 'none_updater_is_creator' : 'ticket_creator');

                $whatsAppReport = [
                    'dispatched' => 0,
                    'failed' => 0,
                    'skipped_no_phone' => 0,
                    'failures' => [],
                ];

                if ($closedByCreator) {
                    $whatsAppReport = HelpdeskTicketAssigneeWhatsAppService::dispatchCustomMessageToEachAssigneeWithReport(
                        ticket: $record,
                        requestedByUserId: Auth::id(),
                        panel: $panel,
                        body: HelpdeskTicketAssigneeWhatsAppService::buildTicketClosedByCreatorBody($record, $user->name),
                        source: 'helpdesk.ticket.closed-by-creator',
                        auditRoute: $panel.'.helpdesks.notifications.whatsapp.status',
                    );
                } elseif (! $isCreatorUpdating) {
                    $whatsAppReport = HelpdeskTicketAssigneeWhatsAppService::dispatchToTicketCreatorWithReport(
                        ticket: $record,
                        requestedByUserId: Auth::id(),
                        panel: $panel,
                        body: HelpdeskTicketAssigneeWhatsAppService::buildStatusUpdatedBody($record, $previousStatus, $sanitized, $user->name),
                        source: 'helpdesk.ticket.status-updated.creator-followup',
                        auditRoute: $panel.'.helpdesks.notifications.whatsapp.status',
                    );
                }

                SecurityAudit::log('AUDIT_HELPDESK_STATUS_UPDATED', $panel.'.helpdesks.update-status', [
                    'panel' => $panel,
                    'helpdesk_id' => $record->getKey(),
                    'old_status' => $previousStatus,
                    'new_status' => $sanitized,
                    'updated_by' => $user->name,
                    'notify_target' => $notifyTarget,
                    'whatsapp_dispatched_count' => $whatsAppReport['dispatched'],
                    'whatsapp_failed_count' => $whatsAppReport['failed'],
                    'whatsapp_skipped_no_phone_count' => $whatsAppReport['skipped_no_phone'],
                    'whatsapp_failures' => array_slice($whatsAppReport['failures'], 0, 10),
                ]);

                Notification::make()
                    ->title($isReopen ? 'Ticket reabierto' : 'Estado actualizado')
                    ->body('El ticket #'.$record->getKey().' quedó en: '.(HelpdeskTaskStatusOptions::all()[$sanitized] ?? $sanitized).'.')
                    ->success()
                    ->send();
            })
            ->hidden(function (HelpDesk $record): bool {
                if (HelpdeskTicketVisibility::canViewGlobalQueue()
                    || HelpdeskTicketIdentity::isCreator($record, Auth::user())
                    || self::currentUserIsTicketAssignee($record)) {
                    return false;
                }

                return in_array($record->status, HelpdeskTaskStatusOptions::terminalStatuses(), true);
            });
    }

    public static function makeUpdatePriorityAction(string $panel = 'business'): Action
    {
        return Action::make('updatePriority')
            ->label('Cambiar prioridad')
            ->icon('heroicon-m-bolt')
            ->color('warning')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Cambiar prioridad del ticket')
            ->modalDescription(fn (HelpDesk $record): string => 'Solo el creador puede ajustar la urgencia · Ticket #'.$record->getKey().' · '.$record->created_by)
            ->modalSubmitActionLabel('Guardar prioridad')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => self::IOS_SUCCESS_BTN,
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cancelar')
                    ->extraAttributes([
                        'class' => self::IOS_GRAY_BTN,
                    ])
            )
            ->fillForm(fn (HelpDesk $record): array => [
                'priority' => $record->priority,
            ])
            ->form([
                Section::make('Prioridad')
                    ->description('Indica la urgencia con la que deben atender el caso quienes están asignados.')
                    ->icon('heroicon-m-bolt')
                    ->schema([
                        Select::make('priority')
                            ->label('Prioridad')
                            ->prefixIcon('heroicon-m-bolt')
                            ->options([
                                'BAJA' => 'Baja — puede esperar',
                                'MEDIA' => 'Media — flujo normal',
                                'ALTA' => 'Alta — bloquea trabajo',
                            ])
                            ->required()
                            ->native(false)
                            ->extraInputAttributes([
                                'class' => 'helpdesk-status-native-select w-full max-w-full min-h-11 text-base sm:text-sm',
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),
            ])
            ->successNotification(null)
            ->action(function (HelpDesk $record, array $data): void {
                $user = Auth::user();
                if ($user === null) {
                    return;
                }

                if (! HelpdeskTicketIdentity::isCreator($record, Auth::user())) {
                    SecurityAudit::log('AUDIT_HELPDESK_PRIORITY_UPDATE_DENIED', $panel.'.helpdesks.update-priority', [
                        'panel' => $panel,
                        'helpdesk_id' => $record->getKey(),
                        'reason' => 'not_ticket_creator',
                    ]);

                    Notification::make()
                        ->title('No autorizado')
                        ->body('Solo quien creó el ticket puede cambiar la prioridad.')
                        ->danger()
                        ->send();

                    return;
                }

                $allowed = ['BAJA', 'MEDIA', 'ALTA'];
                $newPriority = strtoupper(trim((string) ($data['priority'] ?? '')));
                if (! in_array($newPriority, $allowed, true)) {
                    SecurityAudit::log('AUDIT_HELPDESK_PRIORITY_UPDATE_FAILED', $panel.'.helpdesks.update-priority', [
                        'panel' => $panel,
                        'helpdesk_id' => $record->getKey(),
                        'reason' => 'invalid_priority',
                        'submitted' => (string) ($data['priority'] ?? ''),
                    ]);

                    Notification::make()
                        ->title('Prioridad no válida')
                        ->body('Selecciona Baja, Media o Alta.')
                        ->warning()
                        ->send();

                    return;
                }

                $previousPriority = (string) $record->priority;
                if ($newPriority === $previousPriority) {
                    SecurityAudit::log('AUDIT_HELPDESK_PRIORITY_UPDATE_SKIPPED', $panel.'.helpdesks.update-priority', [
                        'panel' => $panel,
                        'helpdesk_id' => $record->getKey(),
                        'priority' => $record->priority,
                        'updated_by' => $user->name,
                        'reason' => 'no_changes',
                    ]);

                    Notification::make()
                        ->title('Sin cambios')
                        ->body('La prioridad del ticket no se modificó.')
                        ->info()
                        ->send();

                    return;
                }

                $record->priority = $newPriority;
                HelpdeskSla::refreshDueDatesForPriority($record);
                $noteHtml = '<p>Prioridad actualizada de <strong>'.$previousPriority.'</strong> a <strong>'.$newPriority.'</strong>.</p>';
                HelpdeskObservationAppender::append(
                    $record,
                    $noteHtml,
                    $user->name,
                    user: $user,
                    eventType: HelpdeskEventRecorder::TYPE_PRIORITY_CHANGE,
                    meta: ['from' => $previousPriority, 'to' => $newPriority],
                );
                $record->refresh();

                $whatsAppReport = HelpdeskTicketAssigneeWhatsAppService::dispatchCustomMessageToEachAssigneeWithReport(
                    ticket: $record,
                    requestedByUserId: Auth::id(),
                    panel: $panel,
                    body: HelpdeskTicketAssigneeWhatsAppService::buildPriorityUpdatedByCreatorBody(
                        $record,
                        $previousPriority,
                        $newPriority,
                        $user->name,
                    ),
                    source: 'helpdesk.ticket.priority-updated-by-creator',
                    auditRoute: $panel.'.helpdesks.notifications.whatsapp.priority',
                );

                Log::info('Helpdesk: prioridad actualizada por el creador del ticket.', [
                    'panel' => $panel,
                    'helpdesk_id' => $record->getKey(),
                    'old_priority' => $previousPriority,
                    'new_priority' => $newPriority,
                    'updated_by' => $user->name,
                    'whatsapp_dispatched' => $whatsAppReport['dispatched'],
                ]);

                SecurityAudit::log('AUDIT_HELPDESK_PRIORITY_UPDATED', $panel.'.helpdesks.update-priority', [
                    'panel' => $panel,
                    'helpdesk_id' => $record->getKey(),
                    'old_priority' => $previousPriority,
                    'new_priority' => $newPriority,
                    'updated_by' => $user->name,
                    'notify_target' => 'ticket_assignees',
                    'whatsapp_dispatched_count' => $whatsAppReport['dispatched'],
                    'whatsapp_failed_count' => $whatsAppReport['failed'],
                    'whatsapp_skipped_no_phone_count' => $whatsAppReport['skipped_no_phone'],
                    'whatsapp_failures' => array_slice($whatsAppReport['failures'], 0, 10),
                ]);

                Notification::make()
                    ->title('Prioridad actualizada')
                    ->body('El ticket #'.$record->getKey().' quedó en prioridad '.$newPriority.'. Se notificó por WhatsApp a quienes están asignados cuando hay teléfono válido.')
                    ->success()
                    ->send();
            })
            ->hidden(fn (HelpDesk $record): bool => ! HelpdeskTicketIdentity::isCreator($record, Auth::user())
                || in_array($record->status, ['TERMINADO', 'CANCELADO'], true));
    }
}
