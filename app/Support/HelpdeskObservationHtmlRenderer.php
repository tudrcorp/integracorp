<?php

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

        $parts = preg_split('/\n\n(?=\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} · )/', $trimmed);
        if ($parts === false) {
            return nl2br(e($trimmed));
        }

        $chunks = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\[(?<meta>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} · [^\]]+)\]\n(?<body>.*)$/s', $part, $m)) {
                $meta = e($m['meta']);
                $bodyHtml = self::renderNoteBody(trim($m['body']));
                $chunks[] = '<div class="helpdesk-note-entry mb-5 last:mb-0">'
                    .'<p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">['.$meta.']</p>'
                    .'<div class="helpdesk-note-body text-sm leading-relaxed text-gray-800 dark:text-gray-100 [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:text-sky-600 [&_a]:underline dark:[&_a]:text-sky-400 [&_strong]:font-semibold [&_mark]:rounded [&_mark]:px-0.5">'.$bodyHtml.'</div>'
                    .'</div>';
            } else {
                $chunks[] = '<div class="helpdesk-note-entry mb-5 last:mb-0 text-sm leading-relaxed text-gray-800 dark:text-gray-100">'
                    .nl2br(e($part))
                    .'</div>';
            }
        }

        return implode('', $chunks);
    }

    private static function renderNoteBody(string $body): string
    {
        if ($body === '') {
            return '';
        }

        if (self::looksLikeHtml($body)) {
            return HelpdeskNoteHtmlSanitizer::sanitize($body);
        }

        return nl2br(e($body));
    }

    private static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<[a-z][\s\S]*>/i', $value);
    }
}
