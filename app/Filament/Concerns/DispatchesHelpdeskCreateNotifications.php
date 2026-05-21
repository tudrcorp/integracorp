<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\HelpDesk;
use App\Services\HelpdeskTeamMembersWhatsAppService;
use App\Services\HelpdeskTicketAssigneeMailService;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Throwable;

trait DispatchesHelpdeskCreateNotifications
{
    protected function dispatchHelpdeskCreateNotifications(HelpDesk $ticket, string $panel): void
    {
        try {
            $emailReport = HelpdeskTicketAssigneeMailService::sendToEachAssigneeWithReport($ticket, $panel);
            $whatsAppReport = HelpdeskTicketAssigneeWhatsAppService::dispatchToEachAssigneeWithReport($ticket, Auth::id(), $panel);
            $teamWhatsAppReport = HelpdeskTeamMembersWhatsAppService::dispatchToEachTeamMemberWithReport($ticket, Auth::id(), $panel);

            SecurityAudit::log('AUDIT_HELPDESK_TICKET_NOTIFICATIONS_PROCESSED', $panel.'.helpdesks.notifications', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'email_report' => [
                    'total_assignees' => $emailReport['total_assignees'],
                    'attempted' => $emailReport['attempted'],
                    'sent' => $emailReport['sent'],
                    'failed' => $emailReport['failed'],
                    'skipped_no_email' => $emailReport['skipped_no_email'],
                    'failures' => array_slice($emailReport['failures'], 0, 10),
                ],
                'whatsapp_report' => [
                    'total_assignees' => $whatsAppReport['total_assignees'],
                    'attempted' => $whatsAppReport['attempted'],
                    'dispatched' => $whatsAppReport['dispatched'],
                    'failed' => $whatsAppReport['failed'],
                    'skipped_no_phone' => $whatsAppReport['skipped_no_phone'],
                    'failures' => array_slice($whatsAppReport['failures'], 0, 10),
                ],
                'team_whatsapp_report' => [
                    'total_members' => $teamWhatsAppReport['total_members'],
                    'attempted' => $teamWhatsAppReport['attempted'],
                    'dispatched' => $teamWhatsAppReport['dispatched'],
                    'failed' => $teamWhatsAppReport['failed'],
                    'skipped_no_phone' => $teamWhatsAppReport['skipped_no_phone'],
                    'failures' => array_slice($teamWhatsAppReport['failures'], 0, 10),
                ],
            ]);

            SecurityAudit::log('AUDIT_HELPDESK_TICKET_CREATED', $panel.'.helpdesks.create', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'created_by' => $ticket->created_by,
                'status' => $ticket->status,
                'mail_sent_count' => $emailReport['sent'],
                'mail_failed_count' => $emailReport['failed'],
                'whatsapp_dispatched_count' => $whatsAppReport['dispatched'],
                'whatsapp_failed_count' => $whatsAppReport['failed'],
                'team_whatsapp_dispatched_count' => $teamWhatsAppReport['dispatched'],
                'team_whatsapp_failed_count' => $teamWhatsAppReport['failed'],
                'team' => $ticket->team,
            ]);
        } catch (Throwable $th) {
            SecurityAudit::log('AUDIT_HELPDESK_TICKET_CREATE_FAILED', $panel.'.helpdesks.create', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'created_by' => $ticket->created_by,
                'error' => $th->getMessage(),
                'exception' => $th::class,
                'where' => static::class.'::afterCreate',
            ]);
        }
    }
}
