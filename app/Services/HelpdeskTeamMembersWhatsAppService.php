<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendNotificacionWhatsApp;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HelpdeskTeamMembersWhatsAppService
{
    public static function buildWhatsAppBodyForTeamTicket(HelpDesk $ticket): string
    {
        $tz = (string) config('app.timezone');
        $createdAt = $ticket->created_at !== null
            ? $ticket->created_at->timezone($tz)->format('d/m/Y H:i')
            : '—';
        $creator = filled($ticket->created_by) ? (string) $ticket->created_by : '—';
        $priorityLabel = self::priorityLabelForWhatsApp((string) $ticket->priority);
        $description = trim(html_entity_decode(strip_tags((string) $ticket->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($description) > 700) {
            $description = mb_substr($description, 0, 697).'...';
        }
        $ticketNo = (string) $ticket->getKey();
        $teamName = trim((string) ($ticket->team ?? ''));
        $membersList = self::formatTeamMembersForWhatsApp(self::teamMembersFromTicket($ticket));

        return <<<TEXT
        Tiene un ticket de soporte en INTEGRACORP que debe ser resuelto por su equipo de ejecución.

        Ticket N.º {$ticketNo}
        *Equipo:* {$teamName}

        *Integrantes del equipo:*
        {$membersList}

        Creado por: {$creator}
        Fecha y hora: {$createdAt}
        *Prioridad: {$priorityLabel}*

        *{$description}*

        Debe conectarse al sistema INTEGRACORP con su usuario y contraseña para dar inicio a la gestión del ticket en conjunto con su equipo.
        TEXT;
    }

    /**
     * @param  list<array{id?: int, name?: string, telefono_corporativo?: string|null}>  $members
     */
    public static function formatTeamMembersForWhatsApp(array $members): string
    {
        $lines = [];

        foreach ($members as $member) {
            $name = trim((string) ($member['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $lines[] = '• '.$name;
        }

        return $lines !== [] ? implode("\n", $lines) : '—';
    }

    /**
     * @return list<array{id?: int, name?: string, telefono_corporativo?: string|null}>
     */
    public static function teamMembersFromTicket(HelpDesk $ticket): array
    {
        $members = $ticket->team_members;

        return is_array($members) ? $members : [];
    }

    public static function ticketHasExecutionTeam(HelpDesk $ticket): bool
    {
        return filled($ticket->team) && self::teamMembersFromTicket($ticket) !== [];
    }

    /**
     * @return array{
     *     total_members:int,
     *     attempted:int,
     *     dispatched:int,
     *     failed:int,
     *     skipped_no_phone:int,
     *     failures:list<array<string,mixed>>,
     *     recipients:list<array<string,mixed>>
     * }
     */
    public static function dispatchToEachTeamMemberWithReport(HelpDesk $ticket, ?int $requestedByUserId, string $panel = 'unknown'): array
    {
        $report = [
            'total_members' => 0,
            'attempted' => 0,
            'dispatched' => 0,
            'failed' => 0,
            'skipped_no_phone' => 0,
            'failures' => [],
            'recipients' => [],
        ];

        if (! self::ticketHasExecutionTeam($ticket)) {
            return $report;
        }

        $members = self::teamMembersFromTicket($ticket);
        $report['total_members'] = count($members);
        $body = self::buildWhatsAppBodyForTeamTicket($ticket);
        $auditRoute = $panel.'.helpdesks.notifications.whatsapp.team';
        $source = 'helpdesk.ticket.create.team';

        if ($requestedByUserId === null) {
            $report['failed'] = $report['total_members'];
            $report['failures'][] = [
                'reason' => 'missing_authenticated_user',
                'where' => 'service.whatsapp.team.dispatch',
            ];

            SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_DISPATCH_FAILED', $auditRoute, [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'team' => $ticket->team,
                'reason' => 'missing_authenticated_user',
                'where' => 'service.whatsapp.team.dispatch',
                'source' => $source,
            ]);

            return $report;
        }

        foreach ($members as $member) {
            $phoneData = self::resolveTeamMemberPhoneData($member);
            $memberId = $phoneData['rrhh_colaborador_id'];
            $memberName = $phoneData['member_name'];
            $rawPhone = $phoneData['raw_phone'];
            $phone = $phoneData['phone'];

            if ($phone === null) {
                $report['skipped_no_phone']++;

                Log::warning('Helpdesk: integrante de equipo sin teléfono válido; no se envía WhatsApp.', [
                    'help_desk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $memberId,
                    'member_name' => $memberName,
                    'raw_phone' => $rawPhone,
                    'where' => 'HelpdeskTeamMembersWhatsAppService::dispatchToEachTeamMemberWithReport',
                ]);

                SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_SKIPPED', $auditRoute, [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'team' => $ticket->team,
                    'rrhh_colaborador_id' => $memberId,
                    'member_name' => $memberName,
                    'raw_phone' => $rawPhone,
                    'reason' => 'missing_or_invalid_phone',
                    'where' => 'service.whatsapp.team.skip.invalid-phone',
                    'source' => $source,
                ]);

                continue;
            }

            $report['attempted']++;

            try {
                SendNotificacionWhatsApp::dispatch($requestedByUserId, $body, $phone, null, [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'team' => $ticket->team,
                    'rrhh_colaborador_id' => $memberId,
                    'member_name' => $memberName,
                    'phone' => $phone,
                    'source' => $source,
                ]);

                $report['dispatched']++;
                $report['recipients'][] = [
                    'rrhh_colaborador_id' => $memberId,
                    'member_name' => $memberName,
                    'phone' => $phone,
                ];

                SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_DISPATCHED', $auditRoute, [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'team' => $ticket->team,
                    'rrhh_colaborador_id' => $memberId,
                    'member_name' => $memberName,
                    'phone' => $phone,
                    'where' => 'service.whatsapp.team.dispatch',
                    'source' => $source,
                ]);
            } catch (Throwable $th) {
                $report['failed']++;
                $report['failures'][] = [
                    'rrhh_colaborador_id' => $memberId,
                    'member_name' => $memberName,
                    'phone' => $phone,
                    'error' => $th->getMessage(),
                    'exception' => $th::class,
                    'where' => 'service.whatsapp.team.dispatch',
                ];

                SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_DISPATCH_FAILED', $auditRoute, [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'team' => $ticket->team,
                    'rrhh_colaborador_id' => $memberId,
                    'member_name' => $memberName,
                    'phone' => $phone,
                    'error' => $th->getMessage(),
                    'exception' => $th::class,
                    'where' => 'service.whatsapp.team.dispatch',
                    'source' => $source,
                ]);

                Log::error('Helpdesk: error al despachar WhatsApp a integrante del equipo.', [
                    'help_desk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $memberId,
                    'member_name' => $memberName,
                    'phone' => $phone,
                    'error' => $th->getMessage(),
                    'exception' => $th::class,
                    'where' => 'HelpdeskTeamMembersWhatsAppService::dispatchToEachTeamMemberWithReport',
                ]);
            }
        }

        return $report;
    }

    /**
     * @param  array{id?: int, name?: string, telefono_corporativo?: string|null}  $member
     * @return array{
     *     rrhh_colaborador_id:int|null,
     *     member_name:string,
     *     raw_phone:string|null,
     *     phone:string|null
     * }
     */
    private static function resolveTeamMemberPhoneData(array $member): array
    {
        $memberId = isset($member['id']) ? (int) $member['id'] : null;
        $memberName = trim((string) ($member['name'] ?? ''));
        $rawPhone = is_string($member['telefono_corporativo'] ?? null)
            ? trim((string) $member['telefono_corporativo'])
            : null;

        if (($rawPhone === null || $rawPhone === '') && $memberId !== null) {
            $colaborador = RrhhColaborador::query()
                ->find($memberId, ['id', 'fullName', 'telefonoCorporativo', 'telefono']);

            if ($colaborador instanceof RrhhColaborador) {
                if ($memberName === '') {
                    $memberName = trim((string) $colaborador->fullName);
                }

                $rawPhone = filled($colaborador->telefonoCorporativo)
                    ? trim((string) $colaborador->telefonoCorporativo)
                    : (filled($colaborador->telefono) ? trim((string) $colaborador->telefono) : null);
            }
        }

        $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

        return [
            'rrhh_colaborador_id' => $memberId,
            'member_name' => $memberName,
            'raw_phone' => $rawPhone,
            'phone' => $phone,
        ];
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
}
