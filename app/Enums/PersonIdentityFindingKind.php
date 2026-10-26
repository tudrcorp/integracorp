<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lo que encuentra el detector de personas duplicadas (Negocios → Afiliaciones).
 */
enum PersonIdentityFindingKind: string
{
    case SameDocument = 'MISMA_CEDULA';
    case SameNameAndBirthDate = 'MISMO_NOMBRE_Y_NACIMIENTO';
    case RepeatedName = 'NOMBRE_REPETIDO';

    public const SEVERITY_HIGH = 'ALTA';

    public const SEVERITY_LOW = 'BAJA';

    public function label(): string
    {
        return match ($this) {
            self::SameDocument => 'Misma persona: misma cédula',
            self::SameNameAndBirthDate => 'Misma persona: mismo nombre y nacimiento',
            self::RepeatedName => 'Nombre cargado dos veces',
        };
    }

    public function severity(): string
    {
        return $this === self::RepeatedName ? self::SEVERITY_LOW : self::SEVERITY_HIGH;
    }

    public function isDuplicate(): bool
    {
        return $this !== self::RepeatedName;
    }

    public function explanation(): string
    {
        return match ($this) {
            self::SameDocument => 'Los dos registros tienen la misma cédula una vez quitadas la letra, los espacios y los guiones, y el mismo nombre (en cualquier orden). Es la misma persona registrada dos veces.',
            self::SameNameAndBirthDate => 'Los dos registros tienen exactamente el mismo nombre y la misma fecha de nacimiento, aunque la cédula esté escrita distinto (por ejemplo, cédula de menor frente a cédula escolar). Es la misma persona registrada dos veces.',
            self::RepeatedName => 'El nombre se cargó repetido: los nombres y los apellidos traen lo mismo, o el nombre completo está escrito dos veces. Sale así en carnets, certificados y documentos.',
        };
    }

    public function consequence(): string
    {
        return match ($this) {
            self::SameDocument, self::SameNameAndBirthDate => 'La persona cuenta dos veces en población y cobranza, puede tener dos planes distintos y Telemedicina y Operaciones pueden atenderla con el plan equivocado.',
            self::RepeatedName => 'Los documentos y la búsqueda muestran un nombre incorrecto, y es más difícil reconocer a la persona si se vuelve a registrar.',
        };
    }

    public function repair(): string
    {
        return match ($this) {
            self::SameDocument, self::SameNameAndBirthDate => 'Decida cuál de los dos registros es el bueno. Dé de baja el otro, o corrija sus datos si en realidad son dos personas distintas mal cargadas. Si lo revisó y no es la misma persona, márquelo así.',
            self::RepeatedName => 'Corrija los nombres y apellidos del registro.',
        };
    }

    public static function severityColor(?string $severity): string
    {
        return $severity === self::SEVERITY_HIGH ? 'danger' : 'warning';
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
