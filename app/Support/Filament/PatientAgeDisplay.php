<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Support\PatientAge;
use Closure;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Cierres listos para mostrar la edad legible en tablas, infolists y
 * formularios Filament ({@see PatientAge}).
 *
 * La columna o el campo siguen leyendo la edad numérica guardada; aquí solo se
 * cambia el texto: un bebé de 5 meses deja de verse como «0».
 */
final class PatientAgeDisplay
{
    /**
     * Para `TextColumn::make('age')->formatStateUsing(...)` y `TextEntry`.
     *
     * @param  string|null  $birthDatePath  atributo o ruta del registro con la fecha de nacimiento (`birth_date`, `telemedicinePatient.birth_date`); null usa solo la edad guardada
     */
    public static function formatter(?string $birthDatePath = 'birth_date'): Closure
    {
        return static fn (mixed $state, mixed $record = null): string => PatientAge::label(
            $birthDatePath !== null && $record !== null ? data_get($record, $birthDatePath) : null,
            $state,
        );
    }

    /**
     * Pista bajo el campo numérico de edad de un formulario: solo aparece
     * cuando la persona tiene menos de 2 años, que es cuando el número engaña.
     */
    public static function inputHint(string $birthDateField = 'birth_date'): Closure
    {
        return static function (Get $get, mixed $state) use ($birthDateField): ?string {
            $birthDate = $get($birthDateField);
            $years = PatientAge::years($birthDate, $state);

            if ($years === null || $years >= 2) {
                return null;
            }

            return 'Equivale a: '.PatientAge::label($birthDate, $state);
        };
    }
}
