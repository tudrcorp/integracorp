<?php

declare(strict_types=1);

namespace App\Http\Controllers\Business;

use App\Enums\CorporateAgendaInvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\CorporateAgendaActivityParticipant;
use App\Services\CorporateAgendaInvitationWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class CorporateAgendaInvitationResponseController extends Controller
{
    public function show(Request $request, CorporateAgendaActivityParticipant $participant)
    {
        $participant->loadMissing([
            'activity.creator:id,name',
            'activity.participants.colaborador:id,fullName',
            'colaborador:id,fullName,emailCorporativo,emailPersonal,avatar',
        ]);

        $activity = $participant->activity;
        abort_if($activity === null, 404);

        $responseUrl = URL::temporarySignedRoute(
            'agenda.invitation.respond',
            now()->addDays(7),
            ['participant' => $participant->id],
        );

        return view('corporate-agenda.invitation-mobile', [
            'participant' => $participant,
            'activity' => $activity,
            'responseUrl' => $responseUrl,
            'flashState' => session('invitation_response_state'),
        ]);
    }

    public function respond(Request $request, CorporateAgendaActivityParticipant $participant)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['accept', 'reject'])],
            'rejection_note' => ['nullable', 'string', 'min:5', 'max:1500'],
        ], [
            'action.required' => 'Selecciona una accion para continuar.',
            'action.in' => 'La accion seleccionada no es valida.',
            'rejection_note.min' => 'El motivo del rechazo debe tener al menos 5 caracteres.',
        ]);

        $status = $validated['action'] === 'accept'
            ? CorporateAgendaInvitationStatus::Accepted
            : CorporateAgendaInvitationStatus::Rejected;

        $rejectionNote = null;
        if ($status === CorporateAgendaInvitationStatus::Rejected) {
            $request->validate([
                'rejection_note' => ['required', 'string', 'min:5', 'max:1500'],
            ], [
                'rejection_note.required' => 'Debes indicar el motivo del rechazo.',
                'rejection_note.min' => 'El motivo del rechazo debe tener al menos 5 caracteres.',
            ]);

            $rejectionNote = trim((string) $validated['rejection_note']);
        }

        $participant->update([
            'invitation_status' => $status->value,
            'response_note' => $rejectionNote,
        ]);

        CorporateAgendaInvitationWhatsAppService::notifyCreatorAboutInvitationResponse(
            participant: $participant,
            status: $status,
            responseNote: $rejectionNote,
            requestedByUserId: null,
            panel: 'business',
        );

        $showUrl = URL::temporarySignedRoute(
            'agenda.invitation.show',
            now()->addDays(7),
            ['participant' => $participant->id],
        );

        return redirect($showUrl)->with('invitation_response_state', $status->value);
    }
}
