<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationServiceOrder;

/**
 * Identificadores del caso de telemedicina que se imprimen en la orden de
 * servicio: el código del caso (`89928-0732`) y la clave de referencia de la
 * consulta (`REF-58180`).
 */
final class OperationServiceOrderCaseReference
{
    public static function caseCode(OperationServiceOrder $order): ?string
    {
        $code = trim((string) $order->operationCoordinationService?->telemedicineCase?->code);

        return $code === '' ? null : $code;
    }

    /**
     * La coordinación guarda la clave al crearse; si falta, se toma de la
     * consulta que originó el servicio.
     */
    public static function referenceNumber(OperationServiceOrder $order): ?string
    {
        $coordination = $order->operationCoordinationService;

        foreach ([$coordination?->reference_number, $coordination?->telemedicineConsultationPatient?->code_reference] as $candidate) {
            $value = trim((string) $candidate);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
