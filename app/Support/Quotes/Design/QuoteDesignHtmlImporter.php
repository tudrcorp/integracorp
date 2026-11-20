<?php

declare(strict_types=1);

namespace App\Support\Quotes\Design;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Lee el HTML que envía el equipo de diseño y propone los valores del tema.
 *
 * El HTML **nunca se dibuja**: DomPDF no entiende flex ni grid ni fuentes web, y dibujar HTML
 * ajeno sería un riesgo. Solo se extraen valores —colores, textos, logo embebido, fuentes— y se
 * devuelve un reporte en español de lo encontrado y de lo que el analista debe completar a mano.
 *
 * Los colores se toman primero de las reglas que los usan (h1, encabezado de tabla, línea de
 * acento…) y, si no aparecen, del nombre de la variable CSS (`--verde`, `--accent`, `--borde`…).
 */
final class QuoteDesignHtmlImporter
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Para cada color del tema: selectores y propiedades donde buscarlo, en orden de preferencia,
     * y palabras del nombre de la variable que sirven de respaldo.
     *
     * @var array<string, array{rules: list<array{0: string, 1: string}>, names: list<string>}>
     */
    private const COLOR_HINTS = [
        'primary' => [
            'rules' => [['h1', 'color'], ['thead tr', 'background'], ['thead', 'background'], ['th', 'background']],
            'names' => ['primar', 'principal', 'marca', 'brand', 'verde', 'azul'],
        ],
        'header_text' => [
            'rules' => [['thead tr', 'color'], ['thead', 'color'], ['th', 'color']],
            'names' => [],
        ],
        'accent' => [
            'rules' => [['.filete', 'background'], ['hr', 'background'], ['hr', 'border-color'], ['.destacada', 'border-top'], ['.featured', 'border-top']],
            'names' => ['acento', 'accent', 'dorad', 'oro', 'gold', 'ocre', 'secund'],
        ],
        'check' => [
            'rules' => [],
            'names' => ['check', 'musgo', 'success', 'exito', 'ok'],
        ],
        'page_background' => [
            'rules' => [['.hoja', 'background'], ['.page', 'background'], ['.sheet', 'background'], ['main', 'background']],
            'names' => ['lino', 'fondo', 'background', 'paper', 'papel'],
        ],
        'band_background' => [
            'rules' => [['.datos', 'background'], ['.data', 'background'], ['.meta', 'background'], ['.band', 'background']],
            'names' => ['arena', 'franja', 'band', 'sand'],
        ],
        'surface' => [
            'rules' => [['.beneficios tbody', 'background'], ['tbody', 'background'], ['.poblacion', 'background']],
            'names' => ['surface', 'superficie'],
        ],
        'border' => [
            'rules' => [['.beneficios tbody tr', 'border-bottom'], ['tbody tr', 'border-bottom'], ['td', 'border-bottom'], ['.pie', 'border-top']],
            'names' => ['piedra', 'borde', 'border', 'linea', 'line', 'divider'],
        ],
        'text' => [
            'rules' => [['body', 'color'], ['html', 'color']],
            'names' => ['tinta', 'texto', 'text', 'ink'],
        ],
        'muted' => [
            'rules' => [['.label', 'color'], ['.ante', 'color'], ['.vigencia', 'color'], ['.muted', 'color']],
            'names' => ['grafito', 'gris', 'muted', 'secondary-text', 'gray', 'grey'],
        ],
    ];

    private const MONO_HINTS = ['mono', 'courier', 'code', 'plex mono', 'consol'];

    /**
     * @return array{theme: array<string, mixed>, logo: ?array{extension: string, contents: string}, report: list<string>, warnings: list<string>}
     */
    public static function analyze(string $html): array
    {
        $report = [];
        $warnings = [];
        $theme = [];

        $css = self::styles($html);
        $variables = self::variables($css);
        $rules = self::rules($css);

        foreach (self::COLOR_HINTS as $key => $hints) {
            $color = self::colorFromRules($rules, $variables, $hints['rules'])
                ?? self::colorFromVariableNames($variables, $hints['names']);

            if ($color !== null) {
                $theme[$key] = $color;
            }
        }

        $document = self::document($html);
        $xpath = $document !== null ? new DOMXPath($document) : null;

        if (! isset($theme['check']) && $xpath !== null) {
            $stroke = $xpath->query('//table//svg/@stroke')?->item(0)?->nodeValue;
            $color = QuoteDesignTheme::normalizeColor($stroke);

            if ($color !== null) {
                $theme['check'] = $color;
            }
        }

        $theme['check'] ??= $theme['primary'] ?? null;

        if ($theme['check'] === null) {
            unset($theme['check']);
        }

        $found = array_intersect_key(QuoteDesignTheme::COLORS, $theme);

        if ($found !== []) {
            $report[] = 'Colores tomados del diseño: '.implode(', ', array_map(
                static fn (string $key, array $color): string => $color['label'].' '.$theme[$key],
                array_keys($found),
                $found,
            )).'.';
        }

        $missing = array_diff_key(QuoteDesignTheme::COLORS, $theme);

        if ($missing !== []) {
            $warnings[] = 'No se encontraron en el HTML y conservan su valor: '.implode(', ', array_column($missing, 'label')).'.';
        }

        $logo = null;

        if ($xpath !== null) {
            $theme = [...$theme, ...self::texts($xpath, $report)];
            $logo = self::logo($xpath, $report, $warnings);
        }

        self::fontsReport($css, $variables, $warnings);

        if (preg_match('/@page\s*\{[^}]*size\s*:\s*letter/i', $css) === 1) {
            $warnings[] = 'El diseño viene en tamaño carta; la propuesta se imprime en A4 y la página se adapta sola.';
        }

        return ['theme' => $theme, 'logo' => $logo, 'report' => $report, 'warnings' => $warnings];
    }

    private static function styles(string $html): string
    {
        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $matches);

        $css = implode("\n", $matches[1] ?? []);

        return preg_replace('/\/\*.*?\*\//s', '', $css) ?? $css;
    }

    /**
     * Variables `--nombre: valor` de cualquier bloque (normalmente `:root`).
     *
     * @return array<string, string>
     */
    private static function variables(string $css): array
    {
        preg_match_all('/(--[a-z0-9_-]+)\s*:\s*([^;}]+)/i', $css, $matches, PREG_SET_ORDER);

        $variables = [];

        foreach ($matches as $match) {
            $variables[strtolower($match[1])] ??= trim($match[2]);
        }

        return $variables;
    }

    /**
     * Reglas de primer nivel (las de `@media` se descartan: son ajustes de impresión).
     *
     * @return list<array{selectors: list<string>, declarations: array<string, string>}>
     */
    private static function rules(string $css): array
    {
        $rules = [];
        $depth = 0;
        $buffer = '';
        $atRule = false;
        $length = strlen($css);

        for ($index = 0; $index < $length; $index++) {
            $char = $css[$index];

            if ($char === '{') {
                if ($depth === 0) {
                    $selector = trim($buffer);
                    $atRule = str_starts_with($selector, '@');
                    $buffer = '';
                    $current = $selector;
                } else {
                    $buffer .= $char;
                }

                $depth++;

                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);

                if ($depth === 0) {
                    if (! $atRule && isset($current)) {
                        $rules[] = [
                            'selectors' => array_values(array_filter(array_map(
                                static fn (string $selector): string => strtolower(preg_replace('/\s+/', ' ', trim($selector)) ?? ''),
                                explode(',', $current),
                            ))),
                            'declarations' => self::declarations($buffer),
                        ];
                    }

                    $buffer = '';
                    $atRule = false;
                } else {
                    $buffer .= $char;
                }

                continue;
            }

            $buffer .= $char;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private static function declarations(string $block): array
    {
        $declarations = [];

        foreach (explode(';', $block) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, null);

            if ($property === null || $value === null) {
                continue;
            }

            $declarations[strtolower(trim($property))] = trim($value);
        }

        return $declarations;
    }

    /**
     * @param  list<array{selectors: list<string>, declarations: array<string, string>}>  $rules
     * @param  array<string, string>  $variables
     * @param  list<array{0: string, 1: string}>  $hints
     */
    private static function colorFromRules(array $rules, array $variables, array $hints): ?string
    {
        foreach ($hints as [$selector, $property]) {
            foreach ($rules as $rule) {
                if (! in_array($selector, $rule['selectors'], true)) {
                    continue;
                }

                $properties = $property === 'background' ? ['background', 'background-color'] : [$property];

                foreach ($properties as $name) {
                    if (! isset($rule['declarations'][$name])) {
                        continue;
                    }

                    $color = self::firstColor(self::resolve($rule['declarations'][$name], $variables));

                    if ($color !== null) {
                        return $color;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $variables
     * @param  list<string>  $names
     */
    private static function colorFromVariableNames(array $variables, array $names): ?string
    {
        foreach ($names as $needle) {
            foreach ($variables as $name => $value) {
                if (! str_contains($name, $needle)) {
                    continue;
                }

                $color = self::firstColor(self::resolve($value, $variables));

                if ($color !== null) {
                    return $color;
                }
            }
        }

        return null;
    }

    /**
     * Sustituye `var(--x, respaldo)` por su valor (hasta 5 niveles de anidamiento).
     *
     * @param  array<string, string>  $variables
     */
    private static function resolve(string $value, array $variables): string
    {
        for ($pass = 0; $pass < 5 && str_contains($value, 'var('); $pass++) {
            $value = preg_replace_callback(
                '/var\(\s*(--[a-z0-9_-]+)\s*(?:,\s*([^)]+))?\)/i',
                static fn (array $match): string => $variables[strtolower($match[1])] ?? trim($match[2] ?? ''),
                $value,
            ) ?? $value;
        }

        return $value;
    }

    private static function firstColor(string $value): ?string
    {
        if (preg_match('/#[0-9a-f]{6}\b|#[0-9a-f]{3}\b|rgba?\([^)]*\)/i', $value, $match) !== 1) {
            return null;
        }

        return QuoteDesignTheme::normalizeColor($match[0]);
    }

    private static function document(string $html): ?DOMDocument
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        /** Sin red ni entidades externas: el HTML es un archivo ajeno. */
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    /**
     * Textos de la página: antetítulo, encabezado de beneficios, vigencia, nota, condiciones y pie.
     *
     * @param  list<string>  $report
     * @return array<string, mixed>
     */
    private static function texts(DOMXPath $xpath, array &$report): array
    {
        $texts = [];

        $overline = $xpath->query('//h1/preceding-sibling::*[1]')?->item(0);

        if ($overline instanceof DOMElement && self::text($overline) !== '' && mb_strlen(self::text($overline)) <= 60) {
            $texts['overline'] = self::text($overline);
        }

        $heading = $xpath->query('(//table//thead//th)[1]')?->item(0);

        if ($heading instanceof DOMElement && self::text($heading) !== '') {
            $texts['benefits_heading'] = self::text($heading);
        }

        foreach ($xpath->query('//p|//span|//div') ?: [] as $node) {
            $text = $node instanceof DOMElement ? self::text($node) : '';

            if (preg_match('/^propuesta v[aá]lida/iu', $text) === 1) {
                $texts['validity_note'] = $text;

                break;
            }
        }

        $noteTitle = $xpath->query('//h2')?->item(0);

        if ($noteTitle instanceof DOMElement && self::text($noteTitle) !== '') {
            $texts['show_note'] = true;
            $texts['note_title'] = self::text($noteTitle);
            $body = $xpath->query('following-sibling::p[1]', $noteTitle)?->item(0);
            $texts['note_body'] = $body instanceof DOMElement ? self::text($body) : '';
        }

        $conditions = [];

        foreach ($xpath->query('//ul/li|//ol/li') ?: [] as $item) {
            $text = $item instanceof DOMElement ? self::text($item) : '';

            if ($text !== '') {
                $conditions[] = ['text' => $text];
            }
        }

        if ($conditions !== []) {
            $texts['conditions'] = array_slice($conditions, 0, QuoteDesignTheme::MAX_CONDITIONS);
        }

        $footer = $xpath->query('//footer')?->item(0);

        if ($footer instanceof DOMElement) {
            $blocks = [];

            foreach ($footer->childNodes as $child) {
                if ($child instanceof DOMElement && self::text($child) !== '') {
                    $blocks[] = $child;
                }
            }

            if ($blocks !== []) {
                $texts['footer_left'] = self::text($blocks[0]);
            }

            if (isset($blocks[1])) {
                $parts = [];

                foreach ($blocks[1]->childNodes as $child) {
                    if ($child instanceof DOMElement && self::text($child) !== '') {
                        $parts[] = self::text($child);
                    }
                }

                if (count($parts) >= 2) {
                    $texts['footer_right_prefix'] = $parts[0];
                    $texts['footer_right_brand'] = implode(' ', array_slice($parts, 1));
                } else {
                    $texts['footer_right_prefix'] = '';
                    $texts['footer_right_brand'] = self::text($blocks[1]);
                }
            }
        }

        $labels = [
            'overline' => 'antetítulo',
            'benefits_heading' => 'encabezado de beneficios',
            'validity_note' => 'vigencia',
            'note_title' => 'nota',
            'conditions' => 'condiciones',
            'footer_left' => 'pie de página',
        ];

        $foundTexts = array_values(array_intersect_key($labels, $texts));

        if ($foundTexts !== []) {
            $report[] = 'Textos tomados del diseño: '.implode(', ', $foundTexts).'.';
        }

        return $texts;
    }

    /**
     * Solo un logo embebido (data URI) puede importarse: uno referenciado por nombre de archivo no
     * viaja dentro del HTML.
     *
     * @param  list<string>  $report
     * @param  list<string>  $warnings
     * @return array{extension: string, contents: string}|null
     */
    private static function logo(DOMXPath $xpath, array &$report, array &$warnings): ?array
    {
        $image = $xpath->query('(//header//img|//img)[1]')?->item(0);

        if (! $image instanceof DOMElement) {
            $warnings[] = 'El diseño no trae logo: suba el logo en la sección «Logo» o se usará el de Tu Doctor en Casa.';

            return null;
        }

        $source = trim($image->getAttribute('src'));

        if (preg_match('/^data:image\/(png|jpe?g);base64,(.+)$/is', $source, $match) === 1) {
            $contents = base64_decode(preg_replace('/\s+/', '', $match[2]) ?? '', true);

            if ($contents !== false && @getimagesizefromstring($contents) !== false) {
                $report[] = 'Logo tomado del diseño.';

                return ['extension' => strtolower($match[1]) === 'png' ? 'png' : 'jpg', 'contents' => $contents];
            }
        }

        $name = basename(parse_url($source, PHP_URL_PATH) ?: $source);
        $warnings[] = 'El logo del diseño es un archivo aparte («'.mb_substr($name, 0, 80).'»): súbalo en la sección «Logo».';

        return null;
    }

    /**
     * @param  array<string, string>  $variables
     * @param  list<string>  $warnings
     */
    private static function fontsReport(string $css, array $variables, array &$warnings): void
    {
        $families = [];

        preg_match_all('/font-family\s*:\s*([^;}]+)/i', self::resolve($css, $variables), $matches);

        foreach ($matches[1] ?? [] as $declaration) {
            $first = trim(explode(',', self::resolve($declaration, $variables))[0], " \t\n\r\"'");

            if ($first !== '' && ! str_starts_with($first, 'var(')) {
                $families[$first] = true;
            }
        }

        $builtIn = ['helvetica', 'arial', 'times', 'times new roman', 'courier', 'courier new', 'dejavu sans', 'dejavu sans mono', 'sans-serif', 'serif', 'monospace', 'inherit'];
        $custom = array_values(array_filter(
            array_keys($families),
            static fn (string $family): bool => ! in_array(strtolower($family), $builtIn, true),
        ));

        foreach ($custom as $family) {
            $isMono = false;

            foreach (self::MONO_HINTS as $hint) {
                if (str_contains(strtolower($family), $hint)) {
                    $isMono = true;
                }
            }

            $warnings[] = $isMono
                ? "El diseño usa la fuente «{$family}» para cifras: súbala en TTF en «Fuente de cifras» para verla igual; mientras, se usa la del tema."
                : "El diseño usa la fuente «{$family}»: súbala en TTF (normal y negrita) para verla igual; mientras, se usa la del tema.";
        }
    }

    private static function text(DOMElement $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? '');
    }
}
