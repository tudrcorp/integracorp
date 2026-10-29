@php
    /** @var string $previewUrl */
    /** @var string $downloadUrl */
    /** @var bool $reverted */
@endphp

<div
    x-data="{
        loading: true,
        slow: false,
        timer: null,
        init() {
            this.timer = setTimeout(() => { if (this.loading) { this.slow = true; } }, 15000);
        },
        loaded() {
            this.loading = false;
            this.slow = false;
            clearTimeout(this.timer);
        },
        destroy() { clearTimeout(this.timer); },
    }"
    class="ic-pba-preview"
>
    <style>
        .ic-pba-preview { display: flex; flex-direction: column; gap: 0.75rem; }
        .ic-pba-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; }
        .ic-pba-note { font-size: 0.8125rem; color: rgb(180 83 9); }
        .dark .ic-pba-note { color: rgb(252 211 77); }
        .ic-pba-links { display: flex; gap: 0.5rem; }
        .ic-pba-btn { display: inline-flex; align-items: center; gap: 0.375rem; border-radius: 0.5rem; padding: 0.4rem 0.8rem; font-size: 0.8125rem; font-weight: 600; text-decoration: none; }
        .ic-pba-btn.is-primary { background: var(--primary-600, #2563eb); color: #fff; }
        .ic-pba-btn.is-primary:hover { background: var(--primary-500, #3b82f6); }
        .ic-pba-btn.is-gray { background: rgb(243 244 246); color: rgb(55 65 81); }
        .dark .ic-pba-btn.is-gray { background: rgb(255 255 255 / 0.08); color: rgb(229 231 235); }
        .ic-pba-frame { position: relative; height: 75vh; border-radius: 0.75rem; overflow: hidden; background: rgb(243 244 246); }
        .dark .ic-pba-frame { background: rgb(255 255 255 / 0.04); }
        .ic-pba-frame iframe { width: 100%; height: 100%; border: 0; background: #fff; }
        .ic-pba-loading { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem; background: inherit; font-size: 0.875rem; color: rgb(75 85 99); text-align: center; padding: 1rem; }
        .dark .ic-pba-loading { color: rgb(209 213 219); }
        .ic-pba-spinner { width: 2.25rem; height: 2.25rem; border-radius: 9999px; border: 3px solid rgb(209 213 219); border-top-color: var(--primary-600, #2563eb); animation: ic-pba-spin 0.8s linear infinite; }
        @keyframes ic-pba-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .ic-pba-spinner { animation-duration: 2.4s; } }
    </style>

    <div class="ic-pba-bar">
        <span class="ic-pba-note">
            @if ($reverted)
                Este ajuste fue revertido: el aviso quedó ANULADO y no debe enviarse al cliente.
            @endif
        </span>
        <span class="ic-pba-links">
            <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="ic-pba-btn is-gray">Abrir en otra pestaña</a>
            <a href="{{ $downloadUrl }}" class="ic-pba-btn is-primary">Descargar PDF</a>
        </span>
    </div>

    <div class="ic-pba-frame">
        <iframe src="{{ $previewUrl }}" title="Vista previa del aviso de cobro por ajuste" x-on:load="loaded()"></iframe>

        <div class="ic-pba-loading" x-show="loading" role="status" aria-live="polite">
            <span class="ic-pba-spinner" aria-hidden="true"></span>
            <span x-show="! slow">Preparando el aviso…</span>
            <span x-show="slow" x-cloak>Está tardando más de lo normal. Puede esperar o <a href="{{ $downloadUrl }}" class="underline">descargarlo directamente</a>.</span>
        </div>
    </div>
</div>
