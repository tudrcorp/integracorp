<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados de gestión de una cuota en «Cobranza Por Mes».
 *
 * Regla (29/10/2026): «Aviso enviado» y «Aviso remitido» pueden ir juntos; «Aviso en revisión»
 * va solo. Marcar «En revisión» apaga los otros dos y marcar cualquiera de los otros dos apaga
 * «En revisión».
 *
 * Se guarda como texto canónico en `collections.follow_up_status`: los estados marcados en el
 * orden de los casos, separados por coma («AVISO ENVIADO,AVISO REMITIDO»), o null.
 */
enum CollectionFollowUpStatus: string
{
    case AvisoEnviado = 'AVISO ENVIADO';

    case AvisoRemitido = 'AVISO REMITIDO';

    case AvisoEnRevision = 'AVISO EN REVISION';

    public const SEPARATOR = ',';

    /**
     * Estados que ya no se ofrecen pero pueden seguir guardados en cuotas o en la traza
     * (SEGUIMIENTO se retiró el 29/10/2026). Se muestran, se pueden quitar o reemplazar, pero
     * no se pueden volver a marcar.
     *
     * @var array<string, string>
     */
    public const RETIRED = [
        'SEGUIMIENTO' => 'Seguimiento',
    ];

    public function label(): string
    {
        return match ($this) {
            self::AvisoEnviado => 'Aviso enviado',
            self::AvisoRemitido => 'Aviso remitido',
            self::AvisoEnRevision => 'Aviso en revisión',
        };
    }

    /**
     * Color (hex) de la opción marcada; se pinta con estilos propios para no depender del build.
     */
    public function hex(): string
    {
        return match ($this) {
            self::AvisoEnviado => '#7c3aed',
            self::AvisoRemitido => '#0d9488',
            self::AvisoEnRevision => '#d97706',
        };
    }

    /**
     * «En revisión» no convive con ningún otro estado.
     */
    public function isExclusive(): bool
    {
        return $this === self::AvisoEnRevision;
    }

    /**
     * Opciones que se pueden marcar.
     *
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

    public static function isRetired(?string $value): bool
    {
        return $value !== null && array_key_exists($value, self::RETIRED);
    }

    /**
     * Resultado de pulsar una casilla sobre lo que está marcado.
     *
     * @param  list<self>  $current
     * @return list<self>
     */
    public static function toggle(array $current, self $option): array
    {
        if (in_array($option, $current, true)) {
            return array_values(array_filter($current, fn (self $state): bool => $state !== $option));
        }

        if ($option->isExclusive()) {
            return [$option];
        }

        return self::ordered([...array_filter($current, fn (self $state): bool => ! $state->isExclusive()), $option]);
    }

    /**
     * Texto canónico para guardar (null si no hay nada marcado).
     *
     * @param  list<self>  $states
     */
    public static function encode(array $states): ?string
    {
        $states = self::ordered($states);

        return $states === [] ? null : implode(self::SEPARATOR, array_map(fn (self $state): string => $state->value, $states));
    }

    /**
     * Estados vigentes de un valor guardado. Un valor retirado o desconocido no marca ninguno.
     *
     * @return list<self>
     */
    public static function decode(?string $value): array
    {
        $states = [];

        foreach (explode(self::SEPARATOR, (string) $value) as $part) {
            $state = self::tryFrom(trim($part));

            if ($state !== null) {
                $states[] = $state;
            }
        }

        return self::ordered($states);
    }

    /**
     * ¿El valor guardado es una combinación permitida de estados vigentes?
     */
    public static function isValidCombination(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $states = self::decode($value);

        if ($states === [] || self::encode($states) !== $value) {
            return false;
        }

        return count($states) === 1 || array_filter($states, fn (self $state): bool => $state->isExclusive()) === [];
    }

    /**
     * Valores guardados que incluyen ese estado (para filtrar con un `whereIn` exacto).
     *
     * @return list<string>
     */
    public static function storedValuesContaining(self $state): array
    {
        if ($state->isExclusive()) {
            return [$state->value];
        }

        $shared = array_values(array_filter(self::cases(), fn (self $case): bool => ! $case->isExclusive()));
        $values = [];

        foreach (range(1, 2 ** count($shared) - 1) as $mask) {
            $combination = [];

            foreach ($shared as $index => $case) {
                if ($mask & (1 << $index)) {
                    $combination[] = $case;
                }
            }

            if (in_array($state, $combination, true)) {
                $values[] = (string) self::encode($combination);
            }
        }

        return $values;
    }

    /**
     * Etiqueta legible de cualquier valor guardado: combinación vigente o estado retirado.
     */
    public static function labelFor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (self::isRetired($value)) {
            return self::RETIRED[$value].' (ya no se usa)';
        }

        $states = self::decode($value);

        return $states === [] ? $value : implode(' + ', array_map(fn (self $state): string => $state->label(), $states));
    }

    /**
     * @param  array<int, self>  $states
     * @return list<self>
     */
    private static function ordered(array $states): array
    {
        return array_values(array_filter(self::cases(), fn (self $case): bool => in_array($case, $states, true)));
    }
}
