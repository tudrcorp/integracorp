<?php

declare(strict_types=1);

namespace App\Services\TdevReports;

use App\Enums\StatusComision;
use App\Models\TdevReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class TdevReportComisionStatusUpdater
{
    public static function apply(TdevReport $record, StatusComision $nuevo): void
    {
        $record->refresh();

        $oldComision = $record->estatus_comision;

        $record->estatus_comision = $nuevo;
        $record->save();

        $user = Auth::user();

        Log::info('TDEV: estatus de comisión actualizado desde tabla Filament', [
            'tdev_report_id' => $record->getKey(),
            'vaucher' => $record->vaucher,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->name,
            'user_email' => $user?->email ?? null,
            'executed_at' => now()->toIso8601String(),
            'estatus_comision_anterior' => $oldComision instanceof StatusComision ? $oldComision->value : $oldComision,
            'estatus_comision_nuevo' => $nuevo->value,
        ]);
    }
}
