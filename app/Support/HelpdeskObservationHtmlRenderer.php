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

        return '<div class="helpdesk-notes-feed">'.implode('', $cards).'</div>';
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

        $label = $count === 1 ? '1 entrada' : $count.' entradas';

        return '<div class="helpdesk-notes-summary">'
            .'<span class="helpdesk-notes-summary__icon" aria-hidden="true">📋</span>'
            .'<div class="helpdesk-notes-summary__text">'
            .'<span class="helpdesk-notes-summary__title">Historial del ticket</span>'
            .'<span class="helpdesk-notes-summary__count">'.e($label).' · más reciente arriba</span>'
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

        return '<article class="helpdesk-note-card helpdesk-note-card--'.e($entry['type']).'">'
            .'<div class="helpdesk-note-card__shell">'
            .'<div class="helpdesk-note-card__head">'
            .'<span class="helpdesk-note-card__avatar" aria-hidden="true">'.e($initials).'</span>'
            .'<div class="helpdesk-note-card__identity">'
            .'<span class="helpdesk-note-card__name">'.e($displayName).'</span>'
            .'<time class="helpdesk-note-card__time" datetime="">'.e($entry['datetime']).'</time>'
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

        $inner = self::looksLikeHtml($body)
            ? HelpdeskNoteHtmlSanitizer::sanitize($body)
            : '<p>'.nl2br(e($body)).'</p>';

        if ($type === 'status_change' || $type === 'priority_change') {
            return '<div class="helpdesk-note-card__callout">'.$inner.'</div>';
        }

        return $inner;
    }

    private static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<[a-z][\s\S]*>/i', $value);
    }
}
