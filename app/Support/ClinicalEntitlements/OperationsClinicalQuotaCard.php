<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Models\TelemedicinePatient;
use Throwable;

/**
 * Vista compacta del cupo para la ficha de Operaciones. Una sola lectura
 * del resolver (cacheada por request) y HTML mínimo, sin JS ni assets.
 */
final class OperationsClinicalQuotaCard
{
    /**
     * @return array{
     *     tone: string,
     *     summary: ?string,
     *     message: ?string,
     *     otpHint: bool,
     *     rows: list<array{label: string, channel: string, count: string, balance: string, tone: string}>
     * }
     */
    public static function viewData(?TelemedicinePatient $record): array
    {
        if (! $record instanceof TelemedicinePatient) {
            return self::state('muted', 'No hay paciente en esta ficha.');
        }

        try {
            $snapshot = AffiliateClinicalEntitlementResolver::forPatient($record);
        } catch (Throwable $exception) {
            report($exception);

            return self::state('danger', 'No se pudo calcular el cupo. Revise la fecha de vigencia o el mapa clínico del plan.');
        }

        if (! $snapshot->hasPlan) {
            return self::state(
                'muted',
                'Sin plan en esta ficha. Asígnalo en Editar Paciente; la afiliación corporativa no alcanza.',
            );
        }

        if (! $snapshot->isComplete) {
            $missing = $snapshot->missingBenefitLabels;
            $preview = array_slice($missing, 0, 3);
            $extra = count($missing) > 3 ? ' +'.(count($missing) - 3) : '';

            $detail = $preview === []
                ? 'Negocios debe completar Planes → Uso clínico.'
                : 'Falta mapear: '.implode(', ', $preview).$extra.'.';

            return self::state('warning', $detail);
        }

        $rows = [];
        $ok = 0;
        $exhausted = 0;

        foreach ($snapshot->entitlements as $entitlement) {
            if ($entitlement->exhausted) {
                $exhausted++;
            } else {
                $ok++;
            }

            $rows[] = [
                'label' => $entitlement->displayName(),
                'channel' => $entitlement->channel->shortLabel(),
                'count' => $entitlement->operationsCountLabel(),
                'balance' => $entitlement->operationsBalanceLabel(),
                'tone' => $entitlement->operationsTone(),
            ];
        }

        if ($rows === []) {
            return self::state('muted', 'El mapa está completo, pero ningún beneficio aplica en consulta.');
        }

        $parts = [];
        if ($ok > 0) {
            $parts[] = $ok.' con saldo';
        }
        if ($exhausted > 0) {
            $parts[] = $exhausted.' agotado'.($exhausted === 1 ? '' : 's');
        }

        return [
            'tone' => $exhausted > 0 && $ok === 0 ? 'danger' : 'ok',
            'summary' => implode(' · ', $parts),
            'message' => null,
            'otpHint' => $exhausted > 0,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *     tone: string,
     *     summary: ?string,
     *     message: ?string,
     *     otpHint: bool,
     *     rows: list<array{label: string, channel: string, count: string, balance: string, tone: string}>
     * }
     */
    private static function state(string $tone, string $message): array
    {
        return [
            'tone' => $tone,
            'summary' => null,
            'message' => $message,
            'otpHint' => false,
            'rows' => [],
        ];
    }
}
