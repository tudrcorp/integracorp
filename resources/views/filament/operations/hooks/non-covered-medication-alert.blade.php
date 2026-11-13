@auth
    @if (\App\Support\Operations\NonCoveredMedicationAlerts::userCanReceive(auth()->user()))
        @persist('operations-non-covered-medication-alert')
            @livewire(\App\Livewire\Operations\NonCoveredMedicationAlertModal::class)
        @endpersist
    @endif
@endauth
