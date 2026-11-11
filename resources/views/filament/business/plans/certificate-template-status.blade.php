@php($active = $versions->firstWhere('status', \App\Models\PlanCertificateTemplate::STATUS_ACTIVE))
<div class="space-y-3 text-sm">
    @if ($active)
        <div class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-800 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/30">
            <strong>Diseño propio activo: v{{ $active->version }}</strong> · {{ $active->page_count }} hoja(s) · subido por {{ $active->created_by_name }} el {{ $active->created_at?->format('d/m/Y H:i') }}.
            Al subir un archivo nuevo se crea la versión v{{ $active->version + 1 }} y reemplaza a esta.
        </div>
    @else
        <div class="rounded-lg bg-amber-50 px-3 py-2 text-amber-900 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30">
            <strong>Este plan usa el certificado predeterminado</strong> (el del Generador de Certificado, con los colores del plan). Suba un diseño solo si el plan necesita uno propio.
        </div>
    @endif

    @if ($versions->isNotEmpty())
        <div>
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Versiones</p>
            <ul class="divide-y divide-gray-950/5 rounded-lg ring-1 ring-gray-950/5 dark:divide-white/10 dark:ring-white/10">
                @foreach ($versions as $version)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-1.5">
                        <span class="text-gray-700 dark:text-gray-200">
                            <strong>v{{ $version->version }}</strong> · {{ $version->original_name ?? 'sin nombre' }} · {{ $version->created_by_name }} · {{ $version->created_at?->format('d/m/Y H:i') }}
                            @if ($version->notes)<span class="block text-xs text-gray-500 dark:text-gray-400">{{ $version->notes }}</span>@endif
                            @if (! $version->isActive() && $version->deactivation_reason)<span class="block text-xs text-gray-500 dark:text-gray-400">Retirada: {{ $version->deactivation_reason }}</span>@endif
                        </span>
                        <span class="rounded-md px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $version->isActive() ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400' }}">{{ $version->isActive() ? 'Activa' : 'Inactiva' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
