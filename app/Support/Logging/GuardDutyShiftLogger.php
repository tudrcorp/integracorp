<?php

namespace App\Support\Logging;

use App\Models\OperationOnCallUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class GuardDutyShiftLogger
{
    /**
     * Traza de seguridad para turnos de guardia (panel Operaciones).
     *
     * @param  array<string, mixed>  $extra
     */
    public static function record(string $event, OperationOnCallUser $record, array $extra = []): void
    {
        $user = Auth::user();

        Log::info('OPERACIONES: Rol de guardia — '.$event.'.', array_merge([
            'audit' => 'operation_on_call_user',
            'event' => $event,
            'record_id' => $record->id,
            'rrhh_colaborador_id' => $record->rrhh_colaborador_id,
            'date_OnCall' => $record->date_OnCall,
            'status' => $record->status,
            'actor_user_id' => $user?->id,
            'actor_name' => $user?->name,
            'actor_email' => $user?->email,
            'request_ip' => request()?->ip(),
            'request_user_agent' => request()?->userAgent(),
            'request_path' => request()?->path(),
        ], $extra));
    }
}
