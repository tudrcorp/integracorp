<?php

declare(strict_types=1);

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Support\HelpdeskObservationAppender;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class MarkHelpdeskTicketInProgressController extends Controller
{
    public function __invoke(HelpDesk $helpDesk): RedirectResponse
    {
        $user = Auth::user();
        if ($user === null) {
            abort(403);
        }

        $colaborador = RrhhColaborador::query()->where('user_id', $user->id)->first();
        if (! $colaborador) {
            SecurityAudit::log('AUDIT_HELPDESK_STATUS_UPDATE_FAILED', 'business.helpdesk-ticket.mark-in-progress', [
                'panel' => 'business',
                'helpdesk_id' => $helpDesk->getKey(),
                'reason' => 'missing_colaborador_profile',
            ], $user);

            Notification::make()
                ->title('No se pudo actualizar')
                ->danger()
                ->body('No tienes un perfil de colaborador vinculado.')
                ->send();

            return redirect()->back();
        }

        $isAssignee = $helpDesk->rrhhColaboradores()
            ->where('rrhh_colaboradors.id', $colaborador->id)
            ->exists();

        if (! $isAssignee) {
            SecurityAudit::log('AUDIT_HELPDESK_STATUS_UPDATE_FAILED', 'business.helpdesk-ticket.mark-in-progress', [
                'panel' => 'business',
                'helpdesk_id' => $helpDesk->getKey(),
                'reason' => 'user_not_assignee',
            ], $user);

            Notification::make()
                ->title('No se pudo actualizar')
                ->danger()
                ->body('El ticket no existe o no tienes permiso.')
                ->send();

            return redirect()->back();
        }

        $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave(
            $helpDesk,
            'EN PROCESO',
            $user->name
        );

        if ($sanitized !== 'EN PROCESO') {
            SecurityAudit::log('AUDIT_HELPDESK_STATUS_UPDATE_FAILED', 'business.helpdesk-ticket.mark-in-progress', [
                'panel' => 'business',
                'helpdesk_id' => $helpDesk->getKey(),
                'reason' => 'status_transition_not_allowed',
                'sanitized' => $sanitized,
            ], $user);

            Notification::make()
                ->title('Acción no permitida')
                ->warning()
                ->body('No se puede cambiar el estatus de este ticket.')
                ->send();

            return redirect()->back();
        }

        if ($helpDesk->status === 'EN PROCESO') {
            SecurityAudit::log('AUDIT_HELPDESK_STATUS_UPDATE_SKIPPED', 'business.helpdesk-ticket.mark-in-progress', [
                'panel' => 'business',
                'helpdesk_id' => $helpDesk->getKey(),
                'reason' => 'already_in_progress',
            ], $user);

            Notification::make()
                ->title('Ticket ya en proceso')
                ->success()
                ->body('Este ticket ya tiene el estatus «En proceso».')
                ->send();

            return redirect()->back();
        }

        $previousStatus = (string) $helpDesk->status;
        $helpDesk->status = 'EN PROCESO';
        $helpDesk->updated_by = $user->name;
        $helpDesk->save();
        $helpDesk->refresh();
        $statusNote = '<p>Estado del ticket actualizado de <strong>'.e($previousStatus).'</strong> a <strong>EN PROCESO</strong>.</p>';
        HelpdeskObservationAppender::append($helpDesk, $statusNote, $user->name);

        SecurityAudit::log('AUDIT_HELPDESK_STATUS_UPDATED', 'business.helpdesk-ticket.mark-in-progress', [
            'panel' => 'business',
            'helpdesk_id' => $helpDesk->getKey(),
            'new_status' => 'EN PROCESO',
            'updated_by' => $user->name,
        ], $user);

        Notification::make()
            ->title('Estatus actualizado')
            ->success()
            ->body('El ticket pasó a «En proceso».')
            ->send();

        return redirect()->back();
    }
}
