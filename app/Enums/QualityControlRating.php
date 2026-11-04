<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Escala de satisfacción de las encuestas de Quality Control (formulario de Operaciones
 * y el Excel de respuestas que se importa).
 */
enum QualityControlRating: string
{
    case TotallySatisfied = 'TOTALMENTE_SATISFECHO';

    case Satisfied = 'SATISFECHO';

    case Neutral = 'NEUTRO';

    case Dissatisfied = 'INSATISFECHO';

    public function label(): string
    {
        return match ($this) {
            self::TotallySatisfied => 'Totalmente satisfecho',
            self::Satisfied => 'Satisfecho',
            self::Neutral => 'Neutro',
            self::Dissatisfied => 'Insatisfecho',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TotallySatisfied, self::Satisfied => 'success',
            self::Neutral => 'warning',
            self::Dissatisfied => 'danger',
        };
    }

    public function isSatisfied(): bool
    {
        return $this === self::TotallySatisfied || $this === self::Satisfied;
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

    /**
     * Acepta el texto del Excel («Totalmente satisfecho», «SATISFECHO», «neutro»…) o el valor guardado.
     */
    public static function fromText(mixed $text): ?self
    {
        $key = Str::of(Str::ascii(trim((string) $text)))->upper()->replaceMatches('/[^A-Z]+/', '_')->trim('_')->toString();

        return $key === '' ? null : self::tryFrom($key);
    }
}
