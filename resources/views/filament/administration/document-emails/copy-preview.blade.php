@props([
    'subject' => '',
    'body_html' => '',
    'documentLabel' => 'documento',
])

{{-- body_html llega saneado por DocumentEmailTemplateRenderer. --}}
<div class="space-y-3">
    <div class="rounded-2xl border border-gray-200/80 bg-gray-50/80 px-4 py-3 dark:border-white/10 dark:bg-white/5">
        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Asunto</p>
        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $subject }}</p>
    </div>
    <div class="rounded-2xl border border-gray-200/80 bg-white px-5 py-4 text-sm leading-relaxed text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200">
        {!! $body_html !!}
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400">El {{ $documentLabel }} en PDF va adjunto al correo.</p>
</div>
