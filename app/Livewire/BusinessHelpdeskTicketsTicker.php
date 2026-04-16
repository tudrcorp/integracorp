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
        $priorityClasses = match ($priority) {
            'ALTA' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/35 dark:bg-rose-500/15 dark:text-rose-200',
            'BAJA' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/35 dark:bg-emerald-500/15 dark:text-emerald-200',
            default => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/35 dark:bg-amber-500/15 dark:text-amber-200',
        };
        $statusClasses = match ($status) {
            'EN PROCESO' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/35 dark:bg-sky-500/15 dark:text-sky-200',
            'TERMINADO' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/35 dark:bg-emerald-500/15 dark:text-emerald-200',
            'CANCELADO' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/35 dark:bg-rose-500/15 dark:text-rose-200',
            default => 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-500/35 dark:bg-slate-500/15 dark:text-slate-200',
        };
        $descriptionPlain = trim(strip_tags((string) ($ticket->description ?? '')));
        $descriptionInner = $descriptionPlain !== ''
            ? e(Str::limit($descriptionPlain, 260))
            : 'Sin descripción registrada.';
        $createdAt = $ticket->created_at?->format('d/m/Y H:i') ?? '—';

        $bodyHtml = '<div class="flex min-w-0 max-w-[30rem] flex-col gap-3 text-[0.9375rem] leading-[1.35]">'
            .'<div class="flex flex-wrap items-center justify-between gap-2">'
            .'<span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[0.72rem] font-bold tracking-[0.02em] '.$priorityClasses.'">Prioridad: '.e($priority).'</span>'
            .'<span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[0.72rem] font-bold tracking-[0.02em] '.$statusClasses.'">'.e($status).'</span>'
            .'</div>'
            .'<div class="grid grid-cols-[auto,1fr] gap-x-2.5 gap-y-1 text-[0.9rem]">'
            .'<span class="font-semibold text-slate-900 dark:text-slate-100">Registrado por:</span><span class="text-slate-700 dark:text-slate-300">'.$creatorName.'</span>'
            .'<span class="font-semibold text-slate-900 dark:text-slate-100">Fecha:</span><span class="text-slate-700 dark:text-slate-300">'.e($createdAt).'</span>'
            .'</div>'
            .'<div class="rounded-xl border border-amber-200 bg-gradient-to-b from-amber-50 to-amber-50/60 px-3 py-2.5 dark:border-amber-500/35 dark:from-amber-500/15 dark:to-amber-500/10">'
            .'<div class="mb-1 text-[0.78rem] font-extrabold uppercase tracking-[0.02em] text-amber-700 dark:text-amber-200">Descripción</div>'
            .'<div class="font-semibold leading-[1.45] text-amber-800 dark:text-amber-100">'.$descriptionInner.'</div>'
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
