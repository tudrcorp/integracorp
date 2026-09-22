<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Etapa de la consulta tal como se imprime en las órdenes de laboratorio,
 * imagenología y especialista: la consulta inicial, o cualquier otra cosa
 * —seguimiento, alta médica— como seguimiento, que es la misma regla con la
 * que se decide el informe de seguimiento.
 */
final class TelemedicineConsultationStageLabel
{
    public const INITIAL = 'Consulta Inicial';

    public const FOLLOW_UP = 'Seguimiento';

    /**
     * Cadena vacía cuando el documento no trae el estado: un PDF regenerado
     * desde un payload antiguo no debe afirmar una etapa que no conoce.
     */
    public static function forStatus(?string $status): string
    {
        $normalized = trim((string) $status);

        if ($normalized === '') {
            return '';
        }

        return mb_strtoupper($normalized) === TelemedicineInitialDiagnosisUpdater::INITIAL_STATUS
            ? self::INITIAL
            : self::FOLLOW_UP;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function forDocumentData(array $data): string
    {
        $status = $data['consultation_status'] ?? null;

        return self::forStatus(is_string($status) ? $status : null);
    }
}
