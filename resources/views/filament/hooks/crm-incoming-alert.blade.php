@auth
    @if (\App\Support\CrmInbox\CrmIncomingAlerts::receives(auth()->user()))
        @persist('crm-incoming-alert')
            @livewire(\App\Livewire\CrmIncomingAlertModal::class)
        @endpersist
    @endif
@endauth
