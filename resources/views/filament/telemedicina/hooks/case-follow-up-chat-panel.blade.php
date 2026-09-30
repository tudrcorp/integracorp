@auth
    @persist('telemedicine-case-follow-up-chat-panel')
        @livewire(\App\Livewire\Operations\CaseFollowUpChatPanel::class, ['context' => \App\Support\Operations\CaseFollowUpChatManager::CONTEXT_TELEMEDICINE])
    @endpersist
@endauth
