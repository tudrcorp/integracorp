<?php

declare(strict_types=1);

namespace App\Observers;

use App\Support\Affiliations\PersonIdentity\PersonIdentityGuard;
use App\Support\Affiliations\PersonIdentity\PersonIdentityIndex;
use Illuminate\Database\Eloquent\Model;

/**
 * Afiliados individuales, corporativos y asociados de Nuevos Negocios: en cada guardado recalcula
 * la identidad comparable y bloquea si la persona ya está vigente en otro registro.
 *
 * Al estar en el modelo cubre todas las puertas de entrada (formularios de los paneles, importación,
 * actualización de población, registros públicos) sin depender de que cada una se acuerde.
 */
class PersonIdentityObserver
{
    public function saving(Model $model): void
    {
        if (! PersonIdentityIndex::isAvailable($model)) {
            return;
        }

        $subject = PersonIdentityGuard::completeSubject($model);

        $model->forceFill(PersonIdentityIndex::keysFor($subject));

        PersonIdentityGuard::assertModelIsValid($model, $subject);
    }
}
