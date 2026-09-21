<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HelpdeskTicketMailException;
use App\Mail\SendEmailCreateTicketAndAssigned;
use App\Mail\SendEmailHelpdeskTicketReverted;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
     * @param  list<int>|null  $onlyColaboradorIds
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
    public static function sendToEachAssigneeWithReport(
        HelpDesk $ticket,
        string $panel = 'unknown',
        ?array $onlyColaboradorIds = null,
        bool $isReassignment = false,
    ): array {
        $ticket = self::loadTicketWithAssigneesForNotifications($ticket);

        $colaboradores = $ticket->rrhhColaboradores;

        if ($onlyColaboradorIds !== null) {
            $ids = array_map(static fn (int|string $id): int => (int) $id, $onlyColaboradorIds);
            $colaboradores = $colaboradores->filter(
                fn (RrhhColaborador $colaborador): bool => in_array((int) $colaborador->getKey(), $ids, true)
            )->values();
        }

        $report = [
            'total_assignees' => (int) $colaboradores->count(),
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped_no_email' => 0,
            'failures' => [],
            'recipients' => [],
        ];

        if ($colaboradores->isEmpty()) {
            return $report;
        }

        foreach ($colaboradores as $colaborador) {
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
                    ->send(SendEmailCreateTicketAndAssigned::fromTicket($ticket, $colaborador, $isReassignment));
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

    /**
     * Envía el correo de reversión Scrum al creador del ticket.
     *
     * @return array{
     *     attempted:int,
     *     sent:int,
     *     failed:int,
     *     skipped_no_email:int,
     *     failures:list<array<string,mixed>>,
     *     recipient:array<string,mixed>|null
     * }
     */
    public static function sendRevertedToCreatorWithReport(
        HelpDesk $ticket,
        string $revertedBy,
        string $reason,
        string $panel = 'unknown',
    ): array {
        $ticket = self::loadTicketWithAssigneesForNotifications($ticket);
        $creator = self::resolveTicketCreatorEmailData($ticket);

        $report = [
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped_no_email' => 0,
            'failures' => [],
            'recipient' => [
                'creator_name' => $creator['creator_name'],
                'user_id' => $creator['user_id'],
                'rrhh_colaborador_id' => $creator['rrhh_colaborador_id'],
                'resolution_source' => $creator['resolution_source'],
            ],
        ];

        $email = $creator['email'];

        if (blank($email)) {
            $report['skipped_no_email'] = 1;

            Log::warning('Helpdesk: creador del ticket sin correo; no se envía notificación de reversión.', [
                'help_desk_id' => $ticket->getKey(),
                'user_id' => $creator['user_id'],
                'rrhh_colaborador_id' => $creator['rrhh_colaborador_id'],
                'where' => 'HelpdeskTicketAssigneeMailService::sendRevertedToCreatorWithReport',
            ]);

            SecurityAudit::log('AUDIT_HELPDESK_EMAIL_SKIPPED', $panel.'.helpdesks.notifications.email.scrum-revert', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'creator_name' => $creator['creator_name'],
                'user_id' => $creator['user_id'],
                'rrhh_colaborador_id' => $creator['rrhh_colaborador_id'],
                'reason' => 'creator_missing_email',
                'where' => 'service.email.skip.creator.no-email',
            ]);

            return $report;
        }

        $report['attempted'] = 1;

        try {
            $ccList = self::buildCcEmailListForAssigneeMessage($ticket, $email);

            Mail::to($email)
                ->cc($ccList)
                ->send(SendEmailHelpdeskTicketReverted::fromTicket(
                    $ticket,
                    $revertedBy,
                    $reason,
                    (string) $creator['creator_name'],
                ));

            $report['sent'] = 1;
            $report['recipient']['email'] = $email;

            SecurityAudit::log('AUDIT_HELPDESK_EMAIL_SENT', $panel.'.helpdesks.notifications.email.scrum-revert', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'creator_name' => $creator['creator_name'],
                'user_id' => $creator['user_id'],
                'rrhh_colaborador_id' => $creator['rrhh_colaborador_id'],
                'to' => $email,
                'cc_count' => count($ccList),
                'where' => 'service.email.send.creator.revert',
                'resolution_source' => $creator['resolution_source'],
            ]);
        } catch (HelpdeskTicketMailException $e) {
            $report['failed'] = 1;
            $report['failures'][] = [
                'email' => $email,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'where' => 'service.email.prepare.creator.revert',
            ];

            Log::error('Helpdesk: no se pudo preparar o enviar correo de reversión al creador.', array_merge(
                $e->context,
                [
                    'message' => $e->getMessage(),
                    'help_desk_id' => $ticket->getKey(),
                    'where' => 'HelpdeskTicketAssigneeMailService::sendRevertedToCreatorWithReport',
                ],
            ));

            SecurityAudit::log('AUDIT_HELPDESK_EMAIL_FAILED', $panel.'.helpdesks.notifications.email.scrum-revert', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'to' => $email,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'where' => 'service.email.prepare.creator.revert',
            ]);
        } catch (Throwable $e) {
            $report['failed'] = 1;
            $report['failures'][] = [
                'email' => $email,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'where' => 'service.email.send.creator.revert',
            ];

            Log::error('Helpdesk: error al enviar correo de reversión al creador.', [
                'message' => $e->getMessage(),
                'help_desk_id' => $ticket->getKey(),
                'exception' => $e::class,
                'where' => 'HelpdeskTicketAssigneeMailService::sendRevertedToCreatorWithReport',
            ]);

            SecurityAudit::log('AUDIT_HELPDESK_EMAIL_FAILED', $panel.'.helpdesks.notifications.email.scrum-revert', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'to' => $email,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'where' => 'service.email.send.creator.revert',
            ]);
        }

        return $report;
    }

    /**
     * @return array{
     *     creator_name:string,
     *     user_id:int|null,
     *     rrhh_colaborador_id:int|null,
     *     email:?string,
     *     resolution_source:string
     * }
     */
    public static function resolveTicketCreatorEmailData(HelpDesk $ticket): array
    {
        $creatorName = trim((string) ($ticket->created_by ?? ''));
        $creatorNameNormalized = preg_replace('/\s+/', ' ', $creatorName) ?? $creatorName;
        $creatorNameLower = Str::lower($creatorNameNormalized);

        $creatorUser = null;
        $creatorUserId = $ticket->created_by_user_id;
        if (is_numeric($creatorUserId) && (int) $creatorUserId > 0) {
            $creatorUser = User::query()->find((int) $creatorUserId, ['id', 'name', 'email']);
        }

        if ($creatorUser === null && $creatorNameNormalized !== '') {
            if (is_numeric($creatorNameNormalized)) {
                $creatorUser = User::query()->find((int) $creatorNameNormalized, ['id', 'name', 'email']);
            }

            if ($creatorUser === null) {
                $creatorUser = User::query()
                    ->where('email', $creatorNameNormalized)
                    ->first(['id', 'name', 'email']);
            }

            if ($creatorUser === null) {
                $creatorUser = User::query()
                    ->whereRaw('LOWER(name) = ?', [$creatorNameLower])
                    ->first(['id', 'name', 'email']);
            }
        }

        $creatorColaborador = null;
        if ($creatorUser !== null) {
            $creatorColaborador = RrhhColaborador::query()
                ->where('user_id', $creatorUser->id)
                ->first(['id', 'fullName', 'emailCorporativo', 'emailPersonal', 'emailAlternativo']);
        }

        if ($creatorColaborador === null && $creatorNameNormalized !== '') {
            $creatorColaborador = RrhhColaborador::query()
                ->whereRaw('LOWER(fullName) = ?', [$creatorNameLower])
                ->first(['id', 'fullName', 'emailCorporativo', 'emailPersonal', 'emailAlternativo']);
        }

        $candidates = [
            'rrhh.emailCorporativo' => $creatorColaborador?->emailCorporativo,
            'user.email' => $creatorUser?->email,
            'rrhh.emailPersonal' => $creatorColaborador?->emailPersonal,
            'rrhh.emailAlternativo' => $creatorColaborador?->emailAlternativo,
        ];

        foreach ($candidates as $source => $candidate) {
            $email = is_string($candidate) ? strtolower(trim($candidate)) : '';
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                return [
                    'creator_name' => $creatorNameNormalized,
                    'user_id' => $creatorUser?->getKey() !== null ? (int) $creatorUser->getKey() : null,
                    'rrhh_colaborador_id' => $creatorColaborador?->getKey() !== null ? (int) $creatorColaborador->getKey() : null,
                    'email' => $email,
                    'resolution_source' => $source,
                ];
            }
        }

        return [
            'creator_name' => $creatorNameNormalized,
            'user_id' => $creatorUser?->getKey() !== null ? (int) $creatorUser->getKey() : null,
            'rrhh_colaborador_id' => $creatorColaborador?->getKey() !== null ? (int) $creatorColaborador->getKey() : null,
            'email' => null,
            'resolution_source' => 'none',
        ];
    }
}
