<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineServiceList;

/**
 * Tipo de servicio que muestra el badge del informe médico: el que el médico eligió en la
 * consulta («Tipo de Servicio»), no un texto fijo.
 */
final class TelemedicineInformeServiceType
{
    public const FALLBACK = 'Telemedicina';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function forInformeData(array $data): string
    {
        if (filled($data['service_type_label'] ?? null)) {
            return trim((string) $data['service_type_label']);
        }

        $serviceListId = $data['telemedicine_service_list_id'] ?? null;

        if (blank($serviceListId) && filled($data['telemedicine_consultation_id'] ?? null)) {
            $serviceListId = TelemedicineConsultationPatient::query()
                ->whereKey((int) $data['telemedicine_consultation_id'])
                ->value('telemedicine_service_list_id');
        }

        if (blank($serviceListId)) {
            return self::FALLBACK;
        }

        $name = trim((string) TelemedicineServiceList::query()->whereKey((int) $serviceListId)->value('name'));

        return $name !== '' ? $name : self::FALLBACK;
    }
}
