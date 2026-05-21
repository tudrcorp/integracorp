@php
    $selectedPublication = $this->selectedSocialPublication;
    $selectedPlatformValues = collect($socialPublicationForm['platforms'] ?? [])
        ->map(fn (mixed $value): string => (string) $value)
        ->values()
        ->all();
@endphp

<form wire:submit.prevent="saveSocialPublications" class="space-y-4">
    <div class="rounded-2xl border border-[#6EA2B3]/70 bg-gradient-to-br from-[#BDD8E9]/70 via-white to-white p-4 shadow-[0_8px_20px_rgba(78,142,162,0.18)] dark:border-[#6EA2B3]/45 dark:from-[#0A4174]/40 dark:via-slate-900/90 dark:to-slate-900/90 dark:shadow-[0_12px_24px_rgba(10,65,116,0.35)]">
        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Calendario publicitario</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Selecciona las redes sociales con publicación programada para este día. Puedes combinar varias redes en la misma fecha.
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            @foreach ($this->socialPlatformOptions as $value => $label)
                @php
                    $meta = \App\Support\CorporateAgendaSocialPlatformCatalog::for($value);
                @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full border border-[#6EA2B3]/70 bg-[#001D39] px-2.5 py-1 text-[10px] font-semibold text-[#EAF4FB] shadow-[0_6px_14px_rgba(0,29,57,0.35)] dark:border-[#6EA2B3]/70 dark:bg-[#001D39] dark:text-[#EAF4FB]">
                    <x-corporate-agenda-social-icon :platform="$value" size="sm" />
                    {{ $meta['label'] }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Fecha de publicación</label>
            <input
                type="date"
                wire:model="socialPublicationForm.publication_date"
                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100"
            >
            @error('socialPublicationForm.publication_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="mb-2 block text-xs font-medium text-slate-700 dark:text-slate-200">Redes sociales</label>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($this->socialPlatformOptions as $value => $label)
                    @php
                        $meta = \App\Support\CorporateAgendaSocialPlatformCatalog::for($value);
                        $isChecked = in_array($value, $selectedPlatformValues, true);
                    @endphp
                    <label
                        class="group cursor-pointer rounded-2xl border p-3 transition
                        {{ $isChecked
                            ? 'border-[#4E8EA2]/85 bg-[#001D39] text-[#EAF4FB] shadow-[0_10px_24px_rgba(78,142,162,0.24)] ring-2 ring-[#7BBDE8]/55 dark:border-[#6EA2B3]/65 dark:bg-[#001D39] dark:text-[#EAF4FB] dark:ring-[#6EA2B3]/65'
                            : 'border-slate-200/80 bg-white/90 hover:border-[#7BBDE8]/80 hover:bg-[#BDD8E9]/30 dark:border-white/10 dark:bg-slate-900/80 dark:hover:border-[#6EA2B3]/65 dark:hover:bg-[#0A4174]/25' }}"
                    >
                        <input
                            type="checkbox"
                            value="{{ $value }}"
                            wire:model.live="socialPublicationForm.platforms"
                            @checked($isChecked)
                            class="sr-only"
                        >
                        <div class="flex items-center gap-3">
                            <x-corporate-agenda-social-icon :platform="$value" size="lg" />
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $meta['label'] }}</span>
                                <span class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $isChecked ? 'Publicación programada' : 'Tocar para programar' }}
                                </span>
                            </span>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('socialPublicationForm.platforms') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <p class="mb-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Configuración por red social</p>
            <p class="mb-3 text-[11px] text-slate-500 dark:text-slate-400">Cada red social puede tener su propio brief y sus propios archivos adjuntos.</p>
        </div>

        @forelse ($selectedPlatformValues as $platformValue)
            @php
                $platformMeta = \App\Support\CorporateAgendaSocialPlatformCatalog::for($platformValue);
                $storedAttachments = $this->socialPublicationAttachmentPreviewsByPlatform[$platformValue] ?? [];
                $newUploads = $this->socialPublicationUploadPreviewsByPlatform[$platformValue] ?? [];
            @endphp
            <div class="sm:col-span-2 rounded-2xl border border-slate-200/80 bg-white/85 p-3 dark:border-white/10 dark:bg-slate-900/80">
                <div class="mb-3 flex items-center gap-2">
                    <x-corporate-agenda-social-icon :platform="$platformValue" size="md" />
                    <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $platformMeta['label'] }}</p>
                </div>

                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Brief / tema de la publicación (opcional)</label>
                <textarea
                    wire:model.defer="socialPublicationBriefByPlatform.{{ $platformValue }}"
                    rows="3"
                    placeholder="Ej. Lanzamiento campaña Q2, testimonial corporativo, aviso de evento..."
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100"
                ></textarea>
                @error('socialPublicationBriefByPlatform.'.$platformValue) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror

                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Adjuntos para {{ $platformMeta['label'] }}</label>
                    <input
                        type="file"
                        wire:model="socialPublicationUploadsByPlatform.{{ $platformValue }}"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-[#0A4174] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#BDD8E9] hover:file:bg-[#49769F] dark:border-white/10 dark:bg-slate-800 dark:text-slate-100"
                    >
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Puedes cargar imágenes, videos o documentos (máx. 10MB por archivo).</p>
                    @error('socialPublicationUploadsByPlatform.'.$platformValue.'.*') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                @if (! empty($storedAttachments))
                    <div class="mt-3">
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">Archivos guardados</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($storedAttachments as $index => $attachment)
                                <div class="group relative overflow-hidden rounded-xl border border-slate-200/80 bg-white dark:border-white/10 dark:bg-slate-900">
                                    @if ($attachment['is_image'])
                                        <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}" class="h-20 w-full object-cover">
                                    @elseif ($attachment['is_video'])
                                        <video src="{{ $attachment['url'] }}" class="h-20 w-full object-cover" muted playsinline preload="metadata"></video>
                                    @else
                                        <div class="flex h-20 items-center justify-center bg-slate-100 text-[11px] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                            {{ $attachment['ext'] }}
                                        </div>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="removeSocialPublicationAttachment('{{ $platformValue }}', {{ $index }})"
                                        class="absolute right-1 top-1 hidden rounded-full bg-rose-600/95 p-1 text-white transition hover:bg-rose-500 group-hover:inline-flex"
                                        title="Eliminar archivo"
                                    >
                                        <x-filament::icon icon="heroicon-o-x-mark" class="size-3" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($newUploads))
                    <div class="mt-3">
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">Nuevos archivos por guardar</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($newUploads as $preview)
                                <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white dark:border-white/10 dark:bg-slate-900">
                                    @if ($preview['is_image'])
                                        <img src="{{ $preview['url'] }}" alt="{{ $preview['name'] }}" class="h-20 w-full object-cover">
                                    @elseif ($preview['is_video'])
                                        <video src="{{ $preview['url'] }}" class="h-20 w-full object-cover" muted playsinline preload="metadata"></video>
                                    @else
                                        <div class="flex h-20 items-center justify-center bg-slate-100 px-2 text-center text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                            {{ \Illuminate\Support\Str::upper($preview['ext'] ?: 'DOC') }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="sm:col-span-2 rounded-2xl border border-dashed border-slate-300/80 bg-white/80 px-4 py-6 text-center dark:border-white/10 dark:bg-slate-900/60">
                <p class="text-xs text-slate-500 dark:text-slate-400">Selecciona al menos una red social para configurar su contenido.</p>
            </div>
        @endforelse
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-[#6EA2B3]/55 bg-white/90 px-4 py-3 dark:border-[#4E8EA2]/45 dark:bg-slate-900/90">
        @if ($selectedPublication && $this->canCurrentUserEditSocialPublication($selectedPublication))
            <button
                type="button"
                wire:click="deleteSelectedSocialPublication"
                class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-rose-500"
            >
                <span class="inline-flex items-center gap-1">
                    <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="deleteSelectedSocialPublication" class="size-3.5 animate-spin" />
                    <span wire:loading.remove wire:target="deleteSelectedSocialPublication">Quitar red seleccionada</span>
                    <span wire:loading wire:target="deleteSelectedSocialPublication">Quitando...</span>
                </span>
            </button>
        @else
            <span class="text-xs text-slate-500 dark:text-slate-400">
                Guarda para sincronizar las redes marcadas en el calendario mensual.
            </span>
        @endif

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="saveSocialPublications"
            class="rounded-xl border border-[#4E8EA2]/80 bg-[#0A4174] px-4 py-2 text-xs font-semibold text-[#BDD8E9] shadow-[0_8px_18px_rgba(10,65,116,0.35)] transition hover:bg-[#49769F] dark:border-[#6EA2B3]/60 dark:shadow-[0_10px_20px_rgba(10,65,116,0.4)]"
        >
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="saveSocialPublications" class="size-3.5 animate-spin" />
                <span wire:loading.remove wire:target="saveSocialPublications">Guardar calendario publicitario</span>
                <span wire:loading wire:target="saveSocialPublications">Guardando...</span>
            </span>
        </button>
    </div>
</form>
