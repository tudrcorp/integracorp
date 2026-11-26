<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Support\CsvExportStream;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Exporta el periodo de Supervisión WhatsApp para informes y propuestas: CSV (Excel) con todas
 * las cifras y PDF con el resumen. Son agregados ya calculados y cacheados: livianos, sin cola.
 */
final class CrmSupervisionExport
{
    public static function filename(string $panel, string $range, string $format): string
    {
        return 'supervision-whatsapp-'.$panel.'-'.$range.'-'.Carbon::now()->timezone('America/Caracas')->format('Ymd-Hi').'.'.$format;
    }

    /**
     * @param  array{range_label: string, data: array<string, mixed>, bot: array<string, mixed>|null, insights: list<array<string, string>>}  $report
     */
    public static function csv(array $report): string
    {
        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            return '';
        }

        $row = static fn (array $values): int|false => fputcsv($handle, array_map(static fn (mixed $value): string => self::cell($value), $values));
        $data = $report['data'];
        $kpis = $data['kpis'];
        $trends = $data['trends'] ?? [];
        $bot = $report['bot'];

        $row(['Supervisión WhatsApp', $report['range_label'], 'Generado '.Carbon::now()->timezone('America/Caracas')->format('d/m/Y H:i')]);
        $row([]);

        $row(['BANDEJA', 'Valor']);

        foreach (self::inboxKpis($data) as [$label, $value]) {
            $row([$label, $value]);
        }

        $row([]);
        $row(['BOT SOL', 'Valor']);

        if ($bot === null) {
            $row(['Sin datos del bot (n8n no respondió)', '']);
        } else {
            foreach (self::botKpis($bot) as [$label, $value]) {
                $row([$label, $value]);
            }
        }

        $row([]);
        $row(['HALLAZGOS', 'Detalle']);

        foreach ($report['insights'] as $insight) {
            $row([$insight['title'], $insight['text']]);
        }

        $row([]);
        $row(['ANALISTAS', 'Estado', 'En atención', 'Sin responder', 'Tomados', '1.ª respuesta', 'Finalizadas', 'Atención', 'Al bot', 'Cerrados', 'Ventas']);

        foreach ($data['analysts'] as $analyst) {
            $row([$analyst['name'], $analyst['state_label'], $analyst['active'], $analyst['waiting'], $analyst['attended'],
                CrmInboxDashboard::duration($analyst['reply_median']), $analyst['finished'], CrmInboxDashboard::duration($analyst['handle_median']),
                $analyst['released'], $analyst['closed'], $analyst['won']]);
        }

        $row([]);
        $row(['CASOS POR DÍA', 'Entraron', 'Tomados', 'Finalizados', 'Clientes del bot', 'Derivaciones del bot']);
        $botDays = [];

        foreach (($bot['days'] ?? []) as $day) {
            $botDays[$day['day']] = $day;
        }

        foreach (($trends['days'] ?? []) as $day) {
            $row([Carbon::parse($day['day'])->format('d/m/Y'), $day['incoming'], $day['taken'], $day['finished'],
                $botDays[$day['day']]['clients'] ?? '', $botDays[$day['day']]['handoffs'] ?? '']);
        }

        $row([]);
        $row(['ÁREA', 'Casos', 'Espera mediana']);

        foreach (($trends['areas'] ?? []) as $area) {
            $row([$area['label'], $area['incoming'], CrmInboxDashboard::duration($area['wait_median'])]);
        }

        $row([]);
        $row(['HORA', 'Casos', 'Espera mediana', 'Mensajes al bot']);

        foreach (($trends['hours'] ?? []) as $hour) {
            $row([sprintf('%02d:00', $hour['hour']), $hour['incoming'], CrmInboxDashboard::duration($hour['wait_median']), $bot['hours'][$hour['hour']] ?? '']);
        }

        $row([]);
        $row(['CÓMO TERMINARON', 'Casos']);

        foreach ($data['outcomes'] as $outcome) {
            $row([$outcome['label'], $outcome['total']]);
        }

