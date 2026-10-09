<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Business\Pages\UserActivityMonitor;
use App\Support\SecurityAudit;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityMinutes;
use App\Support\UserActivity\UserActivityReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte de Actividad de usuarios en CSV (Excel). Es una agregación de los
 * resúmenes diarios (una fila por persona), así que se arma en el acto.
 */
class UserActivityExportCsvController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless(UserActivityMonitor::canAccess(), 403);

        [$from, $to] = UserActivityReport::range($request->string('desde')->toString() ?: null, $request->string('hasta')->toString() ?: null);

        $report = UserActivityReport::summary($from, $to, [
            'type' => $request->string('tipo')->toString() ?: 'all',
            'search' => mb_substr($request->string('q')->toString(), 0, 100),
            'sort' => $request->string('orden')->toString() ?: 'active',
            'direction' => $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc',
        ]);

        SecurityAudit::log('AUDIT_USER_ACTIVITY_EXPORTED', 'business.user-activity.export', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => count($report['rows']),
        ]);

        $filename = 'actividad-usuarios_'.$from->format('Ymd').'_'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($report, $from, $to): void {
            $out = fopen('php://output', 'w');

            /** BOM: Excel abre el UTF-8 con tildes y eñes correctas. */
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Actividad de usuarios', $from->format('d/m/Y').' al '.$to->format('d/m/Y')], ';');
            fputcsv($out, [], ';');
            fputcsv($out, [
                'Persona', 'Correo', 'Tipo', 'Departamento / agencia', 'Días con actividad',
                'Conectado (min)', 'Activo (min)', 'Abierto sin usar (min)', 'En otra pestaña (min)',
                'Uso real (%)', 'Activo por día (min)', 'Acciones', 'Pantallas', 'Descargas',
                'Llega (promedio)', 'Se va (promedio)', 'Último día',
            ], ';');

            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row['name'], $row['email'], $row['type_label'], $row['type_detail'], $row['days'],
                    $row['online'], $row['active'], $row['idle'], $row['background'],
                    $row['usage'], $row['avg_active'], $row['actions'], $row['pages'], $row['downloads'],
                    UserActivityClock::formatMinute($row['avg_first']), UserActivityClock::formatMinute($row['avg_last']), $row['last_day'],
                ], ';');
            }

            $totals = $report['totals'];
            fputcsv($out, [], ';');
            fputcsv($out, ['TOTAL', '', '', '', '', $totals['online'], $totals['active'], $totals['idle'], $totals['background'], UserActivityMinutes::usagePercent($totals['active'], $totals['online']), '', $totals['actions'], $totals['pages']], ';');

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
