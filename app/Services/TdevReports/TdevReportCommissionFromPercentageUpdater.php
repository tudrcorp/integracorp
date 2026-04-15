<?php

declare(strict_types=1);

namespace App\Services\TdevReports;

use App\Models\TdevReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class TdevReportCommissionFromPercentageUpdater
{
    /**
     * (precio_upgrade + monto_pvp_precio_de_venta) × (porcentaje / 100).
     */
    public static function computeMontoComision(float $precioUpgrade, float $montoPvp, ?float $percentage): ?float
    {
        if ($percentage === null) {
            return null;
        }

        $base = $precioUpgrade + $montoPvp;

        return round($base * ($percentage / 100), 4);
    }

    /**
     * Persiste el porcentaje de comisión y recalcula `monto_comision` como
     * (precio_upgrade + monto_pvp_precio_de_venta) × (porcentaje / 100).
     *
     * @return float|null Valor guardado en `porcentaje_comision` (para el estado de la columna).
     */
    public static function apply(TdevReport $record, mixed $percentageInput): ?float
    {
        $record->refresh();

        $oldPct = $record->porcentaje_comision;
        $oldMonto = $record->monto_comision;

        $pct = self::normalizePercentage($percentageInput);
        $base = (float) $record->precio_upgrade + (float) $record->monto_pvp_precio_de_venta;
        $newMonto = self::computeMontoComision(
            (float) $record->precio_upgrade,
            (float) $record->monto_pvp_precio_de_venta,
            $pct,
        );

        $record->porcentaje_comision = $pct;
        $record->monto_comision = $newMonto;
        $record->save();

        $user = Auth::user();

        Log::info('TDEV: porcentaje de comisión actualizado desde tabla Filament', [
            'tdev_report_id' => $record->getKey(),
            'vaucher' => $record->vaucher,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->name,
            'user_email' => $user?->email ?? null,
            'executed_at' => now()->toIso8601String(),
            'porcentaje_comision_anterior' => $oldPct,
            'porcentaje_comision_nuevo' => $pct,
            'monto_comision_anterior' => $oldMonto,
            'monto_comision_nuevo' => $newMonto,
            'base_precio_upgrade_mas_pvp' => $base,
        ]);

        return $pct;
    }

    private static function normalizePercentage(mixed $input): ?float
    {
        if ($input === null || $input === '') {
            return null;
        }

        return round((float) $input, 4);
    }
}
