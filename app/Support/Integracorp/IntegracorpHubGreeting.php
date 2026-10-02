<?php

declare(strict_types=1);

namespace App\Support\Integracorp;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class IntegracorpHubGreeting
{
    public static function salutation(?Carbon $at = null): string
    {
        $hour = (int) ($at ?? now())->format('H');

        return match (true) {
            $hour < 12 => 'Buenos días',
            $hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    }

    public static function firstName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            $parts = preg_split('/\s+/u', $name, 2);

            return $parts[0] ?? $name;
        }

        $localPart = Str::before((string) $user->email, '@');

        return $localPart !== '' ? $localPart : 'equipo';
    }

    public static function modulesLead(int $moduleCount): string
    {
        if ($moduleCount <= 0) {
            return 'Cuando tengas acceso a un módulo, aparecerá aquí.';
        }

        if ($moduleCount === 1) {
            return 'Tu espacio de trabajo está listo.';
        }

        return 'Elige el espacio de trabajo que necesitas hoy.';
    }

    public static function modulesCountLabel(int $moduleCount): string
    {
        if ($moduleCount <= 0) {
            return 'Sin módulos habilitados';
        }

        if ($moduleCount === 1) {
            return '1 módulo habilitado';
        }

        return sprintf('%d módulos habilitados', $moduleCount);
    }
}
