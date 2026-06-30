<div
    x-show="$store.guiaChatUi.isTypingVisible()"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-1"
    wire:ignore
    class="flex items-start gap-2.5 justify-start"
    aria-live="polite"
    aria-busy="true"
>
    <div class="relative shrink-0">
        <span class="absolute -inset-1 animate-ping rounded-full bg-emerald-400/30"></span>
        <span class="absolute inset-0 rounded-full bg-emerald-400/20 blur-sm"></span>
        <img
            src="{{ asset('images/chat/assistant-avatar.png') }}"
            alt=""
            class="relative h-8 w-8 rounded-full border border-white/30 bg-white object-cover shadow-md ring-2 ring-emerald-300/20 sm:h-9 sm:w-9"
        />
    </div>

    <div
        class="inline-flex min-h-[2.5rem] items-center gap-2 rounded-2xl rounded-bl-md border border-white/15 bg-white/10 px-4 py-2.5 shadow-[0_8px_24px_-12px_rgba(0,0,0,0.45)] backdrop-blur-md"
        aria-label="El asistente está escribiendo"
    >
        <span class="guia-chat-typing-dots flex items-center gap-1" aria-hidden="true">
            <span class="guia-chat-typing-dot"></span>
            <span class="guia-chat-typing-dot"></span>
            <span class="guia-chat-typing-dot"></span>
        </span>
        <span class="text-xs font-medium tracking-wide text-white/55 sm:text-[13px]">Escribiendo</span>
    </div>
</div>
