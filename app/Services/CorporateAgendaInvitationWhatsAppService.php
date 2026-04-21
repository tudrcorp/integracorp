<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CorporateAgendaInvitationStatus;
use App\Jobs\SendNotificacionWhatsApp;
use App\Models\CorporateAgendaActivity;
use App\Models\CorporateAgendaActivityParticipant;
use App\Models\RrhhColaborador;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CorporateAgendaInvitationWhatsAppService
{
    public static function dispatchInvitationToParticipant(
        CorporateAgendaActivityParticipant $participant,
        int $requestedByUserId,
        string $panel = 'business',
    ): void {
        $participant->loadMissing([
            'activity.participants.colaborador:id,fullName',
            'colaborador:id,fullName,telefonoCorporativo,telefono,user_id',
        ]);

        $activity = $participant->activity;
        $colaborador = $participant->colaborador;

        if ($activity === null || $colaborador === null) {
            return;
        }

        $phone = self::resolveColaboradorPhoneForWhatsApp($colaborador);
        if ($phone === null) {
            return;
        }

        $invitationUrl = URL::temporarySignedRoute(
            'agenda.invitation.show',
            now()->addDays(7),
            ['participant' => $participant->id],
        );

        $body = self::buildInvitationBody($activity, $invitationUrl);

        SendNotificacionWhatsApp::dispatch(
            $requestedByUserId,
            $body,
            $phone,
            null,
            [
                'panel' => $panel,
                'source' => 'business.agenda.invitation.assigned',
                'agenda_activity_id' => $activity->id,
                'participant_id' => $participant->id,
                'participant_colaborador_id' => $colaborador->id,
            ],
        );
    }

    public static function notifyCreatorAboutInvitationResponse(
        CorporateAgendaActivityParticipant $participant,
        CorporateAgendaInvitationStatus $status,
        ?string $responseNote = null,
        ?int $requestedByUserId = null,
        string $panel = 'business',
    ): void {
        $participant->loadMissing([
            'activity.creator:id,name,phone',
            'colaborador:id,fullName',
        ]);

        $activity = $participant->activity;
        if ($activity === null) {
            return;
        }

        $creatorPhone = self::resolveUserPhoneForWhatsApp((int) $activity->creator_user_id);
        if ($creatorPhone === null) {
            return;
        }

        $actorName = (string) ($participant->colaborador?->fullName ?: 'Colaborador');
        $responseText = $status === CorporateAgendaInvitationStatus::Accepted ? 'ACEPTO' : 'RECHAZO';
        $activityDate = $activity->activity_date?->format('d/m/Y') ?? '—';
        $activityType = $activity->activity_type?->value ?? 'Actividad';

        $message = <<<TEXT
        Actualizacion de invitacion en Agenda Corporativa.

        Actividad: {$activityType}
        Fecha: {$activityDate}
        Colaborador: {$actorName}
        Respuesta: {$responseText} la invitacion de Google Meet.

        Ticket de actividad #{$activity->id}
        TEXT;

        if ($status === CorporateAgendaInvitationStatus::Rejected && is_string($responseNote) && trim($responseNote) !== '') {
            $message .= "\n\nMotivo de rechazo: ".trim($responseNote);
        }

        SendNotificacionWhatsApp::dispatch(
            $requestedByUserId ?? (int) $activity->creator_user_id,
            $message,
            $creatorPhone,
            null,
            [
                'panel' => $panel,
                'source' => 'business.agenda.meet-invitation-response',
                'agenda_activity_id' => $activity->id,
                'creator_user_id' => $activity->creator_user_id,
                'participant_id' => $participant->id,
                'participant_colaborador_id' => $participant->rrhh_colaborador_id,
                'status' => $status->value,
            ],
        );
    }

    private static function buildInvitationBody(CorporateAgendaActivity $activity, string $invitationUrl): string
    {
        $activity->loadMissing(['participants.colaborador:id,fullName']);

        $title = Str::upper((string) ($activity->activity_type?->value ?? 'ACTIVIDAD'));
        $topic = trim(html_entity_decode(strip_tags((string) $activity->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $topic = $topic !== '' ? $topic : 'Sin descripcion registrada';
        if (mb_strlen($topic) > 500) {
            $topic = mb_substr($topic, 0, 497).'...';
        }

        $date = $activity->activity_date instanceof Carbon
            ? $activity->activity_date->copy()
            : Carbon::parse((string) $activity->activity_date);
        $date->locale('es');

        $dayName = Str::ucfirst((string) $date->translatedFormat('l'));
        $dayNumber = $date->format('d');
        $monthUpper = Str::upper((string) $date->translatedFormat('F'));
        $year = $date->format('Y');
        $dateLabel = "{$dayName} {$dayNumber} DE {$monthUpper} de {$year}";

        $startTime = self::formatTimeLabel((string) $activity->start_time);
        $endTime = self::formatTimeLabel((string) $activity->end_time);

        $participants = $activity->participants
            ->map(fn (CorporateAgendaActivityParticipant $item): ?string => $item->colaborador?->fullName)
            ->filter(fn (?string $name): bool => is_string($name) && trim($name) !== '')
            ->values();

        $participantLines = $participants->isNotEmpty()
            ? $participants->map(fn (string $name): string => '- '.$name)->implode("\n")
            : '- Sin participantes asociados';

        if ($activity->has_google_meet) {
            return <<<TEXT
            Invitacion - Tu Dr. en Casa, C.A.
            Tenemos el gusto de invitarte a:

            🤝 {$title}
            📅 Fecha: {$dateLabel}
            🕒 Hora: {$startTime} a {$endTime}
            📝 Tema: {$topic}
            📍 Lugar: ONLINE

            Enlace de la videollamada: {$activity->google_meet_url}

            👥 Participantes:
            {$participantLines}

            ✅ Confirma tu participacion aqui:
            {$invitationUrl}
            TEXT;
        }

        return <<<TEXT
        Invitacion - Tu Dr. en Casa, C.A.
        Tenemos el gusto de invitarte a:

        🤝 {$title}
        📅 Fecha: {$dateLabel}
        🕒 Hora: {$startTime} a {$endTime}
        📝 Tema: {$topic}
        📍 Lugar: PRESENCIAL

        👥 Participantes:
        {$participantLines}

        ✅ Confirma tu participacion aqui:
        {$invitationUrl}
        TEXT;
    }

    private static function formatTimeLabel(string $value): string
    {
        try {
            return Carbon::parse($value)->format('g:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    private static function resolveColaboradorPhoneForWhatsApp(RrhhColaborador $colaborador): ?string
    {
        $candidates = [
            $colaborador->telefonoCorporativo,
            $colaborador->telefono,
        ];

        if ((int) ($colaborador->user_id ?? 0) > 0) {
            $candidates[] = User::query()
                ->whereKey((int) $colaborador->user_id)
                ->value('phone');
        }

        foreach ($candidates as $candidate) {
            $phone = self::normalizePhoneForWhatsApp(is_string($candidate) ? trim($candidate) : null);
            if ($phone !== null) {
                return $phone;
            }
        }

        return null;
    }

    private static function resolveUserPhoneForWhatsApp(int $userId): ?string
    {
        $colaborador = RrhhColaborador::query()
            ->where('user_id', $userId)
            ->first(['telefonoCorporativo', 'telefono']);

        $candidates = [
            $colaborador?->telefonoCorporativo,
            $colaborador?->telefono,
            User::query()->whereKey($userId)->value('phone'),
        ];

        foreach ($candidates as $candidate) {
            $phone = self::normalizePhoneForWhatsApp(is_string($candidate) ? trim($candidate) : null);
            if ($phone !== null) {
                return $phone;
            }
        }

        return null;
    }

    private static function normalizePhoneForWhatsApp(?string $phone): ?string
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

        return '+'.$digits;
    }
}
