<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\PanelLog;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Models\TelemedicinePanelEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de lectura de la bitácora del panel de Telemedicina.
 */
final class TelemedicinePanelEventQueries
{
    /** Tiempo sin guardar a partir del cual una consulta abierta cuenta como abandonada. */
    public const ABANDON_AFTER_MINUTES = 30;

    public const SEQUENCE_LIMIT = 80;

    /** Ventana alrededor del evento cuando no hay un formulario que lo agrupe. */
    public const SEQUENCE_WINDOW_MINUTES = 90;

    /**
     * Formularios de consulta que el médico abrió y nunca guardó. Cada apertura
     * de la pantalla es un componente de Livewire distinto: si ese componente no
     * tiene un «guardado», el médico salió sin registrar.
     */
    public static function abandonedConsultationForms(Builder $query): Builder
    {
        return $query
            ->where('event', 'pagina_abierta')
            ->where('component', CreateTelemedicineConsultationPatient::class)
            ->where('occurred_at', '<=', now()->subMinutes(self::ABANDON_AFTER_MINUTES))
            ->whereNotExists(function ($saved): void {
                $saved->select(DB::raw(1))
                    ->from('telemedicine_panel_events as saved')
                    ->whereColumn('saved.livewire_id', 'telemedicine_panel_events.livewire_id')
                    ->where('saved.event', 'guardado');
            });
    }

    /**
     * Lo que hizo el mismo médico antes y después del evento: todo el recorrido de
     * ese formulario si lo hay, si no, su actividad en la misma ventana de tiempo
     * (y en el mismo caso cuando se conoce).
     *
     * @return Collection<int, TelemedicinePanelEvent>
     */
    public static function sequenceFor(TelemedicinePanelEvent $event): Collection
    {
        $query = TelemedicinePanelEvent::query();

        if (filled($event->livewire_id)) {
            $query->where('livewire_id', $event->livewire_id);
        } else {
            $from = $event->occurred_at?->copy()->subMinutes(self::SEQUENCE_WINDOW_MINUTES);
            $until = $event->occurred_at?->copy()->addMinutes(self::SEQUENCE_WINDOW_MINUTES);

            $query
                ->when($event->user_id !== null, fn (Builder $q): Builder => $q->where('user_id', $event->user_id))
                ->when($event->user_id === null, fn (Builder $q): Builder => $q->where('ip', $event->ip))
                ->when($event->telemedicine_case_id !== null, fn (Builder $q): Builder => $q->where('telemedicine_case_id', $event->telemedicine_case_id))
                ->when($from !== null, fn (Builder $q): Builder => $q->whereBetween('occurred_at', [$from, $until]));
        }

        return $query
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(self::SEQUENCE_LIMIT)
            ->get(['id', 'occurred_at', 'level', 'category', 'event', 'title', 'message', 'step', 'action', 'screen']);
    }
}
