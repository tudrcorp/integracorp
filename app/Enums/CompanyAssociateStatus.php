<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

enum CompanyAssociateStatus: string
{
    use ResolvesFromMixedState;

    case ActivoSinVaucherIls = 'ACTIVO-SIN-VAUCHER-ILS';
    case Activo = 'ACTIVO';
    case Anulado = 'ANULADO';

    public function label(): string
    {
        return match ($this) {
            self::ActivoSinVaucherIls => 'Activo sin voucher ILS',
            self::Activo => 'Activo',
            self::Anulado => 'Anulado',
        };
    }

    /**
     * Color de badge Filament (success, danger, warning, gray, info, primary).
     */
    public function filamentColor(): string
    {
        return match ($this) {
            self::ActivoSinVaucherIls => 'warning',
            self::Activo => 'success',
            self::Anulado => 'danger',
        };
    }

    public function consumesRegistrationDay(): bool
    {
        return $this !== self::Anulado;
    }

    public function canBeAnnulled(): bool
    {
        return $this === self::Activo;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
