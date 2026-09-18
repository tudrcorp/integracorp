<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\PlanGenerator;
use Filament\Actions\Imports\Events\ImportStarted;

/**
 * Deja constancia en el plan generado del import de población que acaba de
 * encolarse.
 *
 * Sin esto la ficha no podría distinguir «todavía no importó nada» de «el
 * import está corriendo y aún no escribió filas», y el analista podría crear la
 * afiliación corporativa con el padrón a medio cargar.
 */
class StampPlanGeneratorPopulationImport
{
    public function handle(ImportStarted $event): void
    {
        $planGeneratorId = (int) ($event->getOptions()['plan_generator_id'] ?? 0);

        if ($planGeneratorId <= 0) {
            return;
        }

        PlanGenerator::query()
            ->whereKey($planGeneratorId)
            ->update(['population_import_id' => $event->getImport()->getKey()]);
    }
}
