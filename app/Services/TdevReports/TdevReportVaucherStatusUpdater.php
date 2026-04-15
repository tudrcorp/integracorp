<?php

declare(strict_types=1);

namespace App\Services\TdevReports;

use App\Enums\StatusComision;
use App\Enums\StatusPago;
use App\Enums\StatusVaucher;
use App\Models\TdevReport;
use App\Support\TdevReportProcessObservationAppender;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class TdevReportVaucherStatusUpdater
{
    /**
     * Atributos a persistir según el estatus de voucher elegido.
     *
     * @return array<string, string>
     */
    public static function attributesForSelection(StatusVaucher $nuevo): array
    {
        $out = [
            'estatus_vaucher' => $nuevo->value,
        ];

        if ($nuevo === StatusVaucher::Anulado) {
            $out['estatus_pago'] = StatusPago::Anulado->value;
            $out['estatus_comision'] = StatusComision::Anulado->value;
        }

        return $out;
    }

    public static function apply(TdevReport $record, StatusVaucher $nuevo, ?string $observacionAnulacionHtml = null): void
    {
        $record->refresh();

        $oldVaucher = $record->estatus_vaucher;
        $oldPago = $record->estatus_pago;
        $oldComision = $record->estatus_comision;

        $attrs = self::attributesForSelection($nuevo);
        $record->fill($attrs);
        $record->save();

        if ($nuevo === StatusVaucher::Anulado) {
            $html = $observacionAnulacionHtml !== null ? trim($observacionAnulacionHtml) : '';
            if ($html !== '') {
                $user = Auth::user();
                if ($user !== null) {
                    TdevReportProcessObservationAppender::append($record, $html, $user->name);
                }
            }
        }

        $user = Auth::user();

        $pagoNuevo = $record->estatus_pago;
        $comisionNuevo = $record->estatus_comision;

        Log::info('TDEV: estatus del voucher actualizado desde tabla Filament', [
            'tdev_report_id' => $record->getKey(),
            'vaucher' => $record->vaucher,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->name,
            'user_email' => $user?->email ?? null,
            'executed_at' => now()->toIso8601String(),
            'estatus_vaucher_anterior' => $oldVaucher instanceof StatusVaucher ? $oldVaucher->value : $oldVaucher,
            'estatus_vaucher_nuevo' => $nuevo->value,
            'cascada_anulado_pago_comision' => $nuevo === StatusVaucher::Anulado,
            'estatus_pago_anterior' => $oldPago instanceof StatusPago ? $oldPago->value : $oldPago,
            'estatus_pago_nuevo' => $pagoNuevo instanceof StatusPago ? $pagoNuevo->value : $pagoNuevo,
            'estatus_comision_anterior' => $oldComision instanceof StatusComision ? $oldComision->value : $oldComision,
            'estatus_comision_nuevo' => $comisionNuevo instanceof StatusComision ? $comisionNuevo->value : $comisionNuevo,
        ]);
    }
}
