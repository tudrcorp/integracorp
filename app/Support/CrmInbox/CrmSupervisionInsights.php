<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

/**
 * Hallazgos del periodo en lenguaje claro, para decidir y proponer mejoras: qué resuelve el bot
 * solo, dónde se pierde tiempo, en qué horas faltan analistas y qué conviene enseñarle al bot.
 * Reglas fijas sobre los números del tablero; no inventa nada que no esté en los datos.
 */
final class CrmSupervisionInsights
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const BAD = 'bad';

    public const INFO = 'info';

    /**
     * @param  array<string, mixed>  $data  tablero de la bandeja
     * @param  array<string, mixed>|null  $bot  métricas del bot (null si no hay datos)
     * @return list<array{tone: string, title: string, text: string}>
     */
    public static function build(array $data, ?array $bot): array
    {
        $insights = [];
        $kpis = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        $trends = is_array($data['trends'] ?? null) ? $data['trends'] : [];
        $period = is_array($bot['period'] ?? null) ? $bot['period'] : null;

        if ($period !== null && $period['clientes'] > 0) {
            $pct = (int) round($period['resueltos_sin_humano'] / $period['clientes'] * 100);
            $insights[] = [
                'tone' => $pct >= 70 ? self::OK : ($pct >= 50 ? self::INFO : self::WARN),
                'title' => 'El bot resolvió solo al '.$pct.' % de los clientes',
                'text' => 'Atendió '.$period['clientes'].' clientes y '.$period['resueltos_sin_humano'].' no necesitaron a una persona. '
                    .($pct < 50 ? 'Es bajo: revisa en «Motivos recientes de derivación» qué preguntas se repiten y enséñaselas al bot.' : 'Las derivaciones restantes son las que requieren criterio humano.'),
            ];
        }

        $peak = $trends['peak_wait_hours'][0] ?? null;
        $slaSeconds = (int) ($trends['sla_minutes'] ?? 15) * 60;

        if (is_array($peak) && $peak['wait_median'] > $slaSeconds) {
            $insights[] = [
                'tone' => self::WARN,
                'title' => 'Entre las '.sprintf('%02d:00', $peak['hour']).' y las '.sprintf('%02d:00', ($peak['hour'] + 1) % 24).' faltan manos',
                'text' => 'En esa hora entraron '.$peak['incoming'].' casos y esperaron '.CrmInboxDashboard::duration($peak['wait_median']).' (mediana) para ser tomados, más que la meta de '.intdiv($slaSeconds, 60).' min. Conviene reforzar ese turno.',
            ];
        }

        if (($trends['sla'] ?? null) !== null) {
            $sla = (int) $trends['sla'];
            $insights[] = [
                'tone' => $sla >= 90 ? self::OK : ($sla >= 70 ? self::WARN : self::BAD),
                'title' => $sla.' % de los casos se tomó a tiempo',
                'text' => 'Meta: tomar cada caso en menos de '.$trends['sla_minutes'].' min. Espera mediana del periodo: '.CrmInboxDashboard::duration($trends['wait_median'] ?? null).'.',
            ];
        }

        if ((int) ($trends['abandoned'] ?? 0) > 0) {
            $insights[] = [
                'tone' => self::BAD,
                'title' => $trends['abandoned'].' caso(s) esperaron demasiado',
                'text' => 'Esperaron más de '.$trends['abandon_minutes'].' min para ser tomados o nunca se tomaron. Son clientes en riesgo de irse: revisa la cobertura por área y por hora.',
            ];
        }

        $areas = is_array($trends['areas'] ?? null) ? $trends['areas'] : [];
        $totalIncoming = array_sum(array_column($areas, 'incoming'));

        if ($areas !== [] && $totalIncoming >= 3) {
            $top = $areas[0];
            $insights[] = [
                'tone' => self::INFO,
                'title' => $top['label'].' recibe la mayoría de los casos',
                'text' => $top['incoming'].' de '.$totalIncoming.' casos ('.(int) round($top['incoming'] / $totalIncoming * 100).' %), con espera mediana de '.CrmInboxDashboard::duration($top['wait_median']).'.',
            ];
        }

        if ($period !== null && $period['errores'] + $period['errores_escalados'] > 0) {
            $insights[] = [
                'tone' => $period['errores_escalados'] > 0 ? self::BAD : self::WARN,
                'title' => 'El asistente falló '.($period['errores'] + $period['errores_escalados']).' vez/veces',
                'text' => ($period['errores_escalados'] > 0 ? $period['errores_escalados'].' terminaron en derivación automática porque el cliente se quedó sin respuesta. ' : '')
                    .'Revisa «Errores recientes del bot»: suelen ser cortes del modelo o de la red.',
            ];
        }

        if ($period !== null && $period['seguimientos_enviados'] > 0) {
            $pct = (int) round($period['seguimientos_respondidos'] / $period['seguimientos_enviados'] * 100);
            $insights[] = [
                'tone' => $pct >= 25 ? self::OK : self::INFO,
                'title' => $pct.' % responde a los seguimientos',
                'text' => 'Se enviaron '.$period['seguimientos_enviados'].' seguimientos automáticos y '.$period['seguimientos_respondidos'].' clientes respondieron en menos de 24 h.',
            ];
        }

        if ($period !== null && $period['clientes_cotizados'] > 0) {
            $insights[] = [
                'tone' => self::INFO,
                'title' => $period['clientes_cotizados'].' cliente(s) recibieron cotización del bot',
                'text' => $period['propuestas_pdf'].' propuesta(s) en PDF enviadas'.(($kpis['won'] ?? 0) > 0 ? ' · '.$kpis['won'].' venta(s) cerradas por analistas en el periodo.' : '.'),
            ];
        }

        $merged = (int) ($trends['merged'] ?? 0) + (int) ($period['derivaciones_repetidas'] ?? 0);

        if ($merged > 0) {
            $insights[] = [
                'tone' => self::INFO,
                'title' => $merged.' derivación(es) repetida(s) evitadas',
                'text' => 'El cliente ya tenía un caso abierto: lo nuevo se agregó a ese caso en lugar de duplicarlo.',
            ];
        }

        if ($bot === null) {
            $insights[] = [
                'tone' => self::WARN,
                'title' => 'Sin datos del bot',
                'text' => 'No se pudieron leer las métricas del bot (n8n). Los hallazgos de la bandeja siguen siendo válidos.',
            ];
        }

        if ($insights === []) {
            $insights[] = ['tone' => self::INFO, 'title' => 'Aún no hay suficiente actividad', 'text' => 'Cuando entren conversaciones y casos en este periodo, aquí aparecerán los hallazgos.'];
        }

        $order = [self::BAD => 0, self::WARN => 1, self::INFO => 2, self::OK => 3];
        usort($insights, fn (array $a, array $b): int => $order[$a['tone']] <=> $order[$b['tone']]);

        return $insights;
    }
}
