<?php

declare(strict_types=1);

namespace App\Support;

final class HelpdeskObservationHtmlRenderer
{
    /**
     * Convierte el historial de observaciones (bloques con cabecera `[dd/mm/aaaa hh:mm · usuario]`) en HTML seguro.
     * Cuerpos en texto plano (notas antiguas) se escapan; cuerpos con marcado se pasan por el sanitizer.
     */
    public static function render(string $observation): string
    {
        $trimmed = trim($observation);
        if ($trimmed === '') {
            return '';
        }

        $entries = self::parseEntries($trimmed);
        if ($entries === []) {
            return '<div class="helpdesk-note-legacy">'.nl2br(e($trimmed)).'</div>';
        }

        $cards = array_map(
            static fn (array $entry): string => self::renderEntryCard($entry),
            array_reverse($entries),
        );

        return '<div class="helpdesk-notes-surface">'
            .'<div class="helpdesk-notes-feed">'.implode('', $cards).'</div>'
            .'</div>';
    }

    public static function renderForModal(string $observation): string
    {
        $trimmed = trim($observation);
        if ($trimmed === '') {
            return '';
        }

        return self::summaryBannerHtml($trimmed).self::render($trimmed);
    }

    public static function countEntries(string $observation): int
    {
        return count(self::parseEntries(trim($observation)));
    }

    public static function summaryBannerHtml(string $observation): string
    {
        $count = self::countEntries($observation);
        if ($count === 0) {
            return '<p class="helpdesk-notes-summary helpdesk-notes-summary--empty">Aún no hay entradas en el historial. Use <strong>Añadir nota</strong> en la tabla del ticket.</p>';
        }

        $entriesLabel = $count === 1 ? 'entrada' : 'entradas';

        return '<div class="helpdesk-notes-summary" role="region" aria-label="Historial del ticket" style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.8rem 1.05rem;margin-bottom:0.75rem">'
            .'<span class="helpdesk-notes-summary__icon" aria-hidden="true" style="display:flex;flex-shrink:0;margin-top:0.08rem;color:inherit">'
            .'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">'
            .'<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />'
            .'</svg>'
            .'</span>'
            .'<div class="helpdesk-notes-summary__text" style="display:flex;flex-direction:column;gap:0.2rem;min-width:0">'
            .'<span class="helpdesk-notes-summary__title" style="display:block">Historial del ticket</span>'
            .'<span class="helpdesk-notes-summary__count" style="display:block">'
            .'<span class="helpdesk-notes-summary__count-value">'.e((string) $count).'</span> '
            .e($entriesLabel)
            .' <span aria-hidden="true">·</span> más reciente arriba'
            .'</span>'
            .'</div>'
            .'</div>';
    }

