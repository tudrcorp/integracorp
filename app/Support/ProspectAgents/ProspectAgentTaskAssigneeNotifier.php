<?php

declare(strict_types=1);

namespace App\Support\ProspectAgents;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\ProspectAgentTaskAssignedMail;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Companies\CompanyAssociateDocumentsBellAlert;
use Filament\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class ProspectAgentTaskAssigneeNotifier
{
    public static function deliver(ProspectAgentTask $task): void
    {
        $task->loadMissing(['prospect_agent', 'rrhh_colaborador.user']);

        $colaborador = $task->rrhh_colaborador;

        if (! $colaborador instanceof RrhhColaborador) {
            Log::warning('ProspectAgentTaskAssigneeNotifier: la tarea no tiene colaborador', [
                'task_id' => $task->getKey(),
            ]);

            return;
        }

        $prospectName = trim((string) ($task->prospect_agent?->name ?? ''));

        if ($prospectName === '') {
            $prospectName = 'Prospecto #'.$task->prospect_agent_id;
        }

        $assignedBy = trim((string) ($task->created_by ?? ''));

        if ($assignedBy === '') {
            $assignedBy = 'Un analista de Negocios';
        }

        $description = trim((string) ($task->task ?? ''));
        $assignedAt = now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
        $prospectUrl = self::prospectUrl($task);
        $taskId = (int) $task->getKey();

        self::sendWhatsApp($taskId, $colaborador, $prospectName, $assignedBy, $description, $assignedAt, $prospectUrl);
        self::sendMail($taskId, $colaborador, $prospectName, $assignedBy, $description, $assignedAt, $prospectUrl);
        self::sendBell($taskId, $colaborador, $prospectName, $assignedBy, $description, $prospectUrl);
    }

    public static function whatsAppBody(
        int $taskId,
        string $prospectName,
        string $assignedBy,
        string $description,
        string $assignedAt,
        string $prospectUrl,
    ): string {
        $safeDescription = $description !== '' ? $description : 'Sin descripción.';

        if (mb_strlen($safeDescription) > 1200) {
            $safeDescription = mb_substr($safeDescription, 0, 1197).'...';
        }

        return <<<TEXT
Le asignaron una tarea de captación en INTEGRACORP.

Tarea N.º {$taskId}
Prospecto: {$prospectName}
Asignada por: {$assignedBy}
Fecha y hora: {$assignedAt}

*Descripción:*
{$safeDescription}

Ingrese a INTEGRACORP para gestionarla:
{$prospectUrl}
TEXT;
    }

    public static function recipientEmail(RrhhColaborador $colaborador): ?string
    {
        foreach (['emailCorporativo', 'emailPersonal', 'emailAlternativo'] as $field) {
            $email = strtolower(trim((string) ($colaborador->getAttribute($field) ?? '')));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    public static function recipientPhone(RrhhColaborador $colaborador): ?string
    {
        $raw = trim((string) ($colaborador->telefonoCorporativo ?? ''));

        if ($raw === '') {
            $raw = trim((string) ($colaborador->telefono ?? ''));
        }

        if ($raw === '') {
            return null;
        }

        return HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($raw);
    }

    private static function sendWhatsApp(
        int $taskId,
        RrhhColaborador $colaborador,
        string $prospectName,
        string $assignedBy,
        string $description,
        string $assignedAt,
        string $prospectUrl,
    ): void {
        $phone = self::recipientPhone($colaborador);

        if ($phone === null) {
            Log::info('ProspectAgentTaskAssigneeNotifier: el colaborador no tiene teléfono para WhatsApp', [
                'task_id' => $taskId,
                'colaborador_id' => $colaborador->getKey(),
            ]);

            return;
        }

        $body = self::whatsAppBody($taskId, $prospectName, $assignedBy, $description, $assignedAt, $prospectUrl);

        self::once($taskId, 'whatsapp', function () use ($colaborador, $body, $phone, $taskId): void {
            SendNotificacionWhatsApp::dispatch(
                $colaborador->user_id,
                $body,
                $phone,
                null,
                [
                    'panel' => 'business',
                    'context' => 'prospect_agent_task_assigned',
                    'prospect_agent_task_id' => $taskId,
                ],
            );
        });
    }

    private static function sendMail(
        int $taskId,
        RrhhColaborador $colaborador,
        string $prospectName,
        string $assignedBy,
        string $description,
        string $assignedAt,
        string $prospectUrl,
    ): void {
        $email = self::recipientEmail($colaborador);

        if ($email === null) {
            Log::info('ProspectAgentTaskAssigneeNotifier: el colaborador no tiene correo', [
                'task_id' => $taskId,
                'colaborador_id' => $colaborador->getKey(),
            ]);

            return;
        }

        self::once($taskId, 'mail', function () use ($colaborador, $email, $prospectName, $assignedBy, $description, $assignedAt, $prospectUrl, $taskId): void {
            Mail::to($email)->queue(new ProspectAgentTaskAssignedMail(
                colaboradorName: (string) $colaborador->fullName,
                assignedBy: $assignedBy,
                prospectName: $prospectName,
                taskDescription: $description !== '' ? $description : 'Sin descripción.',
                assignedAt: $assignedAt,
                prospectUrl: $prospectUrl,
                taskId: $taskId,
            ));
        });
    }

    private static function sendBell(
        int $taskId,
        RrhhColaborador $colaborador,
        string $prospectName,
        string $assignedBy,
        string $description,
        string $prospectUrl,
    ): void {
        $user = $colaborador->user;

        if (! $user instanceof User) {
            Log::info('ProspectAgentTaskAssigneeNotifier: el colaborador no tiene usuario para la campana', [
                'task_id' => $taskId,
                'colaborador_id' => $colaborador->getKey(),
            ]);

            return;
        }

        $summary = $description !== '' ? Str::limit($description, 280) : 'Sin descripción.';
        $body = $assignedBy.' te asignó una tarea sobre '.$prospectName.'. '.$summary;

        self::once($taskId, 'bell', function () use ($user, $body, $prospectUrl, $taskId): void {
            $notification = Notification::make()
                ->title('Nueva tarea de prospecto')
                ->body($body)
                ->icon('heroicon-o-clipboard-document-check')
                ->actions([
                    Action::make('viewProspect')
                        ->label('Ver prospecto')
                        ->url($prospectUrl),
                ]);

            $user->notifyNow($notification->toDatabase());
            DatabaseNotificationsSent::dispatch($user);
            CompanyAssociateDocumentsBellAlert::markPending((int) $user->getKey());

            Log::info('ProspectAgentTaskAssigneeNotifier: campana marcada', [
                'task_id' => $taskId,
                'user_id' => $user->getKey(),
            ]);
        });
    }

    private static function prospectUrl(ProspectAgentTask $task): string
    {
        try {
            $prospectId = $task->prospect_agent?->getKey() ?? $task->prospect_agent_id;

            if ($prospectId === null || $prospectId === '') {
                return ProspectAgentResource::getUrl('index', panel: 'business');
            }

            return ProspectAgentResource::getUrl('view', ['record' => $prospectId], panel: 'business');
        } catch (Throwable $exception) {
            Log::warning('ProspectAgentTaskAssigneeNotifier: no se pudo armar el enlace del prospecto', [
                'task_id' => $task->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return rtrim((string) config('app.url'), '/').'/business/prospect-agents';
        }
    }

    private static function once(int $taskId, string $channel, callable $send): void
    {
        $key = 'prospect-agent-task-notice.'.$taskId.'.'.$channel;

        if (! Cache::add($key, now()->timestamp, now()->addDays(7))) {
            return;
        }

        try {
            $send();
        } catch (Throwable $exception) {
            Cache::forget($key);

            Log::error('ProspectAgentTaskAssigneeNotifier: falló el canal '.$channel, [
                'task_id' => $taskId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
