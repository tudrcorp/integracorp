<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HelpdeskTicketMailException;
use App\Mail\SendEmailCreateTicketAndAssigned;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class HelpdeskTicketAssigneeMailService
{
    private const STATIC_CC_SOLRODRIGUEZ = 'solrodriguez@tudrencasa.com';

    /**
     * CC fijo + correos corporativos de colaboradores en «CC (Opcional)» del ticket.
     * No duplica el destinatario principal (To) en la lista de CC.
     *
     * @return list<string>
     */
    public static function buildCcEmailListForAssigneeMessage(HelpDesk $ticket, string $primaryToEmail): array
    {
        $primary = strtolower(trim($primaryToEmail));

        $cc = [self::STATIC_CC_SOLRODRIGUEZ];

        $ids = $ticket->getAttribute('cc_colaboradores');
        if (is_array($ids) && $ids !== []) {
            $extra = RrhhColaborador::query()
                ->whereIn('id', $ids)
                ->pluck('emailCorporativo')
                ->filter(fn (?string $e): bool => filled($e))
                ->map(fn (string $e): string => strtolower(trim($e)))
                ->reject(fn (string $e): bool => $e === $primary)
                ->unique()
                ->values()
                ->all();
            $cc = [...$cc, ...$extra];
        }

        $cc = array_values(array_unique(array_filter($cc, fn (string $e): bool => $e !== '' && $e !== $primary)));

        if ($cc === []) {
            return [self::STATIC_CC_SOLRODRIGUEZ];
        }

        return $cc;
    }

    /**
     * Vuelve a leer el ticket con asignados desde la BD. Tras crear en Filament, el modelo en memoria
     * puede tener `rrhhColaboradores` vacío u obsoleto; `loadMissing` no recarga si la relación ya está cargada.
     */
    public static function loadTicketWithAssigneesForNotifications(HelpDesk $record): HelpDesk
    {
        $fresh = HelpDesk::query()
            ->with('rrhhColaboradores')
            ->find($record->getKey());

        return $fresh ?? $record;
    }

    /**
     * Envía un correo a cada colaborador asignado al ticket (cada uno en To: con saludo propio).
     * Un fallo en un destinatario no impide el envío al resto.
     *
     * @return int Número de envíos despachados (encolados si el mailer usa cola).
     */
    public static function sendToEachAssignee(HelpDesk $ticket): int
    {
        $report = self::sendToEachAssigneeWithReport($ticket);

        return (int) ($report['sent'] ?? 0);
    }

    /**
     * Envía correo a cada asignado y retorna un reporte detallado para auditoría operativa.
     *
     * @return array{
     *     total_assignees:int,
     *     attempted:int,
     *     sent:int,
     *     failed:int,
     *     skipped_no_email:int,
     *     failures:list<array<string,mixed>>,
     *     recipients:list<array<string,mixed>>
     * }
     */
    public static function sendToEachAssigneeWithReport(HelpDesk $ticket, string $panel = 'unknown'): array
    {
        $ticket = self::loadTicketWithAssigneesForNotifications($ticket);

        $report = [
            'total_assignees' => (int) $ticket->rrhhColaboradores->count(),
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped_no_email' => 0,
            'failures' => [],
            'recipients' => [],
        ];

        if ($ticket->rrhhColaboradores->isEmpty()) {
            return $report;
        }

        foreach ($ticket->rrhhColaboradores as $colaborador) {
            $emailCorporativo = $colaborador->emailCorporativo;

            if (blank($emailCorporativo)) {
                $report['skipped_no_email']++;

                Log::warning('Helpdesk: colaborador asignado sin correo corporativo; no se envía notificación.', [
                    'help_desk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'where' => 'HelpdeskTicketAssigneeMailService::sendToEachAssigneeWithReport',
                ]);

                SecurityAudit::log('AUDIT_HELPDESK_EMAIL_SKIPPED', $panel.'.helpdesks.notifications.email', [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'rrhh_colaborador_name' => $colaborador->fullName,
                    'reason' => 'missing_corporate_email',
                    'where' => 'service.email.skip.no-email',
                ]);

                continue;
            }

            $report['attempted']++;

            try {
                $ccList = self::buildCcEmailListForAssigneeMessage($ticket, $emailCorporativo);

                Mail::to($emailCorporativo)
                    ->cc($ccList)
                    ->send(SendEmailCreateTicketAndAssigned::fromTicket($ticket, $colaborador));
                $report['sent']++;
                $report['recipients'][] = [
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'email' => $emailCorporativo,
                ];

                SecurityAudit::log('AUDIT_HELPDESK_EMAIL_SENT', $panel.'.helpdesks.notifications.email', [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'rrhh_colaborador_name' => $colaborador->fullName,
                    'to' => $emailCorporativo,
                    'cc_count' => count($ccList),
                    'where' => 'service.email.send',
                ]);

            } catch (HelpdeskTicketMailException $e) {
                $report['failed']++;
                $report['failures'][] = [
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'email' => $emailCorporativo,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                    'where' => 'service.email.prepare',
                ];

                Log::error('Helpdesk: no se pudo preparar o enviar correo a un asignado.', array_merge(
                    $e->context,
                    [
                        'message' => $e->getMessage(),
                        'help_desk_id' => $ticket->getKey(),
                        'rrhh_colaborador_id' => $colaborador->getKey(),
                        'where' => 'HelpdeskTicketAssigneeMailService::sendToEachAssigneeWithReport',
                    ],
                ));

                SecurityAudit::log('AUDIT_HELPDESK_EMAIL_FAILED', $panel.'.helpdesks.notifications.email', [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'to' => $emailCorporativo,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                    'where' => 'service.email.prepare',
                ]);
            } catch (Throwable $e) {
                $report['failed']++;
                $report['failures'][] = [
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'email' => $emailCorporativo,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                    'where' => 'service.email.send',
                ];

                Log::error('Helpdesk: error al enviar correo a un asignado.', [
                    'message' => $e->getMessage(),
                    'help_desk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'exception' => $e::class,
                    'where' => 'HelpdeskTicketAssigneeMailService::sendToEachAssigneeWithReport',
                ]);

                SecurityAudit::log('AUDIT_HELPDESK_EMAIL_FAILED', $panel.'.helpdesks.notifications.email', [
                    'panel' => $panel,
                    'helpdesk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'to' => $emailCorporativo,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                    'where' => 'service.email.send',
                ]);
            }
        }

        return $report;
    }
}
