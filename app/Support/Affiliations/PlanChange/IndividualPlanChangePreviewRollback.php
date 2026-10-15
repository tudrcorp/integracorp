<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PlanChange;

use RuntimeException;

/**
 * Revierte la transacción de una vista previa y lleva su resultado afuera. Uso interno de
 * IndividualAffiliationPlanChanger: la vista previa ejecuta el cambio real y lo deshace.
 */
final class IndividualPlanChangePreviewRollback extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(public readonly array $result)
    {
        parent::__construct('Vista previa del cambio de plan.');
    }
}
