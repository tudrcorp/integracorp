<div class="p-1">
    @livewire(
        \App\Livewire\Commercial\AffiliationAffiliatesTable::class,
        ['affiliationId' => $affiliationId, 'corporate' => $corporate],
        key('commercial-affiliates-'.($corporate ? 'corporativa' : 'individual').'-'.$affiliationId)
    )
</div>
