<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Plan;

class IndividualQuotePdfLayout
{
    public const Inicial = 'inicial';

    public const Ideal = 'ideal';

    public const Especial = 'especial';

    /**
     * Plan armado con el asistente que cobra por cobertura. La página del plan
     * se compone con la estructura real en vez de una imagen horneada.
     */
    public const Estructura = 'estructura';

    /**
     * Paquete de beneficios: misma página compuesta, pero con una sola columna
     * y sin desglose por cobertura, porque sus tarifas no tienen `coverage_id`.
     */
    public const EstructuraPaquete = 'estructura-paquete';

    /**
     * Resuelve la plantilla PDF de un plan.
     *
     * Los planes 1, 2 y 3 (Inicial, Ideal y Especial) conservan su plantilla
     * histórica con la imagen de página completa: su portada trae horneados el
     * título, la tabla de beneficios y las condiciones, y son los planes que
     * más se cotizan. El `match` explícito es lo que garantiza que nada de lo
     * que sigue los alcance.
     *
     * Cualquier otro plan se compone desde su estructura. Antes caían en
     * `Ideal`, así que un plan nuevo salía con la imagen del Plan Ideal —
     * titulada «Plan Accidentes» y con columnas IDEAL US$ 1K…10K— aunque sus
     * beneficios y coberturas fueran otros.
     */
    public static function resolve(int $planId): string
    {
        return match ($planId) {
            1 => self::Inicial,
            3 => self::Especial,
            2 => self::Ideal,
            default => self::resolveFromStructure($planId),
        };
    }

    /**
     * Indica si el detalle debe unirse con `coverages` para desglosar por
     * cobertura. Un paquete de beneficios guarda sus tarifas con `coverage_id`
     * nulo: unirlo dejaría la consulta sin filas.
     */
    public static function usesCoverageBreakdown(string $layout): bool
    {
        return in_array($layout, [self::Ideal, self::Especial, self::Estructura], true);
    }

    /**
     * Indica si la página del plan se compone desde la estructura del plan en
     * vez de apoyarse en una imagen de página completa.
     */
    public static function usesPlanStructure(string $layout): bool
    {
        return in_array($layout, [self::Estructura, self::EstructuraPaquete], true);
    }

    /**
     * Layouts históricos, que no deben verse afectados por el armado dinámico.
     *
     * @return list<string>
     */
    public static function legacyLayouts(): array
    {
        return [self::Inicial, self::Ideal, self::Especial];
    }

    private static function resolveFromStructure(int $planId): string
    {
        $plan = Plan::query()->find($planId);

        if ($plan === null) {
            return self::Ideal;
        }

        return $plan->isBenefitPackage()
            ? self::EstructuraPaquete
            : self::Estructura;
    }
}