    /**
     * @return list<array{meta: ?string, actor: string, datetime: string, body: string, type: string}>
     */
    private static function parseEntries(string $trimmed): array
    {
        $parts = preg_split('/\n\n(?=\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} · )/', $trimmed);
        if ($parts === false) {
            return [];
        }

        $entries = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\[(?<meta>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} · [^\]]+)\]\n(?<body>.*)$/s', $part, $m) !== 1) {
                $entries[] = [
                    'meta' => null,
                    'actor' => 'Sistema',
                    'datetime' => '—',
                    'body' => $part,
                    'type' => 'legacy',
                ];

                continue;
            }

            $meta = trim((string) $m['meta']);
            $actor = 'Usuario';
            $datetime = $meta;
            if (preg_match('/^(?<when>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}) · (?<who>.+)$/', $meta, $metaParts) === 1) {
                $datetime = trim($metaParts['when']);
                $actor = trim($metaParts['who']);
            }

            $body = trim((string) $m['body']);
            $entries[] = [
                'meta' => $meta,
                'actor' => $actor !== '' ? $actor : 'Usuario',
                'datetime' => $datetime,
                'body' => $body,
                'type' => self::detectEntryType($body),
            ];
        }

        return $entries;
    }

    private static function detectEntryType(string $body): string
    {
        if (preg_match('/Estado del ticket actualizado/i', $body) === 1) {
            return 'status_change';
        }

        if (preg_match('/Prioridad actualizada/i', $body) === 1) {
            return 'priority_change';
        }

        return 'note';
    }

    /**
     * @param  array{meta: ?string, actor: string, datetime: string, body: string, type: string}  $entry
     */
    private static function renderEntryCard(array $entry): string
    {
        $badge = self::typeBadge($entry['type']);
        $displayName = self::formatDisplayName($entry['actor']);
        $initials = self::initialsFromName($entry['actor']);
        $bodyHtml = self::renderNoteBody($entry['body'], $entry['type']);
        $datetimeIso = self::datetimeToIso($entry['datetime']);

        return '<article class="helpdesk-note-card helpdesk-note-card--'.e($entry['type']).'">'
            .'<div class="helpdesk-note-card__shell">'
            .'<div class="helpdesk-note-card__head">'
            .'<span class="helpdesk-note-card__avatar" aria-hidden="true">'.e($initials).'</span>'
            .'<div class="helpdesk-note-card__identity">'
            .'<span class="helpdesk-note-card__name">'.e($displayName).'</span>'
            .'<time class="helpdesk-note-card__time"'.($datetimeIso !== '' ? ' datetime="'.e($datetimeIso).'"' : '').'>'.e(self::formatDisplayDatetime($entry['datetime'])).'</time>'
            .'</div>'
            .'<span class="helpdesk-note-card__badge helpdesk-note-card__badge--'.e($badge['tone']).'">'.e($badge['label']).'</span>'
            .'</div>'
            .'<div class="helpdesk-note-card__body">'.$bodyHtml.'</div>'
            .'</div>'
            .'</article>';
    }

    /**
     * @return array{label: string, tone: string}
     */
    private static function typeBadge(string $type): array
    {
        return match ($type) {
            'status_change' => ['label' => 'Estado', 'tone' => 'status'],
            'priority_change' => ['label' => 'Prioridad', 'tone' => 'priority'],
            'legacy' => ['label' => 'Histórico', 'tone' => 'legacy'],
            default => ['label' => 'Nota', 'tone' => 'note'],
        };
    }

    private static function formatDisplayName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return 'Usuario';
        }

        return mb_convert_case(mb_strtolower($trimmed, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private static function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1));
    }

    private static function renderNoteBody(string $body, string $type): string
    {
        if ($body === '') {
            return '<p class="helpdesk-note-card__empty">—</p>';
        }

        if ($type === 'status_change') {
            return self::renderStatusChangeBody($body);
        }

        if ($type === 'priority_change') {
            $inner = self::looksLikeHtml($body)
                ? HelpdeskNoteHtmlSanitizer::sanitize($body)
                : '<p>'.nl2br(e($body)).'</p>';

            return '<div class="helpdesk-note-card__callout">'.$inner.'</div>';
        }

        $inner = self::looksLikeHtml($body)
            ? HelpdeskNoteHtmlSanitizer::sanitize($body)
            : '<p>'.nl2br(e($body)).'</p>';

        return '<div class="helpdesk-note-card__content">'.$inner.'</div>';
    }

    private static function renderStatusChangeBody(string $body): string
    {
        $sanitized = self::looksLikeHtml($body)
            ? HelpdeskNoteHtmlSanitizer::sanitize($body)
            : '<p>'.nl2br(e($body)).'</p>';

        $transitionHtml = '';
        if (preg_match(
            '/Estado del ticket actualizado de\s*<strong>(.*?)<\/strong>\s*a\s*<strong>(.*?)<\/strong>/iu',
            $sanitized,
            $matches
        ) === 1) {
            $from = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $to = trim(html_entity_decode(strip_tags($matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $transitionHtml = '<div class="helpdesk-note-status-flow" role="group" aria-label="Cambio de estado">'
                .'<span class="helpdesk-note-status-pill">'.e($from).'</span>'
                .'<span class="helpdesk-note-status-arrow" aria-hidden="true">→</span>'
                .'<span class="helpdesk-note-status-pill helpdesk-note-status-pill--new">'.e($to).'</span>'
                .'</div>';
        }

        $reasonHtml = '';
        if (preg_match(
            '/<p><strong>Motivo del cambio \(analista asignado\):<\/strong><\/p>\s*(.*)$/isu',
            $sanitized,
            $reasonMatch
        ) === 1) {
            $reasonContent = trim((string) $reasonMatch[1]);
            if ($reasonContent !== '' && ! self::isEffectivelyEmptyHtml($reasonContent)) {
                $reasonHtml = '<div class="helpdesk-note-status-reason">'
                    .'<p class="helpdesk-note-status-reason__label">Motivo del cambio</p>'
                    .'<div class="helpdesk-note-status-reason__text">'.$reasonContent.'</div>'
                    .'</div>';
            }
        }

        if ($transitionHtml === '' && $reasonHtml === '') {
            return '<div class="helpdesk-note-card__callout">'.$sanitized.'</div>';
        }

        return '<div class="helpdesk-note-card__callout helpdesk-note-card__callout--status">'
            .$transitionHtml
            .$reasonHtml
            .'</div>';
    }

    private static function formatDisplayDatetime(string $datetime): string
    {
        $trimmed = trim($datetime);
        if ($trimmed === '' || $trimmed === '—') {
            return '—';
        }

        if (preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2})$/', $trimmed, $parts) === 1) {
            return $parts[1].' · '.$parts[2];
        }

        return $trimmed;
    }

    private static function datetimeToIso(string $datetime): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/', trim($datetime), $parts) !== 1) {
            return '';
        }

        return sprintf(
            '%s-%s-%sT%s:%s:00',
            $parts[3],
            $parts[2],
            $parts[1],
            $parts[4],
            $parts[5],
        );
    }

    private static function isEffectivelyEmptyHtml(string $html): bool
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '';
    }

    private static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<[a-z][\s\S]*>/i', $value);
    }
}
