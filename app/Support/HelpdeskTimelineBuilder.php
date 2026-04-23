<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;
use Carbon\CarbonImmutable;

final class HelpdeskTimelineBuilder
{
    /**
     * @return list<array{
     *   type:string,
     *   title:string,
     *   summary:string,
     *   actor:string,
     *   display_name:string,
     *   initials:string,
     *   avatar_url:?string,
     *   at:CarbonImmutable,
     *   datetime_full:string,
     *   relative:string,
     *   side:string,
     *   body_html:string
     * }>
     */
    public static function fromTicket(HelpDesk $record): array
    {
        $timezone = (string) config('app.timezone');
        $createdAt = CarbonImmutable::parse($record->created_at)->timezone($timezone);
        $creatorName = (string) ($record->created_by ?: 'Sistema');
        $creator = HelpdeskTimelineActorResolver::resolve($creatorName);
        $datetimeCreated = self::formatDatetimeFull($createdAt, $timezone);

        $events = [[
            'type' => 'created',
            'title' => 'Alta del ticket en el sistema',
            'summary' => 'Aquí comienza el expediente: en la fecha y hora indicadas se registró un nuevo ticket de soporte. '
                .$creator['display_name'].' fue quien abrió el caso. A partir de este momento el equipo asignado puede ver la descripción, '
                .'cambiar el estado del flujo, ajustar la prioridad (si es el creador) y dejar notas. Referencia absoluta: '.$datetimeCreated
                .'. Referencia relativa: '.$createdAt->diffForHumans().'.',
            'actor' => $creatorName,
            'display_name' => $creator['display_name'],
            'initials' => $creator['initials'],
            'avatar_url' => $creator['avatar_url'],
            'at' => $createdAt,
            'datetime_full' => $datetimeCreated,
            'relative' => $createdAt->diffForHumans(),
            'side' => 'right',
            'body_html' => '<p class="m-0 mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Descripción enviada en el alta</p>'
                .'<div class="text-sm">'.self::escapeWithLineBreaks((string) $record->description).'</div>',
        ]];

        foreach (self::parseObservationBlocks((string) $record->observation, $record, $timezone) as $parsed) {
            $events[] = $parsed;
        }

        usort($events, function (array $a, array $b): int {
            return $a['at']->getTimestamp() <=> $b['at']->getTimestamp();
        });

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function parseObservationBlocks(string $observation, HelpDesk $record, string $timezone): array
    {
        $trimmed = trim($observation);
        if ($trimmed === '') {
            return [];
        }

        $parts = preg_split('/\n\n(?=\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} · )/', $trimmed);
        if ($parts === false) {
            return [];
        }

        $events = [];
        foreach ($parts as $part) {
            $chunk = trim($part);
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^\[(?<meta>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}) · (?<actor>[^\]]+)\]\n(?<body>.*)$/s', $chunk, $match) === 1) {
                $at = CarbonImmutable::createFromFormat('d/m/Y H:i', $match['meta'], $timezone)
                    ?: CarbonImmutable::parse($record->updated_at)->timezone($timezone);
                $actor = trim((string) $match['actor']);
                $actor = $actor !== '' ? $actor : 'Usuario';
                $noteBody = trim((string) $match['body']);
                $events[] = self::classifyObservationEntry($noteBody, $actor, $at, $record, $timezone);
            } else {
                $at = CarbonImmutable::parse($record->updated_at)->timezone($timezone);
                $actor = (string) ($record->updated_by ?: $record->created_by ?: 'Usuario');
                $presentation = HelpdeskTimelineActorResolver::resolve($actor);
                $datetimeFull = self::formatDatetimeFull($at, $timezone);
                $events[] = [
                    'type' => 'legacy_note',
                    'title' => 'Entrada antigua en el historial',
                    'summary' => 'Esta información apareció en el campo de observaciones antes de que el sistema generara entradas con fecha y autor en cada bloque. '
                        .'Se muestra tal cual para no perder contexto. Fecha de referencia usada: '.$datetimeFull.'.',
                    'actor' => $actor,
                    'display_name' => $presentation['display_name'],
                    'initials' => $presentation['initials'],
                    'avatar_url' => $presentation['avatar_url'],
                    'at' => $at,
                    'datetime_full' => $datetimeFull,
                    'relative' => $at->diffForHumans(),
                    'side' => 'left',
                    'body_html' => self::escapeWithLineBreaks($chunk),
                ];
            }
        }

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private static function classifyObservationEntry(
        string $noteBody,
        string $actor,
        CarbonImmutable $at,
        HelpDesk $record,
        string $timezone,
    ): array {
        $presentation = HelpdeskTimelineActorResolver::resolve($actor);
        $datetimeFull = self::formatDatetimeFull($at, $timezone);
        $relative = $at->diffForHumans();
        $side = self::resolveSide($actor, (string) $record->created_by);

        if (preg_match('/Estado del ticket actualizado de <strong>([^<]*)<\/strong> a <strong>([^<]*)<\/strong>/iu', $noteBody, $st) === 1) {
            $fromRaw = trim(html_entity_decode($st[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $toRaw = trim(html_entity_decode($st[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $fromLabel = HelpdeskTaskStatusOptions::all()[$fromRaw] ?? $fromRaw;
            $toLabel = HelpdeskTaskStatusOptions::all()[$toRaw] ?? $toRaw;

            return [
                'type' => 'status_change',
                'title' => 'Cambio de estado del flujo',
                'summary' => $presentation['display_name'].' movió el ticket en el flujo de trabajo: de «'.$fromLabel.'» (código interno: '.$fromRaw.') '
                    .'a «'.$toLabel.'» (código interno: '.$toRaw.'). Los estados indican si el caso está pendiente de iniciar, en proceso, terminado o cancelado. '
                    .'Registro en bitácora: '.$datetimeFull.' (referencia relativa: '.$relative.').',
                'actor' => $actor,
                'display_name' => $presentation['display_name'],
                'initials' => $presentation['initials'],
                'avatar_url' => $presentation['avatar_url'],
                'at' => $at,
                'datetime_full' => $datetimeFull,
                'relative' => $relative,
                'side' => $side,
                'body_html' => self::renderBodyHtml($noteBody),
            ];
        }

        if (preg_match('/Prioridad actualizada de <strong>([^<]*)<\/strong> a <strong>([^<]*)<\/strong>/iu', $noteBody, $prio) === 1) {
            $from = trim(html_entity_decode($prio[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $to = trim(html_entity_decode($prio[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return [
                'type' => 'priority_change',
                'title' => 'Cambio de prioridad del ticket',
                'summary' => $presentation['display_name'].' ejecutó un cambio explícito de urgencia: la prioridad pasó de «'.$from.'» a «'.$to.'». '
                    .'Esto orienta al equipo asignado sobre qué tan rápido deben actuar. Momento exacto del registro: '.$datetimeFull
                    .' (referencia relativa: '.$relative.'). La prioridad en esta mesa suele ajustarla quien creó el ticket.',
                'actor' => $actor,
                'display_name' => $presentation['display_name'],
                'initials' => $presentation['initials'],
                'avatar_url' => $presentation['avatar_url'],
                'at' => $at,
                'datetime_full' => $datetimeFull,
                'relative' => $relative,
                'side' => $side,
                'body_html' => self::renderBodyHtml($noteBody),
            ];
        }

        $plain = trim(html_entity_decode(strip_tags($noteBody), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (preg_match('/Prioridad actualizada de\s*(.+?)\s+a\s+(.+)$/u', $plain, $prioPlain) === 1) {
            $from = trim(rtrim($prioPlain[1], '.'));
            $to = trim(rtrim($prioPlain[2], '.'));

            return [
                'type' => 'priority_change',
                'title' => 'Cambio de prioridad del ticket',
                'summary' => $presentation['display_name'].' ejecutó un cambio explícito de urgencia: la prioridad pasó de «'.$from.'» a «'.$to.'». '
                    .'Esto orienta al equipo asignado sobre qué tan rápido deben actuar. Momento exacto del registro: '.$datetimeFull
                    .' (referencia relativa: '.$relative.').',
                'actor' => $actor,
                'display_name' => $presentation['display_name'],
                'initials' => $presentation['initials'],
                'avatar_url' => $presentation['avatar_url'],
                'at' => $at,
                'datetime_full' => $datetimeFull,
                'relative' => $relative,
                'side' => $side,
                'body_html' => self::renderBodyHtml($noteBody),
            ];
        }

        return [
            'type' => 'note',
            'title' => 'Nota de seguimiento',
            'summary' => $presentation['display_name'].' añadió una nota al hilo conversacional del ticket. Las notas documentan acuerdos, '
                .'evidencias o próximos pasos sin sustituir los campos formales de estado o prioridad. Fecha y hora del mensaje: '.$datetimeFull
                .'. Referencia relativa: '.$relative.'.',
            'actor' => $actor,
            'display_name' => $presentation['display_name'],
            'initials' => $presentation['initials'],
            'avatar_url' => $presentation['avatar_url'],
            'at' => $at,
            'datetime_full' => $datetimeFull,
            'relative' => $relative,
            'side' => $side,
            'body_html' => self::renderBodyHtml($noteBody),
        ];
    }

    private static function formatDatetimeFull(CarbonImmutable $at, string $timezone): string
    {
        return $at->format('d/m/Y').' a las '.$at->format('H:i').' (zona horaria: '.$timezone.')';
    }

    private static function renderBodyHtml(string $body): string
    {
        if ($body === '') {
            return '';
        }

        if ((bool) preg_match('/<[a-z][\s\S]*>/i', $body)) {
            return HelpdeskNoteHtmlSanitizer::sanitize($body);
        }

        return self::escapeWithLineBreaks($body);
    }

    private static function resolveSide(string $actor, string $creator): string
    {
        if (trim($actor) !== '' && trim($creator) !== '' && trim($actor) === trim($creator)) {
            return 'right';
        }

        return 'left';
    }

    private static function escapeWithLineBreaks(string $content): string
    {
        return nl2br(e($content));
    }
}
