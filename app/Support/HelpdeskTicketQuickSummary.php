<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

/**
 * Convierte la descripción HTML de un ticket en un resumen corto y legible para el
 * aviso del ticker de helpdesk. Respeta los saltos entre párrafos (antes se pegaban
 * las frases al quitar las etiquetas) y, si el ticket viene de la página de error,
 * lo reduce a qué pasó, qué dijo el usuario y la causa técnica.
 */
final class HelpdeskTicketQuickSummary
{
    public const ERROR_REPORT_MARKER = 'Reporte automático desde la página de error';

    private const PLAIN_LIMIT = 320;

    private const SUMMARY_LIMIT = 220;

    private const NOTE_LIMIT = 180;

    private const CAUSE_LIMIT = 150;

    /**
     * @return array{
     *     kind: 'error_report'|'plain'|'empty',
     *     text: ?string,
     *     summary: ?string,
     *     note: ?string,
     *     cause: ?string,
     *     error_code: ?string
     * }
     */
    public static function fromDescription(?string $html, ?string $reporterRaw = null, ?string $reporterDisplay = null): array
    {
        $blocks = self::blocks((string) $html);

        if ($blocks === []) {
            return self::result('empty');
        }

        if (str_contains($blocks[0]['text'], self::ERROR_REPORT_MARKER)) {
            return self::errorReport($blocks, $reporterRaw, $reporterDisplay);
        }

        $text = implode("\n", array_column($blocks, 'text'));

        return self::result('plain', text: Str::limit($text, self::PLAIN_LIMIT));
    }

    /**
     * «JOSé LEON» → «José Leon». Vacío → null.
     */
    public static function displayName(?string $name): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');

        return $name === '' ? null : mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * «PENDIENTE POR INICIAR» → «Pendiente por iniciar».
     */
    public static function sentenceCase(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }

    /**
     * @param  list<array{text: string, heading: bool}>  $blocks
     * @return array{kind: 'error_report', text: null, summary: ?string, note: ?string, cause: ?string, error_code: ?string}
     */
    private static function errorReport(array $blocks, ?string $reporterRaw, ?string $reporterDisplay): array
    {
        $summary = null;
        $sections = [];
        $current = null;

        foreach (array_slice($blocks, 1) as $block) {
            if ($block['heading']) {
                $current = $block['text'];
                $sections[$current] ??= [];

                continue;
            }

            if ($current === null) {
                $summary ??= $block['text'];

                continue;
            }

            $sections[$current][] = $block['text'];
        }

        if ($summary !== null && filled($reporterRaw) && filled($reporterDisplay)) {
            $summary = str_replace((string) $reporterRaw, (string) $reporterDisplay, $summary);
        }

        $note = $sections['Lo que dice el usuario'][0] ?? null;
        if ($note === 'No agregó comentarios.') {
            $note = null;
        }

        $cause = $sections['Diagnóstico'][0] ?? null;
        if ($cause !== null) {
            $cause = preg_replace('/^Falla de código:\s*/u', '', $cause);
        }

        $errorCode = $summary !== null && preg_match('/error (\d{3})/u', $summary, $matches) === 1 ? $matches[1] : null;

        return self::result(
            'error_report',
            summary: $summary !== null ? Str::limit($summary, self::SUMMARY_LIMIT) : null,
            note: $note !== null ? Str::limit($note, self::NOTE_LIMIT) : null,
            cause: $cause !== null ? Str::limit($cause, self::CAUSE_LIMIT) : null,
            errorCode: $errorCode,
        );
    }

    /**
     * Bloques de texto de primer nivel: un párrafo, un ítem de lista o un bloque de
     * código. Un párrafo que solo contiene un <strong> se marca como encabezado.
     *
     * @return list<array{text: string, heading: bool}>
     */
    private static function blocks(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        if ($html === strip_tags($html)) {
            return self::plainTextBlocks($html);
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        if (! $loaded || ! $body instanceof DOMElement) {
            return self::plainTextBlocks(strip_tags($html));
        }

        $blocks = [];
        $inline = '';

        foreach ($body->childNodes as $node) {
            $name = $node instanceof DOMElement ? strtolower($node->nodeName) : null;

            if ($name === null || in_array($name, ['strong', 'b', 'em', 'i', 'span', 'a', 'code', 'br'], true)) {
                $inline .= $name === 'br' ? "\n" : $node->textContent;

                continue;
            }

            self::pushBlock($blocks, $inline, false);
            $inline = '';

            if (in_array($name, ['ul', 'ol'], true)) {
                foreach ($node->childNodes as $item) {
                    if ($item instanceof DOMElement && strtolower($item->nodeName) === 'li') {
                        self::pushBlock($blocks, '• '.self::text($item), false);
                    }
                }

                continue;
            }

            self::pushBlock($blocks, self::text($node), self::isHeading($node));
        }

        self::pushBlock($blocks, $inline, false);

        return $blocks;
    }

    /**
     * @return list<array{text: string, heading: bool}>
     */
    private static function plainTextBlocks(string $text): array
    {
        $blocks = [];

        foreach (preg_split('/\R{2,}/u', $text) ?: [] as $paragraph) {
            self::pushBlock($blocks, $paragraph, false);
        }

        return $blocks;
    }

    /**
     * @param  list<array{text: string, heading: bool}>  $blocks
     */
    private static function pushBlock(array &$blocks, string $text, bool $heading): void
    {
        $text = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        if ($text !== '') {
            $blocks[] = ['text' => $text, 'heading' => $heading];
        }
    }

    private static function text(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= $child instanceof DOMElement && strtolower($child->nodeName) === 'br' ? "\n" : $child->textContent;
        }

        return $text;
    }

    private static function isHeading(DOMNode $node): bool
    {
        if (strtolower($node->nodeName) !== 'p') {
            return false;
        }

        $elements = 0;
        $strong = false;

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $elements++;
                $strong = in_array(strtolower($child->nodeName), ['strong', 'b'], true);
            } elseif (trim($child->textContent) !== '') {
                return false;
            }
        }

        return $elements === 1 && $strong;
    }

    /**
     * @return array{kind: 'error_report'|'plain'|'empty', text: ?string, summary: ?string, note: ?string, cause: ?string, error_code: ?string}
     */
    private static function result(
        string $kind,
        ?string $text = null,
        ?string $summary = null,
        ?string $note = null,
        ?string $cause = null,
        ?string $errorCode = null,
    ): array {
        return [
            'kind' => $kind,
            'text' => $text,
            'summary' => $summary,
            'note' => $note,
            'cause' => $cause,
            'error_code' => $errorCode,
        ];
    }
}
