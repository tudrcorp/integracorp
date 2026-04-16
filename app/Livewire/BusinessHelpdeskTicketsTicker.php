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

        $creatorName = $ticket->created_by !== null && trim((string) $ticket->created_by) !== ''
            ? e((string) $ticket->created_by)
            : '—';
        $priority = trim((string) ($ticket->priority ?? 'MEDIA')) !== '' ? (string) $ticket->priority : 'MEDIA';
        $status = trim((string) ($ticket->status ?? 'PENDIENTE POR INICIAR')) !== '' ? (string) $ticket->status : 'PENDIENTE POR INICIAR';
        $priorityPalette = match ($priority) {
            'ALTA' => ['bg' => '#FEE2E2', 'text' => '#9F1239', 'border' => '#FECACA'],
            'BAJA' => ['bg' => '#DCFCE7', 'text' => '#065F46', 'border' => '#BBF7D0'],
            default => ['bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#FDE68A'],
        };
        $statusPalette = match ($status) {
            'EN PROCESO' => ['bg' => '#DBEAFE', 'text' => '#1D4ED8', 'border' => '#BFDBFE'],
            'TERMINADO' => ['bg' => '#DCFCE7', 'text' => '#166534', 'border' => '#BBF7D0'],
            'CANCELADO' => ['bg' => '#FEE2E2', 'text' => '#B91C1C', 'border' => '#FECACA'],
            default => ['bg' => '#F3F4F6', 'text' => '#374151', 'border' => '#E5E7EB'],
        };
        $descriptionPlain = trim(strip_tags((string) ($ticket->description ?? '')));
        $descriptionInner = $descriptionPlain !== ''
            ? e(Str::limit($descriptionPlain, 260))
            : 'Sin descripción registrada.';
        $createdAt = $ticket->created_at?->format('d/m/Y H:i') ?? '—';

        $bodyHtml = '<div style="display:flex;flex-direction:column;gap:0.75rem;min-width:min(100%,30rem);font-size:0.9375rem;line-height:1.35;">'
            .'<div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;">'
            .'<span style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.25rem 0.65rem;border-radius:9999px;font-size:0.72rem;font-weight:700;letter-spacing:0.02em;background:'.$priorityPalette['bg'].';color:'.$priorityPalette['text'].';border:1px solid '.$priorityPalette['border'].';">Prioridad: '.e($priority).'</span>'
            .'<span style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.25rem 0.65rem;border-radius:9999px;font-size:0.72rem;font-weight:700;letter-spacing:0.02em;background:'.$statusPalette['bg'].';color:'.$statusPalette['text'].';border:1px solid '.$statusPalette['border'].';">'.e($status).'</span>'
            .'</div>'
            .'<div style="display:grid;grid-template-columns:auto 1fr;gap:0.35rem 0.6rem;font-size:0.9rem;">'
            .'<span style="font-weight:700;color:#111827;">Registrado por:</span><span style="color:#374151;">'.$creatorName.'</span>'
            .'<span style="font-weight:700;color:#111827;">Fecha:</span><span style="color:#374151;">'.e($createdAt).'</span>'
            .'</div>'
            .'<div style="border:1px solid #FDE68A;background:linear-gradient(180deg,#FFF9DB 0%,#FFFBEB 100%);border-radius:0.85rem;padding:0.7rem 0.8rem;">'
            .'<div style="font-size:0.78rem;font-weight:800;letter-spacing:0.02em;text-transform:uppercase;color:#B45309;margin-bottom:0.35rem;">Descripción</div>'
            .'<div style="font-weight:600;color:#92400E;line-height:1.45;">'.$descriptionInner.'</div>'
            .'</div>'
            .'</div>';

        Notification::make()
            ->title('Ticket #'.$ticket->id.' · Resumen rápido')
            ->icon('heroicon-m-ticket')
            ->iconColor('warning')
            ->warning()
            ->body($bodyHtml)
            ->duration(12000)
            ->actions([
                Action::make('marcarEnProceso')
                    ->label('Cambiar estatus a En proceso')
                    ->button()
                    ->color('warning')
                    ->icon('heroicon-m-arrow-path')
                    ->extraAttributes([
                        'class' => 'aviso-btn-ios-warning shrink-0 inline-flex min-w-[12rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]',
                    ])
                    ->url(route('business.helpdesk-ticket.mark-in-progress', ['helpDesk' => $ticket->id]))
                    ->postToUrl(),
                Action::make('verTablaTickets')
                    ->label('Ir a la tabla de tickets')
                    ->button()
                    ->color('gray')
                    ->icon('heroicon-m-table-cells')
                    ->extraAttributes([
                        'class' => 'ticket-btn-ios-shell shrink-0 inline-flex min-w-[10rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]',
                    ])
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
