<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de documento venezolano que acompaña al número (cédula o RIF).
 *
 * El número se guarda solo con dígitos en su columna de siempre; la letra vive aparte
 * (`document_type_*`) para no romper búsquedas, el candado de personas duplicadas ni los
 * puentes con telemedicina y cobranza, que comparan el número tal cual.
 */
enum IdentityDocumentType: string
{
    case V = 'V';

    case E = 'E';

    case J = 'J';

    case G = 'G';

    public function label(): string
    {
        return match ($this) {
            self::V => 'V - Venezolano',
            self::E => 'E - Extranjero',
            self::J => 'J - Jurídico',
            self::G => 'G - Gubernamental',
        };
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
