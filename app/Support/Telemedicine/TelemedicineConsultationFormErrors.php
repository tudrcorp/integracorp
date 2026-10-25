<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Hace visibles al médico los errores que las reglas de negocio lanzan al
 * guardar la consulta.
 *
 * Esas reglas viven fuera del formulario y nombran el campo a secas
 * (`telemedicine_service_list_id`, `complements`, `feedbackOne`). Livewire solo
 * pinta un error si su clave coincide con la ruta del campo (`data.…`): con la
 * clave a secas el guardado se detenía y el médico no veía ningún mensaje.
 */
final class TelemedicineConsultationFormErrors
{
    public const STATE_PATH = 'data';

    /** Prefijos que ya apuntan a un lugar donde Livewire muestra el error. */
    private const VISIBLE_PREFIXES = [self::STATE_PATH.'.', 'mountedActions.'];

    /**
     * La misma excepción con cada clave apuntando al campo del formulario.
     */
    public static function visible(ValidationException $exception): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $key => $keyMessages) {
            $messages[self::fieldKey((string) $key)] = array_values((array) $keyMessages);
        }

        $visible = ValidationException::withMessages($messages);

        if ($exception->redirectTo !== null) {
            $visible->redirectTo($exception->redirectTo);
        }

        return $visible;
    }

    public static function fieldKey(string $key): string
    {
        foreach (self::VISIBLE_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return $key;
            }
        }

        return self::STATE_PATH.'.'.$key;
    }

    /**
     * Texto del aviso: el campo puede estar en otro paso del asistente.
     */
    public static function summary(ValidationException $exception): string
    {
        $messages = [];

        foreach ($exception->errors() as $keyMessages) {
            foreach ((array) $keyMessages as $message) {
                $messages[] = trim((string) $message);
            }
        }

        return Str::limit(implode(' ', array_unique(array_filter($messages))), 1000);
    }
}
