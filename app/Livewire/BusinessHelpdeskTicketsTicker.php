<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Support\HelpdeskTicketQuickSummary;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BusinessHelpdeskTicketsTicker extends Component
{
    public bool $fullWidth = false;

    public function openTicketNotification(int $helpDeskId): void
    {
        $colaborador = $this->resolveColaborador();
        if (! $colaborador) {
            Notification::make()
                ->title('Ticket no disponible')
                ->danger()
                ->body('No tienes acceso a este ticket o ya no existe.')
                ->send();
            $this->skipRender();

            return;
        }

        $ticket = HelpDesk::query()
            ->select(['id', 'description', 'created_by', 'priority', 'status', 'created_at'])
            ->whereKey($helpDeskId)
            ->whereHas(
                'rrhhColaboradores',
                fn (Builder $sub): Builder => $sub->where('rrhh_colaboradors.id', $colaborador->id)
            )
            ->first();

        if (! $ticket) {
            Notification::make()
                ->title('Ticket no disponible')
                ->danger()
                ->body('No tienes acceso a este ticket o ya no existe.')
                ->send();
            $this->skipRender();

            return;
        }

        $priority = trim((string) ($ticket->priority ?? '')) !== '' ? strtoupper(trim((string) $ticket->priority)) : 'MEDIA';
        $status = trim((string) ($ticket->status ?? '')) !== '' ? strtoupper(trim((string) $ticket->status)) : 'PENDIENTE POR INICIAR';
        $reporter = HelpdeskTicketQuickSummary::displayName($ticket->created_by);

        $body = view('filament.helpdesks.ticket-quick-summary', [
            'priority' => $priority,
            'status' => $status,
            'reporter' => $reporter,
            'createdAt' => $ticket->created_at?->format('d/m/Y h:i a') ?? 'Sin fecha',
            'createdAtRelative' => $ticket->created_at?->locale('es')->diffForHumans(),
            'summary' => HelpdeskTicketQuickSummary::fromDescription($ticket->description, $ticket->created_by, $reporter),
        ])->render();

        $actions = [];

        if ($status === 'PENDIENTE POR INICIAR') {
            $actions[] = Action::make('marcarEnProceso')
                ->label('Marcar en proceso')
                ->button()
                ->color('primary')
                ->icon('heroicon-m-play')
                ->url(route('business.helpdesk-ticket.mark-in-progress', ['helpDesk' => $ticket->id]))
                ->postToUrl();
        }

        $openTicket = Action::make('abrirTicket')
            ->label('Abrir ticket')
            ->icon('heroicon-m-arrow-top-right-on-square')
            ->url(HelpdeskResource::getUrl('view', ['record' => $ticket->id]));

        $actions[] = $actions === []
            ? $openTicket->button()->color('primary')
            : $openTicket->link()->color('gray');

        Notification::make()
            ->title('Ticket #'.$ticket->id)
            ->icon('heroicon-o-ticket')
            ->iconColor('gray')
            ->body($body)
            ->persistent()
            ->actions($actions)
            ->send();

        $this->skipRender();
    }

    public function render(): View
    {
        return view('livewire.business-helpdesk-tickets-ticker', [
            'tickets' => $this->assignedTickets(),
            'fullWidth' => $this->fullWidth,
        ]);
    }

    protected function assignedTickets(): Collection
    {
        $colaborador = $this->resolveColaborador();
        if (! $colaborador) {
            return collect();
        }

        return HelpDesk::query()
            ->whereHas(
                'rrhhColaboradores',
                fn (Builder $q): Builder => $q->where('rrhh_colaboradors.id', $colaborador->id)
            )
            ->whereIn('status', ['PENDIENTE POR INICIAR', 'EN PROCESO'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    protected function resolveColaborador(): ?RrhhColaborador
    {
        $userId = Auth::id();
        if ($userId === null) {
            return null;
        }

        return RrhhColaborador::query()->where('user_id', $userId)->first();
    }
}
