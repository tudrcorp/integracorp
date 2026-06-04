<button
    type="button"
    class="fi-operations-case-chat-topbar-btn"
    x-data
    x-on:click="Livewire.dispatch('operations-case-chat-open')"
    title="Chat de casos en seguimiento"
    aria-label="Abrir chat de casos en seguimiento"
>
    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="size-5 shrink-0" />
    <span class="hidden lg:inline">Chat casos</span>
</button>
