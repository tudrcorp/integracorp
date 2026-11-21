@auth
    @if (\App\Support\Farmadoc\FarmadocAcceptanceAlerts::userCanReceive(auth()->user()))
        @persist('operations-farmadoc-acceptance-alert')
            @livewire(\App\Livewire\Operations\FarmadocAcceptanceAlertModal::class)
        @endpersist
    @endif
@endauth
