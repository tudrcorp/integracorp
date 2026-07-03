@php
    /** @var \App\Models\CompanyAssociate $record */
    $urls = $record->identityDocumentUrls();
@endphp

@if ($urls === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">Sin documentos cargados.</p>
@else
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($urls as $index => $url)
            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white dark:border-white/10 dark:bg-white/5">
                <p class="border-b border-slate-200/80 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-white/10 dark:text-slate-400">
                    Documento {{ $index + 1 }}
                </p>
                <img src="{{ $url }}" alt="Documento de identidad {{ $index + 1 }}"
                    class="h-60 w-full object-contain bg-slate-50 dark:bg-slate-950/40">
            </div>
        @endforeach
    </div>
@endif
