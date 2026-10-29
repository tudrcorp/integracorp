<?php

declare(strict_types=1);

namespace App\Support\Operations\Providers;

use App\Enums\ProviderNetworkType;
use Illuminate\Support\Str;

/**
 * Cómo se lee cada proveedor para el dashboard: estatus del convenio, tipo de convenio
 * (general / preferencial) y carta de aceptación, con los mismos nombres para los tres tipos.
 *
 * Los estatus salen del catálogo de Operaciones (`supplier_estatus_sistemas`). Lo que no está en el
 * catálogo se muestra tal cual en «Otro» en vez de adivinarlo. Los naturales guardan el estatus en
 * dos columnas: la del formulario actual (`status_sistema`) manda; si está vacía se usa la cargada
 * originalmente (`status_sistena`).
 */
final class ProviderNetworkCatalog
{
    public const STATUS_NONE = 'SIN ESTATUS';

    public const STATUS_OTHER = 'OTRO';

    public const AGREEMENT_NONE = 'SIN TIPO';

    /**
     * Estatus en el orden en que se muestran, con su etiqueta y color.
     *
     * @var array<string, array{label: string, color: string, active: bool}>
     */
    public const STATUSES = [
        'ACTIVO AFILIADO' => ['label' => 'Activo afiliado', 'color' => 'var(--ops-good)', 'active' => true],
        'AFILIADO' => ['label' => 'Afiliado', 'color' => 'var(--ops-good-ink)', 'active' => true],
        'ACTIVO' => ['label' => 'Activo', 'color' => '#15803d', 'active' => true],
        'ACTIVO EN PROCESO' => ['label' => 'Activo en proceso', 'color' => 'var(--ops-series1)', 'active' => false],
        'EN PROCESO' => ['label' => 'En proceso', 'color' => 'var(--ops-warning)', 'active' => false],
        'PENDIENTE' => ['label' => 'Pendiente', 'color' => '#d97706', 'active' => false],
        'SIN RESPUESTA DE AFILIACION' => ['label' => 'Sin respuesta de afiliación', 'color' => 'var(--ops-muted)', 'active' => false],
        'CONVENIO SUSPENDIDO POR TDEC' => ['label' => 'Suspendido por TDEC', 'color' => 'var(--ops-critical)', 'active' => false],
        'CONVENIO SUSPENDIDO POR EL PROVEEDOR' => ['label' => 'Suspendido por el proveedor', 'color' => 'var(--ops-bad-ink)', 'active' => false],
        'NO DESEA CONVENIO' => ['label' => 'No desea convenio', 'color' => '#7f1d1d', 'active' => false],
        'INACTIVO' => ['label' => 'Inactivo', 'color' => '#57534e', 'active' => false],
        self::STATUS_NONE => ['label' => 'Sin estatus', 'color' => 'var(--ops-track)', 'active' => false],
        self::STATUS_OTHER => ['label' => 'Otro (fuera del catálogo)', 'color' => '#a8a29e', 'active' => false],
    ];

    /**
     * Tipos de convenio. Los dos preferenciales se muestran juntos y por separado.
     *
     * @var array<string, array{label: string, color: string, preferential: bool}>
     */
    public const AGREEMENTS = [
        'GENERAL' => ['label' => 'General', 'color' => 'var(--ops-series1)', 'preferential' => false],
        'PREFERENCIAL BAJO COSTO' => ['label' => 'Preferencial bajo costo', 'color' => 'var(--ops-good)', 'preferential' => true],
        'PREFERENCIAL GEOGRAFICO' => ['label' => 'Preferencial geográfico', 'color' => 'var(--ops-violet)', 'preferential' => true],
        'CONTADO' => ['label' => 'Contado', 'color' => 'var(--ops-warning)', 'preferential' => false],
        'NO CONVENIDO' => ['label' => 'No convenido', 'color' => 'var(--ops-critical)', 'preferential' => false],
        self::AGREEMENT_NONE => ['label' => 'Sin tipo de convenio', 'color' => 'var(--ops-track)', 'preferential' => false],
    ];

    /**
     * @var array<string, string>
     */
    private const STATUS_ALIASES = [
        'AFILILIADO' => 'AFILIADO',
        'SIN RESPUESTA' => 'SIN RESPUESTA DE AFILIACION',
        'SUSPENDIDO' => 'CONVENIO SUSPENDIDO POR TDEC',
    ];

    public static function normalize(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Str::upper(Str::ascii((string) $value))));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{key: string, raw: string}
     */
    public static function status(ProviderNetworkType $type, array $row): array
    {
        $candidates = match ($type) {
            ProviderNetworkType::Juridico => [$row['status_sistema'] ?? null],
            ProviderNetworkType::Natural => [$row['status_sistema'] ?? null, $row['status_sistena'] ?? null],
            ProviderNetworkType::Aliado => [$row['status_agreement'] ?? null],
        };

        foreach ($candidates as $candidate) {
            $value = self::normalize($candidate);

            if ($value === '') {
                continue;
            }

            $value = self::STATUS_ALIASES[$value] ?? $value;

            return ['key' => array_key_exists($value, self::STATUSES) ? $value : self::STATUS_OTHER, 'raw' => $value];
        }

        return ['key' => self::STATUS_NONE, 'raw' => ''];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function agreement(ProviderNetworkType $type, array $row): string
    {
        $value = self::normalize(match ($type) {
            ProviderNetworkType::Aliado => $row['type_agreement'] ?? null,
            default => $row['status_convenio'] ?? null,
        });

        return array_key_exists($value, self::AGREEMENTS) && $value !== self::AGREEMENT_NONE ? $value : self::AGREEMENT_NONE;
    }

    public static function isActiveStatus(string $key): bool
    {
        return self::STATUSES[$key]['active'] ?? false;
    }

    public static function statusLabel(string $key): string
    {
        return self::STATUSES[$key]['label'] ?? $key;
    }

    public static function agreementLabel(string $key): string
    {
        return self::AGREEMENTS[$key]['label'] ?? $key;
    }

    /**
     * Carta de aceptación cargada. Null: los aliados no manejan carta.
     *
     * @param  array<string, mixed>  $row
     */
    public static function hasLetter(ProviderNetworkType $type, array $row): ?bool
    {
        if ($type === ProviderNetworkType::Aliado) {
            return null;
        }

        return trim((string) ($row['carta_acceptance'] ?? '')) !== '';
    }

    /**
     * «SERVICIOS MEDICOS» → «Servicios medicos»: agrupa variantes de mayúsculas y espacios.
     */
    public static function label(mixed $value, string $fallback = 'Sin dato'): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $clean === '' ? $fallback : Str::ucfirst(Str::lower($clean));
    }
}
