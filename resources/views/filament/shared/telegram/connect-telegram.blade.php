<x-filament-panels::page>
    @php
        $recipient = $this->recipient;
        $linked = $this->isLinked();
        $bot = $this->botUsername();
    @endphp

    <div class="flex flex-col gap-6" @if ($linkUrl) wire:poll.5s="checkLinked" @endif>
        <x-filament::section icon="heroicon-o-paper-airplane" heading="Su estado">
            @if ($linked)
                <div class="flex flex-col gap-2">
                    <x-filament::badge color="success" icon="heroicon-m-check-circle" class="w-fit">Vinculado</x-filament::badge>
                    <p class="text-sm text-slate-700 dark:text-slate-200">
                        Recibe los avisos en Telegram{{ $recipient?->telegram_username ? ' como @'.$recipient->telegram_username : '' }}
                        desde el {{ $recipient?->linked_at?->timezone(config('app.timezone'))->format('d/m/Y h:i A') }}.
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Si algún día Telegram falla, el aviso le llega por WhatsApp o correo: no se pierde.</p>
                </div>
            @elseif ($recipient?->status === \App\Enums\TelegramRecipientStatus::Blocked)
                <div class="flex flex-col gap-2">
                    <x-filament::badge color="danger" class="w-fit">Bot bloqueado</x-filament::badge>
                    <p class="text-sm text-slate-700 dark:text-slate-200">Telegram nos avisó que bloqueó el bot. Desbloquéelo en Telegram o genere un enlace nuevo para volver a vincularse. Mientras tanto, sus avisos llegan por WhatsApp o correo.</p>
                </div>
            @else
                <div class="flex flex-col gap-3">
                    <x-filament::badge color="gray" class="w-fit">Sin vincular</x-filament::badge>
                    <p class="text-sm text-slate-700 dark:text-slate-200">
                        Instale Telegram en su teléfono (es gratis) y pulse <strong>«Generar enlace»</strong> arriba a la derecha. Luego elija la forma que le resulte más fácil.
                    </p>
                </div>
            @endif
        </x-filament::section>

        @if ($linkUrl)
            <div class="grid gap-6 lg:grid-cols-3">
                <x-filament::section icon="heroicon-o-chat-bubble-left-ellipsis" heading="Opción 1 · Por WhatsApp" description="La más fácil.">
                    <div class="flex flex-col gap-3 text-sm text-slate-700 dark:text-slate-200">
                        <p>Le enviamos el enlace a su WhatsApp. Ábralo <strong>desde el teléfono</strong>, toque el enlace y pulse <strong>Iniciar</strong> en Telegram.</p>
                        <div>{{ $this->sendLinkByWhatsAppAction }}</div>
                        <div class="text-xs">¿No tiene WhatsApp en ese teléfono? {{ $this->sendLinkByEmailAction }}</div>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-key" heading="Opción 2 · Con un código" description="Sin escanear nada.">
                    <div class="flex flex-col gap-3 text-sm text-slate-700 dark:text-slate-200">
                        <p>Abra Telegram, busque <strong>{{ '@'.$bot }}</strong>, pulse <strong>Iniciar</strong> y escríbale este código:</p>
                        <div class="rounded-2xl bg-slate-100 px-4 py-3 text-center font-mono text-3xl font-bold tracking-[0.3em] text-slate-900 dark:bg-white/10 dark:text-white">
                            {{ \App\Support\Telegram\TelegramLinkService::formatShortCode((string) $linkCode) }}
                        </div>
                        <x-filament::button tag="a" href="https://t.me/{{ $bot }}" target="_blank" rel="noopener" color="gray" size="sm" icon="heroicon-o-arrow-top-right-on-square">
                            Abrir {{ '@'.$bot }}
                        </x-filament::button>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-qr-code" heading="Opción 3 · Con el QR" description="Con la cámara del teléfono.">
                    <div class="flex flex-col items-center gap-3 text-center">
                        @php($qr = $this->qrSvg())
                        @if ($qr !== '')
                            <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:ring-white/10">{!! $qr !!}</div>
                        @endif
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                            Use la <strong>cámara</strong> del teléfono (o Google Lens). El escáner de Telegram <strong>no</strong> sirve: solo inicia sesión en la computadora.
                        </p>
                    </div>
                </x-filament::section>
            </div>

            <p class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <x-filament::loading-indicator class="h-4 w-4" />
                Esperando que pulse «Iniciar» o envíe el código… El enlace y el código vencen a las {{ $linkExpiresAt }} y sirven una sola vez. No los comparta: quien los use primero queda vinculado a su cuenta.
            </p>
        @endif
    </div>
</x-filament-panels::page>
