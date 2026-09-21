<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PlanGenerator;

class PlanGeneratorObserver
{
    /**
     * Antes de borrar un registro base, libera a sus cotizaciones derivadas.
     *
     * Una derivada es un documento comercial propio: puede estar aprobada o ya
     * en pre-afiliación. Borrar la plantilla no puede arrastrarla ni dejarla
     * apuntando a una fila que no existe —eso rompería la agrupación de la
     * tabla, porque el título de la familia se resuelve desde el base—. Al
     * quedar con `parent_id` nulo cada derivada pasa a ser su propio base.
     */
    public function deleting(PlanGenerator $planGenerator): void
    {
        $planGenerator->derivedQuotations()
            ->getQuery()
            ->update(['parent_id' => null]);
    }
}
