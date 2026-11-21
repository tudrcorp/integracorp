@php
    /** @var \App\Models\OutboundMessage $message */
    $isBlocked = $message->status === \App\Enums\OutboundMessageStatus::Blocked;
    $rows = [
        ['Canal', $message->channel->label()],
        ['Para', $message->audience->label().' · '.($message->audience === \App\Enums\NotificationAudience::Internal ? 'personal de TDG' : 'fuera de TDG')],
        ['Destinatario', trim(($message->recipient_name ? $message->recipient_name.' · ' : '').$message->maskedRecipient()).($message->recipients_count > 1 ? ' (+'.($message->recipients_count - 1).')' : '')],
        ['Estado', $message->status->label()],
        ['Fecha', $message->created_at?->timezone(config('app.timezone'))->format('d/m/Y h:i:s a')],
        ['Tipo de mensaje', match ($message->message_type) { 'document' => 'Documento', 'image' => 'Imagen con texto', 'video' => 'Video', 'mail' => 'Correo', default => 'Texto' }],
    ];

    if (filled($message->attachment_name)) {
        $rows[] = ['Adjunto', $message->attachment_name];
    }

    if ($message->is_critical) {
        $rows[] = ['Prioridad', 'Crítico'];
    }
@endphp

<div class="ncd">
    <style>
        .ncd { display: grid; gap: 1rem; font-size: .875rem; color: #0f172a; }
        .dark .ncd { color: #f1f5f9; }
        .ncd-alert { padding: .55rem .75rem; border-radius: .75rem; border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; }
        .ncd-alert.is-blocked { border-color: #cbd5e1; background: #f8fafc; color: #334155; }
        .dark .ncd-alert { border-color: #7f1d1d; background: rgb(127 29 29 / 25%); color: #fecaca; }
        .dark .ncd-alert.is-blocked { border-color: #334155; background: rgb(30 41 59 / 60%); color: #e2e8f0; }
        .ncd-list { display: grid; grid-template-columns: 8.5rem 1fr; gap: .45rem .75rem; margin: 0; }
        .ncd-list dt { color: #64748b; }
        .ncd-list dd { margin: 0; font-weight: 500; overflow-wrap: anywhere; }
        .ncd-label { margin: 0 0 .3rem; font-size: .7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
        .ncd-body { max-height: 22rem; overflow-y: auto; white-space: pre-line; line-height: 1.55; padding: .75rem; border-radius: .75rem; background: #f8fafc; }
        .dark .ncd-body { background: rgb(30 41 59 / 60%); }
        .ncd-reserved { padding: .75rem; border-radius: .75rem; border: 1px dashed #cbd5e1; color: #64748b; }
        .ncd-origin { margin: 0; font-size: .72rem; color: #94a3b8; overflow-wrap: anywhere; }
    </style>

    @if (filled($message->error))
        <div class="ncd-alert {{ $isBlocked ? 'is-blocked' : '' }}">
            <strong>{{ $isBlocked ? 'No salió:' : 'Error:' }}</strong> {{ $message->error }}
        </div>
    @endif

    <dl class="ncd-list">
        @foreach ($rows as [$label, $value])
            <dt>{{ $label }}</dt>
            <dd>{{ $value }}</dd>
        @endforeach
    </dl>

    @if (filled($message->subject))
        <div>
            <p class="ncd-label">Asunto</p>
            <p style="margin: 0; font-weight: 500;">{{ $message->subject }}</p>
        </div>
    @endif

    <div>
        <p class="ncd-label">Mensaje</p>
        @if (! $canReadBody)
            <div class="ncd-reserved">Contenido reservado: los mensajes externos pueden traer datos clínicos o personales. Solo un SUPERADMIN puede leerlos.</div>
        @elseif (filled($message->body))
            <div class="ncd-body">{{ $message->body }}</div>
        @else
            <p style="margin: 0; color: #64748b;">Sin texto guardado.</p>
        @endif
    </div>

    @if (filled($message->origin) && \App\Support\NotificationCenter\NotificationCenterAccess::isSuperAdmin())
        <p class="ncd-origin">Origen técnico: {{ $message->origin }}</p>
    @endif
</div>
