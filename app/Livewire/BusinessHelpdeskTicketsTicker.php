<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
            ->select(['id', 'description', 'created_by'])
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

        $creatorName = $ticket->created_by !== null && trim((string) $ticket->created_by) !== ''
            ? e((string) $ticket->created_by)
            : '—';
        $descriptionPlain = strip_tags((string) ($ticket->description ?? ''));
        $descriptionInner = $descriptionPlain !== ''
            ? '<span class="font-semibold text-primary-600 dark:text-primary-400">'.e(Str::limit($descriptionPlain, 400)).'</span>'
            : '<span class="font-semibold text-primary-600 dark:text-primary-400"><em>Sin descripción</em></span>';

        $bodyHtml = '<div class="text-sm space-y-2">'
            .'<p><span class="font-medium text-gray-950 dark:text-white">Registrado por:</span> '
            .'<span class="text-gray-700 dark:text-gray-200">'.$creatorName.'</span></p>'
            .'<p><span class="font-medium text-gray-950 dark:text-white">Descripción:</span> '.$descriptionInner.'</p>'
            .'</div>';

        Notification::make()
            ->title('Ticket #'.$ticket->id)
            ->success()
            ->body($bodyHtml)
            ->actions([
                Action::make('marcarEnProceso')
                    ->label('Cambiar estatus a En proceso')
                    ->button()
                    ->url(route('business.helpdesk-ticket.mark-in-progress', ['helpDesk' => $ticket->id]))
                    ->postToUrl(),
                Action::make('verTablaTickets')
                    ->label('Ir a la tabla de tickets')
                    ->color('gray')
                    ->url(HelpdeskResource::getUrl('index')),
            ])
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
