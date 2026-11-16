<div class="flex flex-col gap-4 text-sm text-slate-700 dark:text-slate-200">
    <p>Envíele a la persona el enlace o el código por un canal de confianza. Puede usar cualquiera de las tres formas:</p>

    <div class="flex flex-col gap-2">
        <p class="font-semibold">1 · Enlace (tocarlo desde el teléfono y pulsar «Iniciar»)</p>
        <div x-data="{ copied: false }" class="flex w-full items-center gap-2">
            <input type="text" readonly value="{{ $url }}"
                class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                x-on:focus="$event.target.select()">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-clipboard-document"
                x-on:click="navigator.clipboard.writeText(@js($url)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                <span x-show="! copied">Copiar</span>
                <span x-show="copied" x-cloak>Copiado</span>
            </x-filament::button>
        </div>
    </div>

    @if ($code !== '')
        <div class="flex flex-col gap-2">
            <p class="font-semibold">2 · Código (buscar {{ '@'.$bot }} en Telegram, pulsar «Iniciar» y escribirlo)</p>
            <div class="rounded-2xl bg-slate-100 px-4 py-3 text-center font-mono text-3xl font-bold tracking-[0.3em] text-slate-900 dark:bg-white/10 dark:text-white">
                {{ \App\Support\Telegram\TelegramLinkService::formatShortCode($code) }}
            </div>
        </div>
    @endif

    @if ($qr !== '')
        <div class="flex flex-col items-center gap-2">
            <p class="self-start font-semibold">3 · QR (con la cámara del teléfono, no con el escáner de Telegram)</p>
            <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:ring-white/10">{!! $qr !!}</div>
        </div>
    @endif

    <p class="text-xs text-amber-600 dark:text-amber-400">
        Sirven una sola vez y vencen a las {{ $expiresAt }}. No los comparta en grupos: quien los use primero queda vinculado.
    </p>
</div>
