@auth
    @livewire(\App\Livewire\Operations\CaseFollowUpChatPanel::class, key('operations-case-follow-up-chat-panel'))
@else
    <script>
        try {
            sessionStorage.removeItem('fi-operations-case-chat-state');
        } catch (_) {}
    </script>
@endauth
