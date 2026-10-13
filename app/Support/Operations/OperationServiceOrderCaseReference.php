<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Support\Telemedicine\TelemedicineDocumentCaseCode;

/**
 * Identificadores del caso de telemedicina que se imprimen en los documentos
 * de Operaciones: el número del caso (`89928-0732`) y la clave de referencia
 * de la consulta (`REF-58180`).
 */
final class OperationServiceOrderCaseReference
{
    public const CASE_LABEL = TelemedicineDocumentCaseCode::LABEL;

    public static function caseCode(OperationServiceOrder $order): ?string
    {
        return self::caseCodeForCoordination($order->operationCoordinationService);
    }

    /**
     * Usa la relación si ya viene cargada; si no, una consulta puntual por el
     * caso, sin scopes globales (un caso eliminado conserva su número).
     */
    public static function caseCodeForCoordination(?OperationCoordinationService $coordination): ?string
    {
        if ($coordination === null) {
            return null;
        }

        if ($coordination->relationLoaded('telemedicineCase')) {
            $code = trim((string) $coordination->telemedicineCase?->code);

            if ($code !== '') {
                return $code;
            }
        }

        return TelemedicineDocumentCaseCode::forCaseId($coordination->telemedicine_case_id);
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
