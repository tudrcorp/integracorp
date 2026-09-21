<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeMailService;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use Illuminate\Support\Facades\Auth;

final class HelpdeskBusinessScrumWorkflow
{
    /**
     * @param  array<string, mixed>|list<int|string>|int|string|null  $assignees
     * @return list<int>
     */
    public static function normalizeAssigneeIds(mixed $assignees): array
    {
        if (! is_array($assignees)) {
            $assignees = filled($assignees) ? [$assignees] : [];
        }

        return array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter($assignees, static fn (mixed $id): bool => filled($id))
        )));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isQueuedForProductOwner(array $data, ?int $productOwnerId = null): bool
    {
        $productOwnerId ??= HelpdeskBusinessScrumRoles::productOwnerId();

        if ($productOwnerId === null) {
            return false;
        }

        return self::normalizeAssigneeIds($data['rrhhColaboradores'] ?? null) === [$productOwnerId];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function queueForProductOwner(array $data, ?int $productOwnerId = null): array
    {
        $productOwnerId ??= HelpdeskBusinessScrumRoles::productOwnerId();

        if ($productOwnerId === null || ! self::isQueuedForProductOwner($data, $productOwnerId)) {
            return $data;
        }

        $data['rrhhColaboradores'] = [$productOwnerId];
        $data['status'] = HelpdeskTaskStatusOptions::STATUS_PENDING;

        return $data;
    }

    public static function productOwnerReadinessError(): ?string
    {
        $productOwner = HelpdeskBusinessScrumRoles::productOwnerColaborador();

        if ($productOwner === null) {
            return 'No se encontró a '.HelpdeskBusinessScrumRoles::PRODUCT_OWNER_NAME.' en el directorio RRHH. El ticket de negocios debe entrar al backlog del Product Owner.';
        }

        if (blank($productOwner->user_id)) {
            return HelpdeskBusinessScrumRoles::PRODUCT_OWNER_NAME.' no tiene usuario de sistema. No se puede enviar el ticket al backlog Scrum.';
        }

        if (blank($productOwner->emailCorporativo)) {
            return HelpdeskBusinessScrumRoles::PRODUCT_OWNER_NAME.' no tiene correo corporativo. No se puede enviar el ticket al backlog Scrum.';
        }

        return null;
    }

    public static function syncProductOwnerInbox(HelpDesk $ticket, ?int $productOwnerId = null): void
    {
        $productOwnerId ??= HelpdeskBusinessScrumRoles::productOwnerId();

        if ($productOwnerId === null) {
            return;
        }

        $ticket->rrhhColaboradores()->sync([$productOwnerId]);
        $ticket->unsetRelation('rrhhColaboradores');
        $ticket->load('rrhhColaboradores');
    }

    /**
     * @param  list<int|string>  $assigneeIds
     * @param  list<int>  $developerIds
     */
    public static function assignmentIsProductOwnerInbox(array $assigneeIds, int $productOwnerId, array $developerIds): bool
    {
        $assigneeIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $assigneeIds
        )));

        if (! in_array($productOwnerId, $assigneeIds, true)) {
            return false;
        }

        return array_intersect($assigneeIds, array_map(static fn (mixed $id): int => (int) $id, $developerIds)) === [];
    }

    public static function ticketIsProductOwnerInbox(HelpDesk $ticket): bool
    {
        $productOwnerId = HelpdeskBusinessScrumRoles::productOwnerId();

        if ($productOwnerId === null) {
            return false;
        }

        $ticket->loadMissing('rrhhColaboradores');

        return self::assignmentIsProductOwnerInbox(
            $ticket->rrhhColaboradores->modelKeys(),
            $productOwnerId,
            HelpdeskBusinessScrumRoles::developerIds()
        );
    }

    public static function canRevertToAnalyst(HelpDesk $ticket, ?User $user = null, ?bool $isProductOwnerInbox = null): bool
    {
        $user ??= Auth::user() instanceof User ? Auth::user() : null;

        if (! $user instanceof User || ! HelpdeskBusinessScrumRoles::isProductOwnerUser($user)) {
            return false;
        }

        if (! ($isProductOwnerInbox ?? self::ticketIsProductOwnerInbox($ticket))) {
            return false;
        }

        return ! in_array($ticket->status, [
            HelpdeskTaskStatusOptions::STATUS_DONE,
            HelpdeskTaskStatusOptions::STATUS_CANCELLED,
            HelpdeskTaskStatusOptions::STATUS_REVERTED,
        ], true);
    }

    public static function canAssignToSprint(HelpDesk $ticket, ?User $user = null, ?bool $isProductOwnerInbox = null): bool
    {
        $user ??= Auth::user() instanceof User ? Auth::user() : null;

        if (! $user instanceof User || ! HelpdeskBusinessScrumRoles::isProductOwnerUser($user)) {
            return false;
        }

        if (! ($isProductOwnerInbox ?? self::ticketIsProductOwnerInbox($ticket))) {
            return false;
        }

        return ! in_array($ticket->status, [
            HelpdeskTaskStatusOptions::STATUS_DONE,
            HelpdeskTaskStatusOptions::STATUS_CANCELLED,
        ], true);
    }

    /**
     * @return array{ok: bool, title: string, body: string}
     */
    public static function revertToAnalyst(HelpDesk $ticket, User $actor, string $reason, string $panel = 'business'): array
    {
        if (! self::canRevertToAnalyst($ticket, $actor)) {
            return [
                'ok' => false,
                'title' => 'Acción no permitida',
                'body' => 'Solo el Product Owner puede revertir un ticket abierto al analista.',
            ];
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 8) {
            return [
                'ok' => false,
                'title' => 'Motivo requerido',
                'body' => 'Indique el motivo de la reversión (mínimo 8 caracteres) para devolver el ticket al analista.',
            ];
        }

        $previousStatus = (string) $ticket->status;
        $productOwnerId = HelpdeskBusinessScrumRoles::productOwnerId();

        if ($productOwnerId !== null) {
            $ticket->rrhhColaboradores()->sync([$productOwnerId]);
        }

        $ticket->status = HelpdeskTaskStatusOptions::STATUS_REVERTED;
        $ticket->cancellation_reason = $reason;
        $ticket->updated_by = $actor->name;
        HelpdeskSla::markCancelled($ticket);
        $ticket->save();
        $ticket->refresh();
        $ticket->load('rrhhColaboradores');

        $noteHtml = '<p>El Product Owner <strong>'.e($actor->name).'</strong> revirtió el ticket al analista.</p>'
            .'<p><strong>Motivo:</strong> '.e($reason).'</p>';

        HelpdeskObservationAppender::append(
            $ticket,
            $noteHtml,
            $actor->name,
            user: $actor,
            eventType: HelpdeskEventRecorder::TYPE_STATUS_CHANGE,
            meta: [
                'action' => 'scrum_revert',
                'from' => $previousStatus,
                'to' => HelpdeskTaskStatusOptions::STATUS_REVERTED,
            ],
        );

        $emailReport = HelpdeskTicketAssigneeMailService::sendRevertedToCreatorWithReport(
            $ticket,
            $actor->name,
            $reason,
            $panel,
        );

        $whatsAppReport = HelpdeskTicketAssigneeWhatsAppService::dispatchToTicketCreatorWithReport(
            ticket: $ticket,
            requestedByUserId: $actor->getAuthIdentifier() !== null ? (int) $actor->getAuthIdentifier() : null,
            panel: $panel,
            body: HelpdeskTicketAssigneeWhatsAppService::buildRevertedToCreatorBody($ticket, $actor->name, $reason),
            source: 'helpdesk.ticket.scrum-reverted.creator-followup',
            auditRoute: $panel.'.helpdesks.notifications.whatsapp.scrum-revert',
        );

        SecurityAudit::log('AUDIT_HELPDESK_SCRUM_REVERTED', $panel.'.helpdesks.scrum-revert', [
            'panel' => $panel,
            'helpdesk_id' => $ticket->getKey(),
            'updated_by' => $actor->name,
            'reason' => $reason,
            'email_sent_count' => $emailReport['sent'],
            'email_failed_count' => $emailReport['failed'],
            'email_skipped_no_email_count' => $emailReport['skipped_no_email'],
            'whatsapp_dispatched_count' => $whatsAppReport['dispatched'],
            'whatsapp_failed_count' => $whatsAppReport['failed'],
            'whatsapp_skipped_no_phone_count' => $whatsAppReport['skipped_no_phone'],
        ]);

        $body = 'El ticket #'.$ticket->getKey().' volvió al analista con el motivo indicado.';
        if ((int) $emailReport['sent'] > 0) {
            $body .= ' Se notificó por correo a quien creó el ticket.';
        } elseif ((int) $emailReport['skipped_no_email'] > 0) {
            $body .= ' No se pudo notificar por correo: el creador no tiene un email válido.';
        }
        if ((int) $whatsAppReport['dispatched'] > 0) {
            $body .= ' Se notificó por WhatsApp a quien creó el ticket.';
        } elseif ((int) $whatsAppReport['skipped_no_phone'] > 0) {
            $body .= ' No se pudo notificar por WhatsApp: el creador no tiene un teléfono válido.';
        }

        return [
            'ok' => true,
            'title' => 'Ticket revertido',
            'body' => $body,
        ];
    }

    /**
     * @param  list<int|string>  $developerIds
     * @return array{ok: bool, title: string, body: string}
     */
    public static function assignToSprint(HelpDesk $ticket, User $actor, array $developerIds, string $panel = 'business'): array
    {
        if (! self::canAssignToSprint($ticket, $actor)) {
            return [
                'ok' => false,
                'title' => 'Acción no permitida',
                'body' => 'Solo el Product Owner puede asignar el ticket al equipo de desarrollo.',
            ];
        }

        $allowedIds = HelpdeskBusinessScrumRoles::developerIds();
        $selectedIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $developerIds),
            static fn (int $id): bool => in_array($id, $allowedIds, true)
        )));

        if ($selectedIds === []) {
            return [
                'ok' => false,
                'title' => 'Equipo requerido',
                'body' => 'Seleccione a Anthony Aular y/o Gustavo Camacho para resolver el ticket.',
            ];
        }

        $previousStatus = (string) $ticket->status;
        $ticket->rrhhColaboradores()->sync($selectedIds);
        $ticket->status = HelpdeskTaskStatusOptions::STATUS_IN_ANALYSIS;
        $ticket->cancellation_reason = null;
        $ticket->updated_by = $actor->name;
        HelpdeskSla::markReopened($ticket);
        HelpdeskSla::markFirstResponseIfNeeded($ticket, $actor);
        $ticket->save();
        $ticket->refresh();
        $ticket->load('rrhhColaboradores');

        $assigneeNames = $ticket->rrhhColaboradores
            ->pluck('fullName')
            ->filter()
            ->implode(', ');

        $noteHtml = '<p>El Product Owner <strong>'.e($actor->name).'</strong> asignó el ticket al sprint.</p>'
            .'<p><strong>Desarrolladores:</strong> '.e($assigneeNames).'</p>';

        HelpdeskObservationAppender::append(
            $ticket,
            $noteHtml,
            $actor->name,
            user: $actor,
            eventType: HelpdeskEventRecorder::TYPE_STATUS_CHANGE,
            meta: [
                'action' => 'scrum_assign_sprint',
                'from' => $previousStatus,
                'to' => HelpdeskTaskStatusOptions::STATUS_IN_ANALYSIS,
                'developer_ids' => $selectedIds,
            ],
        );

        HelpdeskTicketAssigneeMailService::sendToEachAssigneeWithReport($ticket, $panel);
        HelpdeskTicketAssigneeWhatsAppService::dispatchCustomMessageToEachAssigneeWithReport(
            ticket: $ticket,
            requestedByUserId: $actor->getAuthIdentifier() !== null ? (int) $actor->getAuthIdentifier() : null,
            panel: $panel,
            body: HelpdeskTicketAssigneeWhatsAppService::buildSprintReassignedBody($ticket, $actor->name, $assigneeNames),
            source: 'helpdesk.ticket.scrum-assigned',
            auditRoute: $panel.'.helpdesks.notifications.whatsapp.scrum-assign',
        );

        SecurityAudit::log('AUDIT_HELPDESK_SCRUM_ASSIGNED', $panel.'.helpdesks.scrum-assign', [
            'panel' => $panel,
            'helpdesk_id' => $ticket->getKey(),
            'updated_by' => $actor->name,
            'developer_ids' => $selectedIds,
        ]);

        return [
            'ok' => true,
            'title' => 'Ticket asignado al sprint',
            'body' => 'El ticket #'.$ticket->getKey().' quedó en análisis con '.$assigneeNames.'.',
        ];
    }

    public static function ticketAllowsContentResubmit(?HelpDesk $ticket): bool
    {
        return $ticket instanceof HelpDesk
            && $ticket->status === HelpdeskTaskStatusOptions::STATUS_REVERTED;
    }

    public static function canCreatorResubmit(HelpDesk $ticket, ?User $user = null): bool
    {
        $user ??= Auth::user() instanceof User ? Auth::user() : null;

        if (! $user instanceof User || ! self::ticketAllowsContentResubmit($ticket)) {
            return false;
        }

        return HelpdeskTicketIdentity::isCreator($ticket, $user);
    }

    public static function creatorResubmitButtonLabel(): string
    {
        return 'Corregir y reenviar';
    }

    public static function creatorResubmitSaveLabel(): string
    {
        return 'Reenviar a '.HelpdeskBusinessScrumRoles::PRODUCT_OWNER_NAME;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareCreatorResubmitData(HelpDesk $ticket, array $data, User $actor): array
    {
        $preserved = $ticket->only($ticket->getFillable());

        foreach (['description', 'priority', 'image', 'ticket_type', 'cc_colaboradores'] as $field) {
            if (array_key_exists($field, $data)) {
                $preserved[$field] = $data[$field];
            }
        }

        $preserved['status'] = HelpdeskTaskStatusOptions::STATUS_PENDING;
        $preserved['cancellation_reason'] = null;
        $preserved['updated_by'] = $actor->name;
        $preserved['created_by'] = $ticket->created_by;
        $preserved['created_by_user_id'] = $ticket->created_by_user_id;

        $hours = HelpdeskSla::hoursForPriority($preserved['priority'] ?? $ticket->priority);
        $now = now();
        $preserved['cancelled_at'] = null;
        $preserved['resolved_at'] = null;
        $preserved['sla_breached_at'] = null;
        $preserved['first_responded_at'] = null;
        $preserved['first_response_due_at'] = $now->copy()->addHours($hours['first_response']);
        $preserved['resolution_due_at'] = $now->copy()->addHours($hours['resolution']);

        return $preserved;
    }

    public static function finalizeCreatorResubmit(HelpDesk $ticket, User $actor, string $panel = 'business'): void
    {
        self::syncProductOwnerInbox($ticket);

        $noteHtml = '<p>El analista <strong>'.e($actor->name).'</strong> corrigió el ticket y lo reenvió al backlog de <strong>'
            .e(HelpdeskBusinessScrumRoles::PRODUCT_OWNER_NAME).'</strong>.</p>';

        HelpdeskObservationAppender::append(
            $ticket,
            $noteHtml,
            $actor->name,
            user: $actor,
            eventType: HelpdeskEventRecorder::TYPE_STATUS_CHANGE,
            meta: [
                'action' => 'scrum_resubmit',
                'from' => HelpdeskTaskStatusOptions::STATUS_REVERTED,
                'to' => HelpdeskTaskStatusOptions::STATUS_PENDING,
            ],
        );

        HelpdeskTicketAssigneeMailService::sendToEachAssigneeWithReport($ticket, $panel);
        HelpdeskTicketAssigneeWhatsAppService::dispatchCustomMessageToEachAssigneeWithReport(
            ticket: $ticket,
            requestedByUserId: $actor->getAuthIdentifier() !== null ? (int) $actor->getAuthIdentifier() : null,
            panel: $panel,
            body: HelpdeskTicketAssigneeWhatsAppService::buildResubmittedToProductOwnerBody($ticket, $actor->name),
            source: 'helpdesk.ticket.scrum-resubmitted',
            auditRoute: $panel.'.helpdesks.notifications.whatsapp.scrum-resubmit',
        );

        SecurityAudit::log('AUDIT_HELPDESK_SCRUM_RESUBMITTED', $panel.'.helpdesks.scrum-resubmit', [
            'panel' => $panel,
            'helpdesk_id' => $ticket->getKey(),
            'updated_by' => $actor->name,
        ]);
    }
}
