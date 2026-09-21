<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeMailService;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\InternalPanelDepartmentMap;
use App\Support\Filament\UserNavigationAccess;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HelpdeskTicketReassignment
{
    public const MIN_EXPLANATION_LENGTH = 3;

    /**
     * @return array{title: string, body: string}|null
     */
    public static function validateExplanation(?string $html): ?array
    {
        $plainLength = HelpdeskStatusChangeNote::plainTextLength($html);

        if ($plainLength < self::MIN_EXPLANATION_LENGTH) {
            return [
                'title' => 'Motivo requerido',
                'body' => 'Indique el motivo de la reasignación (mínimo '.self::MIN_EXPLANATION_LENGTH.' caracteres).',
            ];
        }

        return null;
    }

    public static function userCan(?Authenticatable $user, HelpDesk $record, ?string $panel = null): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (in_array((string) $record->status, HelpdeskTaskStatusOptions::terminalStatuses(), true)) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return true;
        }

        $module = self::moduleForPanel($panel);

        if (! UserNavigationAccess::canPerformModuleAction(
            $user,
            $module,
            BusinessFilamentActionPermissionRegistry::REASSIGN_HELPDESK_TICKET,
        )) {
            return false;
        }

        return self::userIsAssignee($user, $record);
    }

    public static function userIsAssignee(User $user, HelpDesk $record): bool
    {
        $userId = $user->getAuthIdentifier();

        if ($userId === null) {
            return false;
        }

        $record->loadMissing('rrhhColaboradores');

        return $record->rrhhColaboradores->contains(
            function (RrhhColaborador $colaborador) use ($userId): bool {
                return (int) $colaborador->user_id === (int) $userId;
            }
        );
    }

    /**
     * @param  list<int|string>|array<int, int|string>  $ids
     * @return list<int>
     */
    public static function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            if (! is_numeric($id)) {
                continue;
            }

            $intId = (int) $id;

            if ($intId > 0) {
                $normalized[] = $intId;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  list<int|string>|array<int, int|string>  $currentIds
     * @param  list<int|string>|array<int, int|string>  $newIds
     * @return array{added: list<int>, removed: list<int>, unchanged: list<int>}
     */
    public static function diffIds(array $currentIds, array $newIds): array
    {
        $current = self::normalizeIds($currentIds);
        $new = self::normalizeIds($newIds);

        return [
            'added' => array_values(array_diff($new, $current)),
            'removed' => array_values(array_diff($current, $new)),
            'unchanged' => array_values(array_intersect($current, $new)),
        ];
    }

    /**
     * @param  list<string>  $previousNames
     * @param  list<string>  $newNames
     */
    public static function buildObservationHtml(
        array $previousNames,
        array $newNames,
        ?string $explanationHtml,
    ): string {
        $html = '<p>Ticket reasignado. Responsables anteriores: <strong>'.e(self::formatNameList($previousNames))
            .'</strong>. Nuevos responsables: <strong>'.e(self::formatNameList($newNames)).'</strong>.</p>';

        $explanation = HelpdeskNoteHtmlSanitizer::sanitize(trim((string) $explanationHtml));

        if ($explanation !== '' && HelpdeskStatusChangeNote::plainTextLength($explanation) >= self::MIN_EXPLANATION_LENGTH) {
            $html .= '<p><strong>Motivo de la reasignación:</strong></p>'.$explanation;
        }

        return $html;
    }

    /**
     * @param  list<int|string>|array<int, int|string>  $newAssigneeIds
     * @return array{
     *     ok: bool,
     *     code: string,
     *     title: string,
     *     body: string,
     *     added_ids: list<int>,
     *     removed_ids: list<int>,
     *     email_report: array<string, mixed>|null,
     *     whatsapp_new_report: array<string, mixed>|null,
     *     whatsapp_removed_report: array<string, mixed>|null,
     *     whatsapp_creator_report: array<string, mixed>|null
     * }
     */
    public static function apply(
        HelpDesk $ticket,
        User $user,
        array $newAssigneeIds,
        ?string $explanationHtml,
        string $panel,
    ): array {
        $empty = self::emptyResult();

        if (! self::userCan($user, $ticket, $panel)) {
            return array_merge($empty, [
                'ok' => false,
                'code' => 'forbidden',
                'title' => 'No autorizado',
                'body' => 'No tiene permiso para reasignar este ticket. Pida al administrador o a un superadmin que le conceda la acción «Reasignar tickets». Además, debe estar asignado al caso (los superadmin pueden reasignar cualquier ticket abierto).',
            ]);
        }

        $explanationError = self::validateExplanation($explanationHtml);

        if ($explanationError !== null) {
            return array_merge($empty, [
                'ok' => false,
                'code' => 'missing_explanation',
                'title' => $explanationError['title'],
                'body' => $explanationError['body'],
            ]);
        }

        $newIds = self::normalizeIds($newAssigneeIds);

        if ($newIds === []) {
            return array_merge($empty, [
                'ok' => false,
                'code' => 'empty_assignees',
                'title' => 'Asignado requerido',
                'body' => 'Debe seleccionar al menos un colaborador responsable del ticket.',
            ]);
        }

        $ticket->loadMissing('rrhhColaboradores');
        $currentIds = $ticket->rrhhColaboradores
            ->map(fn (RrhhColaborador $colaborador): int => (int) $colaborador->getKey())
            ->all();
        $diff = self::diffIds($currentIds, $newIds);

        if ($diff['added'] === [] && $diff['removed'] === []) {
            return array_merge($empty, [
                'ok' => false,
                'code' => 'no_changes',
                'title' => 'Sin cambios',
                'body' => 'Los responsables del ticket no se modificaron.',
            ]);
        }

        $allowedIds = array_map(
            static fn (int|string $id): int => (int) $id,
            array_keys(HelpdeskFormSchema::rrhhColaboradorOptionsForHelpdeskMultiselect())
        );
        $unknown = array_values(array_diff($newIds, $allowedIds));

        if ($unknown !== []) {
            return array_merge($empty, [
                'ok' => false,
                'code' => 'invalid_assignees',
                'title' => 'Asignados no válidos',
                'body' => 'Uno o más colaboradores seleccionados no están habilitados para ejecutar tickets.',
            ]);
        }

        $previousNames = self::namesFromColaboradores($ticket->rrhhColaboradores);
        /** @var Collection<int, RrhhColaborador> $removedColaboradores */
        $removedColaboradores = $ticket->rrhhColaboradores
            ->filter(fn (RrhhColaborador $colaborador): bool => in_array((int) $colaborador->getKey(), $diff['removed'], true))
            ->values();

        try {
            DB::transaction(function () use ($ticket, $user, $newIds, $previousNames, $explanationHtml, $diff, $currentIds): void {
                $ticket->rrhhColaboradores()->sync($newIds);
                $ticket->updated_by = $user->name;
                $ticket->save();
                $ticket->unsetRelation('rrhhColaboradores');
                $ticket->load('rrhhColaboradores');

                HelpdeskObservationAppender::append(
                    $ticket,
                    self::buildObservationHtml(
                        $previousNames,
                        self::namesFromColaboradores($ticket->rrhhColaboradores),
                        $explanationHtml,
                    ),
                    $user->name,
                    user: $user,
                    eventType: HelpdeskEventRecorder::TYPE_ASSIGNMENT_CHANGE,
                    meta: [
                        'previous_ids' => self::normalizeIds($currentIds),
                        'new_ids' => $newIds,
                        'added_ids' => $diff['added'],
                        'removed_ids' => $diff['removed'],
                    ],
                );
            });
        } catch (Throwable $throwable) {
            SecurityAudit::log('AUDIT_HELPDESK_REASSIGN_FAILED', $panel.'.helpdesks.reassign', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'updated_by' => $user->name,
                'error' => $throwable->getMessage(),
                'exception' => $throwable::class,
            ]);

            return array_merge($empty, [
                'ok' => false,
                'code' => 'persist_failed',
                'title' => 'No se pudo reasignar',
                'body' => 'Ocurrió un error al guardar la reasignación. Intente de nuevo; si persiste, avise a sistemas.',
            ]);
        }

        $emailReport = null;
        $whatsAppNewReport = null;
        $whatsAppRemovedReport = null;
        $whatsAppCreatorReport = null;

        try {
            $emailReport = HelpdeskTicketAssigneeMailService::sendToEachAssigneeWithReport(
                $ticket,
                $panel,
                $diff['added'],
                true,
            );
            $whatsAppNewReport = HelpdeskTicketAssigneeWhatsAppService::dispatchCustomMessageToColaboradoresWithReport(
                ticket: $ticket,
                colaboradores: $ticket->rrhhColaboradores
                    ->filter(fn (RrhhColaborador $colaborador): bool => in_array((int) $colaborador->getKey(), $diff['added'], true))
                    ->values(),
                requestedByUserId: (int) $user->getAuthIdentifier(),
                panel: $panel,
                body: HelpdeskTicketAssigneeWhatsAppService::buildReassignedToNewAssigneeBody(
                    $ticket,
                    $user->name,
                    $explanationHtml,
                ),
                source: 'helpdesk.ticket.reassigned.new-assignee',
                auditRoute: $panel.'.helpdesks.notifications.whatsapp.reassign',
            );
            $whatsAppRemovedReport = HelpdeskTicketAssigneeWhatsAppService::dispatchCustomMessageToColaboradoresWithReport(
                ticket: $ticket,
                colaboradores: $removedColaboradores,
                requestedByUserId: (int) $user->getAuthIdentifier(),
                panel: $panel,
                body: HelpdeskTicketAssigneeWhatsAppService::buildUnassignedBody(
                    $ticket,
                    $user->name,
                ),
                source: 'helpdesk.ticket.reassigned.removed-assignee',
                auditRoute: $panel.'.helpdesks.notifications.whatsapp.reassign',
            );

            if (! HelpdeskTicketIdentity::isCreator($ticket, $user)) {
                $whatsAppCreatorReport = HelpdeskTicketAssigneeWhatsAppService::dispatchToTicketCreatorWithReport(
                    ticket: $ticket,
                    requestedByUserId: (int) $user->getAuthIdentifier(),
                    panel: $panel,
                    body: HelpdeskTicketAssigneeWhatsAppService::buildReassignedToCreatorBody(
                        $ticket,
                        $user->name,
                        self::formatNameList(self::namesFromColaboradores($ticket->rrhhColaboradores)),
                    ),
                    source: 'helpdesk.ticket.reassigned.creator-followup',
                    auditRoute: $panel.'.helpdesks.notifications.whatsapp.reassign',
                );
            }
        } catch (Throwable $throwable) {
            SecurityAudit::log('AUDIT_HELPDESK_REASSIGN_NOTIFY_FAILED', $panel.'.helpdesks.reassign', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'updated_by' => $user->name,
                'error' => $throwable->getMessage(),
                'exception' => $throwable::class,
            ]);
        }

        $newNames = self::formatNameList(self::namesFromColaboradores($ticket->rrhhColaboradores));

        SecurityAudit::log('AUDIT_HELPDESK_TICKET_REASSIGNED', $panel.'.helpdesks.reassign', [
            'panel' => $panel,
            'helpdesk_id' => $ticket->getKey(),
            'updated_by' => $user->name,
            'added_ids' => $diff['added'],
            'removed_ids' => $diff['removed'],
            'new_ids' => $newIds,
            'email_sent_count' => $emailReport['sent'] ?? 0,
            'whatsapp_new_dispatched_count' => $whatsAppNewReport['dispatched'] ?? 0,
            'whatsapp_removed_dispatched_count' => $whatsAppRemovedReport['dispatched'] ?? 0,
            'whatsapp_creator_dispatched_count' => $whatsAppCreatorReport['dispatched'] ?? 0,
        ]);

        return [
            'ok' => true,
            'code' => 'reassigned',
            'title' => 'Ticket reasignado',
            'body' => 'El ticket #'.$ticket->getKey().' quedó a cargo de: '.$newNames.'. Se notificó a los nuevos responsables.',
            'added_ids' => $diff['added'],
            'removed_ids' => $diff['removed'],
            'email_report' => $emailReport,
            'whatsapp_new_report' => $whatsAppNewReport,
            'whatsapp_removed_report' => $whatsAppRemovedReport,
            'whatsapp_creator_report' => $whatsAppCreatorReport,
        ];
    }

    public static function moduleForPanel(?string $panel): string
    {
        if (is_string($panel) && $panel !== '') {
            return InternalPanelDepartmentMap::moduleForPanel($panel)
                ?? BusinessFilamentActionPermissionRegistry::OWNER_MODULE;
        }

        try {
            $panelId = Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return BusinessFilamentActionPermissionRegistry::OWNER_MODULE;
        }

        if (! is_string($panelId) || $panelId === '') {
            return BusinessFilamentActionPermissionRegistry::OWNER_MODULE;
        }

        $module = InternalPanelDepartmentMap::moduleForPanel($panelId);

        if ($module === null) {
            return BusinessFilamentActionPermissionRegistry::OWNER_MODULE;
        }

        return BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule(
            BusinessFilamentActionPermissionRegistry::REASSIGN_HELPDESK_TICKET,
            $module,
        )
            ? $module
            : BusinessFilamentActionPermissionRegistry::OWNER_MODULE;
    }

    /**
     * @param  Collection<int, RrhhColaborador>  $colaboradores
     * @return list<string>
     */
    private static function namesFromColaboradores(Collection $colaboradores): array
    {
        return $colaboradores
            ->map(fn (RrhhColaborador $colaborador): string => trim((string) $colaborador->fullName))
            ->filter(fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $names
     */
    public static function formatNameList(array $names): string
    {
        $clean = array_values(array_filter(
            array_map(static fn (string $name): string => trim($name), $names),
            static fn (string $name): bool => $name !== '',
        ));

        if ($clean === []) {
            return 'Sin asignar';
        }

        return implode(', ', $clean);
    }

    /**
     * @return array{
     *     ok: bool,
     *     code: string,
     *     title: string,
     *     body: string,
     *     added_ids: list<int>,
     *     removed_ids: list<int>,
     *     email_report: null,
     *     whatsapp_new_report: null,
     *     whatsapp_removed_report: null,
     *     whatsapp_creator_report: null
     * }
     */
    private static function emptyResult(): array
    {
        return [
            'ok' => false,
            'code' => '',
            'title' => '',
            'body' => '',
            'added_ids' => [],
            'removed_ids' => [],
            'email_report' => null,
            'whatsapp_new_report' => null,
            'whatsapp_removed_report' => null,
            'whatsapp_creator_report' => null,
        ];
    }
}
