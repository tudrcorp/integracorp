<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use Illuminate\Validation\ValidationException;

/**
 * Cómo se cobran los upgrades de una afiliación. Se elige una vez por afiliación:
 *
 * - **Anual** (por defecto): completos en el primer pago del año (`billing_mode = PRIMER_PAGO`).
 * - **La misma frecuencia de la afiliación**: repartidos en cada cuota (`billing_mode = CUOTAS`).
 *
 * Cualquier otra frecuencia se rechaza: el upgrade no puede cobrarse con un calendario distinto
 * al de la afiliación. Una afiliación sin elección (NULL, anteriores al 05/11/2026) es Anual.
 */
final class UpgradeBillingChoice
{
    public const ANNUAL = CorporatePaymentFrequency::ANUAL;

    public const DEFAULT = self::ANNUAL;

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            CorporatePaymentFrequency::ANUAL => 'Anual (completo en el primer pago)',
            CorporatePaymentFrequency::SEMESTRAL => 'Semestral (repartido en cada cuota)',
            CorporatePaymentFrequency::TRIMESTRAL => 'Trimestral (repartido en cada cuota)',
            CorporatePaymentFrequency::MENSUAL => 'Mensual (repartido en cada cuota)',
        ];
    }

    public static function normalize(mixed $choice): ?string
    {
        return is_string($choice) ? CorporatePaymentFrequency::normalize($choice) : null;
    }

    /**
     * Varias formas de pago (generador): válidas, sin repetir y en el orden del catálogo.
     *
     * @return list<string>
     */
    public static function normalizeList(mixed $choices): array
    {
        if (is_string($choices)) {
            $decoded = json_decode($choices, true);
            $choices = is_array($decoded) ? $decoded : [$choices];
        }

        $wanted = array_filter(array_map(fn (mixed $choice): ?string => self::normalize($choice), is_array($choices) ? $choices : []));

        return array_values(array_filter(array_keys(self::options()), fn (string $option): bool => in_array($option, $wanted, true)));
    }

    /**
     * Sugerencia al afiliar: la frecuencia de la afiliación si la cotización la ofreció
     * repartida; si no, Anual.
     *
     * @param  list<string>  $offered
     */
    public static function suggestFor(array $offered, mixed $affiliationFrequency): string
    {
        $frequency = CorporatePaymentFrequency::normalize((string) $affiliationFrequency);

        return $frequency !== null && in_array($frequency, self::normalizeList($offered), true) ? $frequency : self::DEFAULT;
    }

    /**
     * Etiqueta corta (botones del generador).
     */
    public static function shortLabel(string $choice): string
    {
        return CorporatePaymentFrequency::label($choice);
    }

    /**
     * Mensaje de error si la elección no es válida para la frecuencia de la afiliación, o null.
     */
    public static function error(mixed $choice, mixed $affiliationFrequency): ?string
    {
        $choice = self::normalize($choice);

        if ($choice === null) {
            return 'Indique cómo se cobrará el upgrade.';
        }

        $affiliation = CorporatePaymentFrequency::normalize((string) $affiliationFrequency);

        if ($choice === self::ANNUAL || $choice === $affiliation) {
            return null;
        }

        return 'La frecuencia de pago del upgrade ('.CorporatePaymentFrequency::label($choice).') debe ser igual a la frecuencia de pago de la afiliación ('
            .CorporatePaymentFrequency::label($affiliation).'). Elija '.CorporatePaymentFrequency::label($affiliation).' o Anual.';
    }

    /**
     * @throws ValidationException
     */
    public static function assertValid(mixed $choice, mixed $affiliationFrequency, string $field = 'upgrade_billing_frequency'): string
    {
        $error = self::error($choice, $affiliationFrequency);

        if ($error !== null) {
            throw ValidationException::withMessages([$field => $error]);
        }

        return (string) self::normalize($choice);
    }

    /**
     * `billing_mode` de las asignaciones según la elección. En una afiliación anual ambos
     * caminos son lo mismo: se usa el cobro único de siempre.
     */
    public static function modeFor(mixed $choice, mixed $affiliationFrequency): string
    {
        $choice = self::normalize($choice) ?? self::ANNUAL;
        $affiliation = CorporatePaymentFrequency::normalize((string) $affiliationFrequency) ?? self::ANNUAL;

        return $choice !== self::ANNUAL && $choice === $affiliation && $affiliation !== self::ANNUAL
            ? UpgradeFirstPaymentBilling::MODE_INSTALLMENTS
            : UpgradeFirstPaymentBilling::MODE;
    }

    /**
     * Elección vigente de una afiliación. Si quedó inválida (cambió la frecuencia de la
     * afiliación y la elección no la siguió), vale Anual.
     */
    public static function of(Affiliation|AffiliationCorporate $affiliation): string
    {
        $choice = self::normalize($affiliation->getAttribute('upgrade_billing_frequency')) ?? self::ANNUAL;

        return self::error($choice, $affiliation->payment_frequency) === null ? $choice : self::ANNUAL;
    }

    public static function modeOf(Affiliation|AffiliationCorporate $affiliation): string
    {
        return self::modeFor(self::of($affiliation), $affiliation->payment_frequency);
    }

    public static function label(mixed $choice): string
    {
        $choice = self::normalize($choice) ?? self::ANNUAL;

        return self::options()[$choice];
    }

    /**
     * Tras cambiar la frecuencia de la afiliación: si los upgrades iban repartidos, la elección
     * sigue a la nueva frecuencia (siguen repartidos en cada cuota). Si eran anuales, no cambia.
     */
    public static function followFrequency(Affiliation|AffiliationCorporate $affiliation, ?string $previousFrequency): void
    {
        $choice = self::normalize($affiliation->getAttribute('upgrade_billing_frequency'));

        if ($choice === null || $choice === self::ANNUAL || $choice !== CorporatePaymentFrequency::normalize((string) $previousFrequency)) {
            return;
        }

        $affiliation->setAttribute('upgrade_billing_frequency', CorporatePaymentFrequency::normalize((string) $affiliation->payment_frequency) ?? self::ANNUAL);
    }
}
