<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

/**
 * Forma común de la vista previa de «Agregar upgrade» (individual y
 * corporativo), la que pinta `filament.business.upgrades.assignment-preview`.
 *
 * - `rows`: por afiliado, si recibe el upgrade (precio por su edad) o por qué no,
 *   y su tarifa anual y monto por período antes y después.
 * - `owner`: total de la afiliación antes y después.
 * - `collections`: avisos POR PAGAR que se ajustarían.
 * - `blockers`: lo que impide guardar; el botón no debe confirmar con bloqueos.
 */
final class UpgradeAssignmentPreview
{
    /**
     * @param  list<string>  $blockers
     * @return array<string, mixed>
     */
    public static function empty(?string $error = null, array $blockers = []): array
    {
        return [
            'error' => $error,
            'blockers' => $blockers,
            'upgrade' => null,
            'rows' => [],
            'owner' => null,
            'collections' => [],
            'annual_delta' => 0.0,
        ];
    }

    /**
     * Afiliados que recibirían el upgrade: son los que necesitan voucher.
     *
     * @param  array<string, mixed>  $preview
     * @return list<int>
     */
    public static function receivingIds(array $preview): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['id'],
            array_filter((array) ($preview['rows'] ?? []), static fn (array $row): bool => (bool) $row['receives']),
        ));
    }

    /**
     * Por qué no se puede confirmar todavía, o null si se puede.
     *
     * @param  array<string, mixed>  $preview
     */
    public static function blockingMessage(array $preview): ?string
    {
        if (($preview['error'] ?? null) !== null) {
            return (string) $preview['error'];
        }

        if (($preview['blockers'] ?? []) !== []) {
            return implode(' ', (array) $preview['blockers']);
        }

        if (self::receivingIds($preview) === []) {
            return 'Ningún afiliado seleccionado puede recibir este upgrade. Revise el detalle de cada uno.';
        }

        return null;
    }
}
