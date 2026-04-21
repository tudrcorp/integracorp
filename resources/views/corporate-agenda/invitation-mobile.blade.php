<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Invitacion de actividad</title>
    <style>
        :root {
            color-scheme: light dark;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            background: radial-gradient(circle at top, #5ddcf5 0%, #0b2048 44%, #030a1a 100%);
            color: #eff6ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .safe-wrapper {
            max-width: 430px;
            width: 100%;
            padding: calc(16px + env(safe-area-inset-top)) 16px calc(24px + env(safe-area-inset-bottom));
        }

        .glass-card {
            border-radius: 30px;
            border: 1px solid rgba(148, 215, 255, 0.34);
            background:
                radial-gradient(circle at 12% 2%, rgba(103, 232, 249, 0.2) 0%, rgba(103, 232, 249, 0) 42%),
                linear-gradient(165deg, rgba(8, 30, 70, 0.93) 0%, rgba(4, 18, 49, 0.97) 58%, rgba(4, 15, 42, 0.99) 100%);
            backdrop-filter: blur(14px);
            box-shadow:
                0 26px 56px rgba(2, 8, 23, 0.58),
                inset 0 1px 0 rgba(255, 255, 255, 0.15);
            padding: 20px 18px 16px;
            overflow: hidden;
        }

        .eyebrow {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: #9ed4ff;
            letter-spacing: 0.02em;
        }

        .title {
            margin: 8px 0 14px;
            font-size: 40px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .summary {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            margin-bottom: 12px;
            align-items: flex-start;
        }

        .summary-list {
            display: grid;
            gap: 6px;
            font-size: 14px;
            color: #dbeafe;
        }

        .summary-item {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            line-height: 1.3;
        }

        .summary-icon {
            width: 24px;
            display: inline-flex;
            justify-content: center;
            opacity: 0.9;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            align-self: flex-start;
            border-radius: 999px;
            padding: 8px 13px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .status-accepted {
            background: rgba(16, 185, 129, 0.22);
            color: #a7f3d0;
            border: 1px solid rgba(16, 185, 129, 0.54);
        }

        .status-rejected {
            background: rgba(244, 63, 94, 0.22);
            color: #fecdd3;
            border: 1px solid rgba(251, 113, 133, 0.56);
        }

        .status-pending {
            background: rgba(148, 163, 184, 0.25);
            color: #d1d5db;
            border: 1px solid rgba(148, 163, 184, 0.5);
        }

        .info-card {
            margin-top: 12px;
            border-radius: 18px;
            border: 1px solid rgba(56, 189, 248, 0.38);
            background: linear-gradient(180deg, rgba(10, 75, 122, 0.33), rgba(8, 55, 98, 0.31));
            padding: 12px;
            color: #dbeafe;
        }

        .info-title {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            color: #bfdbfe;
        }

        .info-content {
            margin: 4px 0 0;
            font-size: 14px;
            line-height: 1.4;
            font-weight: 700;
            color: #e0f2fe;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-top: 14px;
        }

        .btn {
            width: 100%;
            border: 0;
            border-radius: 18px;
            padding: 15px 16px;
            font-size: 16px;
            font-weight: 800;
            color: #fff;
            cursor: pointer;
            transition: transform .2s ease, opacity .2s ease, box-shadow .25s ease, filter .2s ease;
        }

        .btn:hover {
            filter: brightness(1.06);
        }

        .btn:active {
            transform: scale(0.985);
        }

        .btn-accept {
            background: linear-gradient(135deg, #10b981, #34d399);
            box-shadow: 0 10px 24px rgba(16, 185, 129, 0.36);
        }

        .btn-reject {
            background: linear-gradient(135deg, #f43f5e, #fb7185);
            box-shadow: 0 10px 24px rgba(244, 63, 94, 0.35);
        }

        .btn-whatsapp {
            margin-top: 12px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            width: 100%;
            text-decoration: none;
            background: rgba(8, 62, 100, 0.45);
            border: 1px solid rgba(103, 232, 249, 0.48);
            border-radius: 16px;
            color: #e0f2fe;
            padding: 12px 14px;
            font-weight: 800;
            transition: filter .2s ease, transform .2s ease;
        }

        .btn-whatsapp:hover {
            filter: brightness(1.08);
        }

        .btn-whatsapp:active {
            transform: scale(0.99);
        }

        .reject-area {
            margin-top: 12px;
            padding: 10px;
            border-radius: 16px;
            border: 1px solid rgba(251, 113, 133, 0.34);
            background: rgba(190, 24, 93, 0.14);
            animation: slideIn .25s ease;
        }

        .field-label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            color: #fecdd3;
            font-weight: 600;
        }

        textarea {
            width: 100%;
            min-height: 96px;
            border-radius: 14px;
            border: 1px solid rgba(251, 113, 133, 0.4);
            background: rgba(15, 23, 42, 0.52);
            color: #f8fafc;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
            resize: vertical;
        }

        textarea:focus {
            border-color: rgba(251, 113, 133, 0.75);
            box-shadow: 0 0 0 3px rgba(251, 113, 133, 0.2);
        }

        .error {
            margin-top: 6px;
            color: #fecaca;
            font-size: 12px;
        }

        .flash-ok, .flash-no {
            margin-bottom: 14px;
            border-radius: 18px;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 700;
            animation: slideIn .45s ease, pulseGlow 1.2s ease;
        }

        .flash-ok {
            border: 1px solid rgba(52, 211, 153, 0.5);
            background: rgba(16, 185, 129, 0.2);
            color: #dcfce7;
        }

        .flash-no {
            border: 1px solid rgba(251, 113, 133, 0.5);
            background: rgba(244, 63, 94, 0.22);
            color: #ffe4e6;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 rgba(56, 189, 248, 0); }
            50% { box-shadow: 0 0 22px rgba(56, 189, 248, 0.35); }
            100% { box-shadow: 0 0 0 rgba(56, 189, 248, 0); }
        }
    </style>
</head>
<body>
    <div class="safe-wrapper">
        @if ($flashState === \App\Enums\CorporateAgendaInvitationStatus::Accepted->value)
            <div class="flash-ok">Aceptacion registrada correctamente.</div>
        @elseif ($flashState === \App\Enums\CorporateAgendaInvitationStatus::Rejected->value)
            <div class="flash-no">Rechazo registrado correctamente.</div>
        @endif

        @php
            $statusValue = $participant->invitation_status?->value ?? \App\Enums\CorporateAgendaInvitationStatus::Pending->value;
            $statusLabel = match ($statusValue) {
                \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'Aceptada',
                \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'Rechazada',
                default => 'Pendiente',
            };
            $statusClass = match ($statusValue) {
                \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'status-accepted',
                \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'status-rejected',
                default => 'status-pending',
            };
            $activityDate = $activity->activity_date?->locale('es')->translatedFormat('l d \d\e F \d\e Y') ?? '—';
            $startLabel = \Carbon\Carbon::parse((string) $activity->start_time)->format('g:i A');
            $endLabel = \Carbon\Carbon::parse((string) $activity->end_time)->format('g:i A');
            $participants = $activity->participants
                ->map(fn ($item) => $item->colaborador?->fullName)
                ->filter()
                ->implode(', ');
        @endphp

        <div class="glass-card">
            <p class="eyebrow">Invitacion - Tu Dr. en Casa, C.A.</p>
            <h1 class="title">{{ $activity->activity_type?->value ?? 'Actividad' }}</h1>

            <div class="summary">
                <div class="summary-list">
                    <div class="summary-item">
                        <span class="summary-icon">📅</span>
                        <span>{{ \Illuminate\Support\Str::ucfirst($activityDate) }}</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-icon">🕒</span>
                        <span>{{ $startLabel }} a {{ $endLabel }}</span>
                    </div>
                </div>
                <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>

            <div class="info-card">
                <p class="info-title">Tema</p>
                <p class="info-content">{{ $activity->description }}</p>
                <p class="info-title" style="margin-top: 8px;">Participantes</p>
                <p class="info-content">{{ $participants !== '' ? $participants : 'Sin participantes asociados' }}</p>
            </div>

            @if ($activity->has_google_meet && filled($activity->google_meet_url))
                <div class="info-card" style="margin-top: 10px;">
                    <p class="info-title">Enlace de videollamada</p>
                    <p class="info-content" style="word-break: break-all;">
                        <a href="{{ $activity->google_meet_url }}" target="_blank" style="color: #67e8f9;">{{ $activity->google_meet_url }}</a>
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ $responseUrl }}" id="invitation-response-form" style="margin-top: 14px;">
                @csrf
                <input type="hidden" name="action" id="invitation-action" value="">

                <div id="reject-note-wrapper" class="reject-area" style="display: none;">
                    <label class="field-label" for="rejection_note">Motivo del rechazo (obligatorio si rechazas)</label>
                    <textarea name="rejection_note" id="rejection_note">{{ old('rejection_note') }}</textarea>
                    @error('rejection_note')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-accept" onclick="submitInvitationAction('accept')">Aceptar invitacion</button>
                    <button type="button" class="btn btn-reject" onclick="submitInvitationAction('reject')">Rechazar invitacion</button>
                </div>
                @error('action')
                    <p class="error">{{ $message }}</p>
                @enderror
            </form>

            <a class="btn-whatsapp" href="https://wa.me/" target="_blank" rel="noopener noreferrer">
                <span>💬</span>
                <span>Volver a WhatsApp</span>
            </a>
        </div>
    </div>

    <script>
        const rejectWrapper = document.getElementById('reject-note-wrapper');
        const actionInput = document.getElementById('invitation-action');
        const form = document.getElementById('invitation-response-form');

        function submitInvitationAction(action) {
            actionInput.value = action;

            if (action === 'reject') {
                rejectWrapper.style.display = 'block';
                const noteField = document.getElementById('rejection_note');
                if (noteField && noteField.value.trim().length < 5) {
                    noteField.focus();
                    return;
                }
            } else {
                rejectWrapper.style.display = 'none';
            }

            form.submit();
        }

        @if ($errors->has('rejection_note'))
            rejectWrapper.style.display = 'block';
        @endif
    </script>
</body>
</html>
