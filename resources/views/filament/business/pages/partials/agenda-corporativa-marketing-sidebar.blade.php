<div class="mb-3 flex items-center justify-between gap-2">
    <p class="text-xs font-semibold uppercase tracking-wide text-[#0A4174] dark:text-[#BDD8E9]">Publicaciones del día</p>
    <button
        type="button"
        wire:click="startCreateSocialPublication"
        wire:loading.attr="disabled"
        wire:target="startCreateSocialPublication"
        class="rounded-xl border border-[#4E8EA2]/80 bg-[#0A4174] px-3 py-1.5 text-[11px] font-semibold text-[#BDD8E9] shadow-[0_8px_18px_rgba(10,65,116,0.35)] transition hover:bg-[#49769F] dark:border-[#6EA2B3]/60 dark:shadow-[0_10px_20px_rgba(10,65,116,0.4)]"
    >
        <span class="inline-flex items-center gap-1">
            <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="startCreateSocialPublication" class="size-3.5 animate-spin" />
            <span>Programar redes</span>
        </span>
    </button>
</div>

<div class="mb-3 rounded-2xl border border-[#6EA2B3]/70 bg-[#BDD8E9]/50 px-3 py-2 text-[11px] text-[#0A4174] dark:border-[#6EA2B3]/45 dark:bg-[#0A4174]/35 dark:text-[#BDD8E9]">
    Marca las redes donde habrá publicación. Los iconos también aparecen en el calendario mensual.
</div>

@if (! empty($this->selectedDateSocialPublicationReferencePreviews))
    <div class="mb-3 rounded-2xl border border-slate-200/80 bg-white/90 p-2 dark:border-white/10 dark:bg-slate-900/80">
        <p class="mb-2 px-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">Referencias visuales</p>
        <div class="grid grid-cols-4 gap-1.5">
            @foreach ($this->selectedDateSocialPublicationReferencePreviews as $attachment)
                <div class="overflow-hidden rounded-lg border border-slate-200/80 bg-slate-50 dark:border-white/10 dark:bg-slate-800/80">
                    @if ($attachment['is_image'])
                        <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}" class="h-12 w-full object-cover">
                    @else
                        <div class="flex h-12 items-center justify-center text-[10px] font-semibold uppercase text-slate-600 dark:text-slate-200">
                            {{ $attachment['ext'] }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
    @forelse ($this->selectedDateSocialPublications as $publication)
        @php
            $platformValue = $publication->platform?->value ?? (string) $publication->getRawOriginal('platform');
            $platformMeta = \App\Support\CorporateAgendaSocialPlatformCatalog::for($platformValue);
            $isSelected = $selectedSocialPublicationId === $publication->id;
        @endphp
        <article
            class="w-full rounded-2xl border p-3 text-left transition
            {{ $isSelected
                ? 'border-[#4E8EA2]/80 bg-[#BDD8E9]/55 shadow-[0_8px_22px_rgba(78,142,162,0.2)] dark:border-[#6EA2B3]/65 dark:bg-[#0A4174]/45'
                : 'border-slate-200/80 bg-white/90 hover:border-[#7BBDE8]/80 hover:bg-[#BDD8E9]/30 dark:border-white/10 dark:bg-slate-900/80 dark:hover:border-[#6EA2B3]/65 dark:hover:bg-[#0A4174]/25' }}"
        >
            <button type="button" wire:click="selectSocialPublication({{ $publication->id }})" class="w-full text-left">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <x-corporate-agenda-social-icon :platform="$platformValue" size="md" />
                        <span>
                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $platformMeta['label'] }}</p>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ $publication->creator?->name ?: 'Sin creador' }}</p>
                        </span>
                    </div>
                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $platformMeta['chip_class'] }}">
                        Programada
                    </span>
                </div>
                @if (filled($publication->brief))
                    <p class="mt-2 line-clamp-2 text-[11px] text-slate-600 dark:text-slate-300">{{ $publication->brief }}</p>
                @endif

                @if (is_array($publication->attachments) && $publication->attachments !== [])
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach (collect($publication->attachments)->take(3) as $path)
                            @php
                                $pathText = trim((string) $path);
                                $exists = $pathText !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($pathText);
                                $ext = \Illuminate\Support\Str::lower(pathinfo($pathText, PATHINFO_EXTENSION));
                                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                                $url = $exists ? \Illuminate\Support\Facades\Storage::disk('public')->url($pathText) : null;
                            @endphp
                            @if ($exists)
                                <div class="overflow-hidden rounded-md border border-slate-200/80 dark:border-white/10">
                                    @if ($isImage && $url)
                                        <img src="{{ $url }}" alt="Adjunto publicación" class="size-8 object-cover">
                                    @else
                                        <div class="flex size-8 items-center justify-center bg-slate-100 text-[9px] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                            {{ $ext !== '' ? $ext : 'doc' }}
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </button>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-[#6EA2B3]/60 bg-white/70 px-4 py-8 text-center dark:border-[#6EA2B3]/35 dark:bg-slate-900/50">
            <p class="text-xs text-slate-500 dark:text-slate-400">Sin publicaciones programadas para este día.</p>
            <p class="mt-1 text-[11px] text-[#0A4174] dark:text-[#7BBDE8]">Usa el formulario para planificar Instagram, YouTube, X o Facebook.</p>
        </div>
    @endforelse
</div>
