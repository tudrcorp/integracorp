<?php

declare(strict_types=1);

namespace App\Observers;

use App\Support\Collections\DroppedAffiliationCollections;
use Illuminate\Database\Eloquent\Model;

/**
 * Al dar de baja una afiliación (individual o corporativa) anula sus cuotas por pagar que
 * vencen desde ese día; al reactivarla las restituye. Escucha el modelo para cubrir todos
 * los caminos de baja (tablas, fichas, ediciones) sin depender de cuál se use.
 */
class AffiliationDropoutCollectionsObserver
{
    public function updated(Model $affiliation): void
    {
        DroppedAffiliationCollections::handleStatusChange($affiliation);
    }
}