        if ($bot !== null && $bot['reasons'] !== []) {
            $row([]);
            $row(['MOTIVOS RECIENTES DE DERIVACIÓN', 'Área', 'Fecha']);

            foreach ($bot['reasons'] as $reason) {
                $row([$reason['motivo'], CrmInboxAreas::label($reason['area'] !== '' ? $reason['area'] : null), self::time($reason['at'])]);
            }
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return CsvExportStream::UTF8_BOM.$csv;
    }

    /**
     * @param  array{range_label: string, data: array<string, mixed>, bot: array<string, mixed>|null, insights: list<array<string, string>>}  $report
     */
    public static function pdf(array $report): string
    {
        $html = view('filament.crm.exports.supervision-pdf', [
            'report' => $report,
            'inboxKpis' => self::inboxKpis($report['data']),
            'botKpis' => $report['bot'] !== null ? self::botKpis($report['bot']) : [],
            'generatedAt' => Carbon::now()->timezone('America/Caracas')->format('d/m/Y H:i'),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{0: string, 1: string|int}>
     */
    public static function inboxKpis(array $data): array
    {
        $k = $data['kpis'];
        $t = $data['trends'] ?? [];

        return [
            ['Casos que entraron', $k['incoming']],
            ['Casos tomados', $k['attended']],
            ['Tomados a tiempo (meta '.($t['sla_minutes'] ?? 15).' min)', ($t['sla'] ?? null) !== null ? $t['sla'].' %' : '—'],
            ['Espera para ser tomado (mediana)', CrmInboxDashboard::duration($t['wait_median'] ?? null)],
            ['Primera respuesta (mediana, desde que entró)', CrmInboxDashboard::duration($k['first_reply_median'])],
            ['Esperaron demasiado o no se tomaron', $t['abandoned'] ?? 0],
            ['Conversaciones finalizadas por analistas', $k['finished']],
            ['Tiempo de atención (mediana)', CrmInboxDashboard::duration($k['handle_median'])],
            ['Devueltas al bot', $k['released']],
            ['Propuestas enviadas por analistas', $k['quotes']],
            ['Ventas sobre casos cerrados', $k['conversion'] !== null ? $k['conversion'].' % ('.$k['won'].' de '.$k['closed'].')' : '—'],
            ['Derivaciones repetidas unidas al caso abierto', $t['merged'] ?? 0],
        ];
    }

    /**
     * @param  array<string, mixed>  $bot
     * @return list<array{0: string, 1: string|int}>
     */
    public static function botKpis(array $bot): array
    {
        $p = $bot['period'];
        $pct = static fn (int $part, int $total): string => $total > 0 ? (int) round($part / $total * 100).' %' : '—';

        return [
            ['Clientes atendidos', $p['clientes']],
            ['Clientes nuevos', $p['clientes_nuevos']],
            ['Mensajes recibidos', $p['mensajes_in']],
            ['Mensajes enviados', $p['mensajes_out']],
            ['Notas de voz recibidas', $p['audios_in']],
            ['Resueltos sin una persona', $p['resueltos_sin_humano'].' ('.$pct($p['resueltos_sin_humano'], $p['clientes']).')'],
            ['Clientes derivados a analistas', $p['clientes_derivados']],
            ['Cotizaciones', $p['cotizaciones']],
            ['Propuestas en PDF', $p['propuestas_pdf']],
            ['Seguimientos enviados', $p['seguimientos_enviados']],
            ['Respondieron al seguimiento', $p['seguimientos_respondidos'].' ('.$pct($p['seguimientos_respondidos'], $p['seguimientos_enviados']).')'],
            ['Errores del asistente', $p['errores'] + $p['errores_escalados']],
        ];
    }

    private static function time(?int $timestamp): string
    {
        return $timestamp !== null ? Carbon::createFromTimestamp($timestamp)->timezone('America/Caracas')->format('d/m/Y H:i') : '';
    }

    /**
     * Evita que Excel interprete un texto como fórmula (=, +, -, @).
     */
    private static function cell(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@'], true) && ! is_numeric($text) ? "'".$text : $text;
    }
}
