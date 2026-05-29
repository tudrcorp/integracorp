@if (\App\Support\Filament\ProjectsPanelHelpdeskTicketsTicker::shouldDisplay())
    @include('filament.hooks.business-helpdesk-tickets-ticker-wrapper', ['fullWidth' => $fullWidth ?? true])
@endif
