<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ProspectAgent;
use App\Support\ProspectAgents\ProspectAgentToTravelAgencyConverter;

/**
 * Al crear o actualizar un prospecto de Captación con estatus «Aliado activo»,
 * garantiza su ficha en Agencias de viaje (cubre Filament, CSV e imports).
 */
class ProspectAgentTravelAgencyObserver
{
    public function created(ProspectAgent $prospect): void
    {
        ProspectAgentToTravelAgencyConverter::convertIfEligible($prospect);
    }

    public function updated(ProspectAgent $prospect): void
    {
        if (! $prospect->wasChanged('status')) {
            return;
        }

        ProspectAgentToTravelAgencyConverter::convertIfEligible($prospect);
    }
}
