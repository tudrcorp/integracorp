<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendNotificacionWhatsApp;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class HelpdeskTicketAssigneeWhatsAppService
{
    public static function buildWhatsAppBodyForAssignedTicket(HelpDesk $ticket): string
    {
        $tz = (string) config('app.timezone');
        $createdAt = $ticket->created_at !== null
            ? $ticket->created_at->timezone($tz)->format('d/m/Y H:i')
            : '—';
        $creator = filled($ticket->created_by) ? (string) $ticket->created_by : '—';
        $priorityLabel = self::priorityLabelForWhatsApp((string) $ticket->priority);
        $description = trim(html_entity_decode(strip_tags((string) $ticket->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($description) > 900) {
            $description = mb_substr($description, 0, 897).'...';
        }
        $ticketNo = (string) $ticket->getKey();

        return <<<TEXT
        Le han asignado un ticket de soporte en INTEGRACORP.

        Ticket N.º {$ticketNo}
        Creado por: {$creator}
        Fecha y hora: {$createdAt}
        *Prioridad: {$priorityLabel}*

        *{$description}*

        Debe conectarse al sistema INTEGRACORP con su usuario y contraseña para dar inicio a la gestión del ticket.
        TEXT;
    }

    public static function normalizePhoneForWhatsApp(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '58') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+58'.substr($digits, 1);
        }

        if (str_starts_with($digits, '4') && strlen($digits) === 10) {
            return '+58'.$digits;
        }

        if (str_starts_with($digits, '58') && strlen($digits) > 12) {
            return '+'.$digits;
        }

        return str_starts_with($digits, '+') ? $digits : '+'.$digits;
    }

    public static function buildStatusUpdatedBody(HelpDesk $ticket, string $previousStatus, string $newStatus, string $updatedBy): string
    {
        $tz = (string) config('app.timezone');
        $updatedAt = now()->timezone($tz)->format('d/m/Y H:i');
        $ticketNo = (string) $ticket->getKey();
        $description = trim(html_entity_decode(strip_tags((string) $ticket->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($description) > 350) {
            $description = mb_substr($description, 0, 347).'...';
        }

        return <<<TEXT
        Actualización de estatus en ticket de soporte INTEGRACORP.

        Ticket N.º {$ticketNo}
        Estado anterior: {$previousStatus}
        Estado actual: {$newStatus}
        Actualizado por: {$updatedBy}
        Fecha y hora: {$updatedAt}

        {$description}

        Debe conectarse al sistema INTEGRACORP con su usuario y contraseña para continuar la gestión.
        TEXT;
    }

    public static function buildNoteAddedBody(HelpDesk $ticket, string $addedBy, string $noteHtml): string
    {
        $tz = (string) config('app.timezone');
        $createdAt = now()->timezone($tz)->format('d/m/Y H:i');
        $ticketNo = (string) $ticket->getKey();
        $notePlain = trim(html_entity_decode(strip_tags($noteHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($notePlain) > 500) {
            $notePlain = mb_substr($notePlain, 0, 497).'...';
        }

        return <<<TEXT
        Nueva nota registrada en ticket de soporte INTEGRACORP.

        Ticket N.º {$ticketNo}
        Estado actual: {$ticket->status}
        Nota añadida por: {$addedBy}
        Fecha y hora: {$createdAt}

        *Actualizacion:*
        *{$notePlain}*

        Debe conectarse al sistema INTEGRACORP con su usuario y contraseña para revisar el seguimiento.
        TEXT;
    }

    public static function buildTicketClosedByCreatorBody(HelpDesk $ticket, string $closedBy): string
    {
        $tz = (string) config('app.timezone');
        $closedAt = now()->timezone($tz)->format('d/m/Y H:i');
        $ticketNo = (string) $ticket->getKey();
        $description = trim(html_entity_decode(strip_tags((string) $ticket->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($description) > 350) {
            $description = mb_substr($description, 0, 347).'...';
        }

        return <<<TEXT
        El creador del ticket ha marcado el caso como TERMINADO.

        Ticket N.º {$ticketNo}
        Cerrado por: {$closedBy}
        Fecha y hora de cierre: {$closedAt}
        Estado actual: TERMINADO

        {$description}

        Si necesitas ampliar la gestión, debes comunicarte con el creador del ticket.
        TEXT;
    }

    public static function buildPriorityUpdatedByCreatorBody(
        HelpDesk $ticket,
        string $previousPriority,
        string $newPriority,
        string $updatedBy,
    ): string {
        $tz = (string) config('app.timezone');
        $updatedAt = now()->timezone($tz)->format('d/m/Y H:i');
        $ticketNo = (string) $ticket->getKey();
        $prevLabel = self::priorityLabelForWhatsApp($previousPriority);
        $newLabel = self::priorityLabelForWhatsApp($newPriority);
        $description = trim(html_entity_decode(strip_tags((string) $ticket->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($description) > 350) {
            $description = mb_substr($description, 0, 347).'...';
        }

        return <<<TEXT
        El creador del ticket ha actualizado la prioridad en INTEGRACORP.

        Ticket N.º {$ticketNo}
        Prioridad anterior: {$prevLabel}
        Prioridad nueva: {$newLabel}
        Estado del ticket: {$ticket->status}
        Actualizado por: {$updatedBy}
        Fecha y hora: {$updatedAt}

        {$description}

        Conéctese al sistema INTEGRACORP para revisar el ticket.
        TEXT;
    }

    private static function priorityLabelForWhatsApp(string $priority): string
    {
        $priorityRaw = strtoupper(trim($priority));

        return match ($priorityRaw) {
            'BAJA' => 'Baja — puede esperar',
            'MEDIA' => 'Media — flujo normal',
            'ALTA' => 'Alta — bloquea trabajo',
            default => filled($priority) ? $priority : '—',
        };
    }

    /**
     * @return array{
     *     total_assignees:int,
     *     attempted:int,
     *     dispatched:int,
     *     failed:int,
     *     skipped_no_phone:int,
     *     failures:list<array<string,mixed>>,
     *     recipients:list<array<string,mixed>>
     * }
     */
    public static function dispatchToEachAssigneeWithReport(HelpDesk $ticket, ?int $requestedByUserId, string $panel = 'unknown'): array
    {
        $body = self::buildWhatsAppBodyForAssignedTicket($ticket);

        return self::dispatchCustomMessageToEachAssigneeWithReport(
            ticket: $ticket,
            requestedByUserId: $requestedByUserId,
            panel: $panel,
            body: $body,
            source: 'helpdesk.ticket.create',
            auditRoute: $panel.'.helpdesks.notifications.whatsapp',
        );
    }

    /**
     * @return array{
     *     attempted:int,
     *     dispatched:int,
     *     failed:int,
     *     skipped_no_phone:int,
     *     failures:list<array<string,mixed>>,
     *     recipient:array<string,mixed>|null
     * }
     */
    public static function dispatchToTicketCreatorWithReport(
        HelpDesk $ticket,
        ?int $requestedByUserId,
        string $panel,
        string $body,
        string $source,
        string $auditRoute
    ): array {
        $ticket = HelpdeskTicketAssigneeMailService::loadTicketWithAssigneesForNotifications($ticket);

        $report = [
            'attempted' => 0,
            'dispatched' => 0,
            'failed' => 0,
            'skipped_no_phone' => 0,
            'failures' => [],
            'recipient' => null,
        ];

        if ($requestedByUserId === null) {
            $report['failed'] = 1;
            $report['failures'][] = [
                'reason' => 'missing_authenticated_user',
                'where' => 'service.whatsapp.dispatch.creator',
            ];

            SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_DISPATCH_FAILED', $auditRoute, [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'reason' => 'missing_authenticated_user',
                'where' => 'service.whatsapp.dispatch.creator',
                'source' => $source,
            ]);

            return $report;
        }

        $creatorRecipient = self::resolveTicketCreatorPhoneData($ticket);
        $creatorName = (string) ($creatorRecipient['creator_name'] ?? '');
        $creatorUserId = $creatorRecipient['user_id'] ?? null;
        $creatorColaboradorId = $creatorRecipient['rrhh_colaborador_id'] ?? null;
        $rawPhone = $creatorRecipient['raw_phone'] ?? null;
        $phone = $creatorRecipient['phone'] ?? null;
        $resolutionSource = (string) ($creatorRecipient['resolution_source'] ?? 'unknown');

        if ($phone === null) {
            $report['skipped_no_phone'] = 1;
            $report['recipient'] = [
                'creator_name' => $creatorName,
                'user_id' => $creatorUserId,
                'rrhh_colaborador_id' => $creatorColaboradorId,
                'raw_phone' => $rawPhone,
                'resolution_source' => $resolutionSource,
            ];

            SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_SKIPPED', $auditRoute, [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'creator_name' => $creatorName,
                'user_id' => $creatorUserId,
                'rrhh_colaborador_id' => $creatorColaboradorId,
                'raw_phone' => $rawPhone,
                'reason' => 'creator_missing_or_invalid_phone',
                'where' => 'service.whatsapp.skip.creator.invalid-phone',
                'resolution_source' => $resolutionSource,
                'source' => $source,
            ]);

            return $report;
        }

        $report['attempted'] = 1;

        try {
            SendNotificacionWhatsApp::dispatch($requestedByUserId, $body, $phone, null, [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'creator_name' => $creatorName,
                'user_id' => $creatorUserId,
                'rrhh_colaborador_id' => $creatorColaboradorId,
                'phone' => $phone,
                'resolution_source' => $resolutionSource,
                'source' => $source,
            ]);

            $report['dispatched'] = 1;
            $report['recipient'] = [
                'creator_name' => $creatorName,
                'user_id' => $creatorUserId,
                'rrhh_colaborador_id' => $creatorColaboradorId,
                'phone' => $phone,
                'resolution_source' => $resolutionSource,
            ];

            SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_DISPATCHED', $auditRoute, [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'creator_name' => $creatorName,
                'user_id' => $creatorUserId,
                'rrhh_colaborador_id' => $creatorColaboradorId,
                'phone' => $phone,
                'where' => 'service.whatsapp.dispatch.creator',
                'resolution_source' => $resolutionSource,
                'source' => $source,
            ]);
        } catch (Throwable $th) {
            $report['failed'] = 1;
            $report['failures'][] = [
                'creator_name' => $creatorName,
                'user_id' => $creatorUserId,
                'rrhh_colaborador_id' => $creatorColaboradorId,
                'phone' => $phone,
                'error' => $th->getMessage(),
                'exception' => $th::class,
                'where' => 'service.whatsapp.dispatch.creator',
                'resolution_source' => $resolutionSource,
            ];

            SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_DISPATCH_FAILED', $auditRoute, [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'creator_name' => $creatorName,
                'user_id' => $creatorUserId,
                'rrhh_colaborador_id' => $creatorColaboradorId,
                'phone' => $phone,
                'error' => $th->getMessage(),
                'exception' => $th::class,
                'where' => 'service.whatsapp.dispatch.creator',
                'resolution_source' => $resolutionSource,
                'source' => $source,
            ]);
        }

        return $report;
    }

    /**
     * @return array{
     *     creator_name:string,
     *     user_id:int|null,
     *     rrhh_colaborador_id:int|null,
     *     raw_phone:string|null,
     *     phone:string|null,
     *     resolution_source:string
     * }
     */
    private static function resolveTicketCreatorPhoneData(HelpDesk $ticket): array
    {
        $creatorName = trim((string) ($ticket->created_by ?? ''));
        $creatorNameNormalized = preg_replace('/\s+/', ' ', $creatorName) ?? $creatorName;
        $creatorNameLower = Str::lower($creatorNameNormalized);

        $creatorUser = null;
        if ($creatorNameNormalized !== '') {
            if (is_numeric($creatorNameNormalized)) {
                $creatorUser = User::query()->find((int) $creatorNameNormalized, ['id', 'name', 'email', 'phone']);
            }

            if ($creatorUser === null) {
                $creatorUser = User::query()
                    ->where('email', $creatorNameNormalized)
                    ->first(['id', 'name', 'email', 'phone']);
            }

            if ($creatorUser === null) {
                $creatorUser = User::query()
                    ->whereRaw('LOWER(name) = ?', [$creatorNameLower])
                    ->first(['id', 'name', 'email', 'phone']);
            }
        }

        $creatorColaborador = null;
        if ($creatorUser !== null) {
            $creatorColaborador = RrhhColaborador::query()
                ->where('user_id', $creatorUser->id)
                ->first(['id', 'fullName', 'telefonoCorporativo', 'telefono']);
        }

        if ($creatorColaborador === null && $creatorNameNormalized !== '') {
            $creatorColaborador = RrhhColaborador::query()
                ->whereRaw('LOWER(fullName) = ?', [$creatorNameLower])
                ->first(['id', 'fullName', 'telefonoCorporativo', 'telefono']);
        }

        $rawPhone = null;
        $resolutionSource = 'none';

        $candidatePhones = [
            'rrhh.telefonoCorporativo' => $creatorColaborador?->telefonoCorporativo,
            'rrhh.telefono' => $creatorColaborador?->telefono,
            'user.phone' => $creatorUser?->phone,
        ];

        foreach ($candidatePhones as $source => $candidatePhone) {
            $candidate = is_string($candidatePhone) ? trim($candidatePhone) : null;
            $normalized = self::normalizePhoneForWhatsApp($candidate);
            if ($normalized !== null) {
                $rawPhone = $candidate;
                $resolutionSource = $source;

                return [
                    'creator_name' => $creatorNameNormalized,
                    'user_id' => $creatorUser?->getKey(),
                    'rrhh_colaborador_id' => $creatorColaborador?->getKey(),
                    'raw_phone' => $rawPhone,
                    'phone' => $normalized,
                    'resolution_source' => $resolutionSource,
                ];
            }
        }

        $rawPhone = is_string($creatorColaborador?->telefonoCorporativo) && trim($creatorColaborador->telefonoCorporativo) !== ''
            ? trim($creatorColaborador->telefonoCorporativo)
            : (is_string($creatorColaborador?->telefono) && trim($creatorColaborador->telefono) !== ''
                ? trim($creatorColaborador->telefono)
                : (is_string($creatorUser?->phone) ? trim((string) $creatorUser->phone) : null));

        return [
            'creator_name' => $creatorNameNormalized,
            'user_id' => $creatorUser?->getKey(),
            'rrhh_colaborador_id' => $creatorColaborador?->getKey(),
            'raw_phone' => $rawPhone,
            'phone' => null,
            'resolution_source' => $resolutionSource,
        ];
    }

    /**
     * @return array{
     *     total_assignees:int,
     *     attempted:int,
     *     dispatched:int,
     *     failed:int,
     *     skipped_no_phone:int,
     *     failures:list<array<string,mixed>>,
     *     recipients:list<array<string,mixed>>
     * }
     */
    public static function dispatchCustomMessageToEachAssigneeWithReport(
        HelpDesk $ticket,
        ?int $requestedByUserId,
        string $panel,
        string $body,
        string $source,
        string $auditRoute
    ): array {
        $ticket = HelpdeskTicketAssigneeMailService::loadTicketWithAssigneesForNotifications($ticket);

        $report = [
            'total_assignees' => (int) $ticket->rrhhColaboradores->count(),
            'attempted' => 0,
            'dispatched' => 0,
            'failed' => 0,
            'skipped_no_phone' => 0,
            'failures' => [],
            'recipients' => [],
        ];

        if ($ticket->rrhhColaboradores->isEmpty()) {
            return $report;
        }

        if ($requestedByUserId === null) {
            $report['failed'] = $report['total_assignees'];
            $report['failures'][] = [
                'reason' => 'missing_authenticated_user',
                'where' => 'service.whatsapp.dispatch',
            ];

            SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_DISPATCH_FAILED', $auditRoute, [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'reason' => 'missing_authenticated_user',
                'where' => 'service.whatsapp.dispatch',
                'source' => $source,
            ]);

            return $report;
        }

        foreach ($ticket->rrhhColaboradores as $colaborador) {
            $rawPhone = $colaborador->telefonoCorporativo ?: $colaborador->telefono;
            $phone = self::normalizePhoneForWhatsApp(is_string($rawPhone) ? trim($rawPhone) : null);

            if ($phone === null) {
                $report['skipped_no_phone']++;

                Log::warning('Helpdesk: colaborador asignado sin teléfono válido; no se envía WhatsApp.', [
                    'help_desk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'raw_phone' => $rawPhone,
                    'where' => 'HelpdeskTicketAssigneeWhatsAppService::dispatchToEachAssigneeWithReport',
                ]);

                SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_SKIPPED', $auditRoute, [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'rrhh_colaborador_name' => $colaborador->fullName,
                    'raw_phone' => $rawPhone,
                    'reason' => 'missing_or_invalid_phone',
                    'where' => 'service.whatsapp.skip.invalid-phone',
                    'source' => $source,
                ]);

                continue;
            }

            $report['attempted']++;

            try {
                SendNotificacionWhatsApp::dispatch($requestedByUserId, $body, $phone, null, [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'rrhh_colaborador_name' => $colaborador->fullName,
                    'phone' => $phone,
                    'source' => $source,
                ]);

                $report['dispatched']++;
                $report['recipients'][] = [
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'phone' => $phone,
                ];

                SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_DISPATCHED', $auditRoute, [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'rrhh_colaborador_name' => $colaborador->fullName,
                    'phone' => $phone,
                    'where' => 'service.whatsapp.dispatch',
                    'source' => $source,
                ]);
            } catch (Throwable $th) {
                $report['failed']++;
                $report['failures'][] = [
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'phone' => $phone,
                    'error' => $th->getMessage(),
                    'exception' => $th::class,
                    'where' => 'service.whatsapp.dispatch',
                ];

                SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_DISPATCH_FAILED', $auditRoute, [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'phone' => $phone,
                    'error' => $th->getMessage(),
                    'exception' => $th::class,
                    'where' => 'service.whatsapp.dispatch',
                    'source' => $source,
                ]);

                Log::error('Helpdesk: error al despachar WhatsApp a colaborador asignado.', [
                    'help_desk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'phone' => $phone,
                    'error' => $th->getMessage(),
                    'exception' => $th::class,
                    'where' => 'HelpdeskTicketAssigneeWhatsAppService::dispatchToEachAssigneeWithReport',
                ]);
            }
        }

        return $report;
    }
}
