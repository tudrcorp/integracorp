<div class="fi-clinic-coordination-docs-modal-embed max-h-[min(78vh,48rem)] overflow-y-auto p-1">
    @livewire(\App\Livewire\Operations\ClinicCoordinationDocumentsManager::class, [
        'serviceId' => $serviceId,
        'readOnly' => $readOnly,
    ], key('clinic-coordination-docs-'.$serviceId))
</div>
