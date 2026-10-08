@if (! request()->routeIs([
    'filament.business.pages.atencion-whatsapp',
    'filament.operations.pages.atencion-whatsapp',
    'filament.administration.pages.atencion-whatsapp',
]))
    @livewire(\App\Livewire\BusinessHelpdeskTicketsTicker::class, ['fullWidth' => $fullWidth ?? true], key('filament-business-helpdesk-ticker'))
@endif
