<?php

declare(strict_types=1);

namespace App\Support\Quotes\Design;

use App\Models\QuoteDesign;
use App\Support\QuotePdfPlanStructure;
use Illuminate\Support\Facades\Storage;

/**
 * Tema de una propuesta con diseño: lo único que el PDF lee de un diseño de cotización.
 *
 * Se arma desde el formulario o desde una versión guardada y **siempre** se sanea: un color que
 * no sea hexadecimal, un texto vacío o un archivo que ya no existe caen al valor por defecto. Así
 * una versión vieja o un dato manipulado nunca rompen la propuesta: en el peor caso sale con los
 * colores por defecto.
 *
 * El HTML de diseño nunca se dibuja: de él solo se toman valores ({@see QuoteDesignHtmlImporter}).
 */
final readonly class QuoteDesignTheme
{
    /**
     * Colores del tema, con su valor por defecto y lo que pintan. El orden es el del formulario.
     *
     * @var array<string, array{default: string, label: string, help: string}>
     */
    public const COLORS = [
        'primary' => ['default' => '#052F60', 'label' => 'Color principal', 'help' => 'Título del plan, encabezados de las tablas y cifras de las tarifas.'],
        'header_text' => ['default' => '#FFFFFF', 'label' => 'Texto sobre el color principal', 'help' => 'Letras de los encabezados de las tablas.'],
        'accent' => ['default' => '#29ABE2', 'label' => 'Color de acento', 'help' => 'Línea bajo la cabecera, borde de la tarifa destacada y viñetas de las notas.'],
        'check' => ['default' => '#29ABE2', 'label' => 'Color del check (✓)', 'help' => 'Marca de los beneficios incluidos.'],
        'page_background' => ['default' => '#FFFFFF', 'label' => 'Fondo de la hoja', 'help' => 'Color de fondo de toda la página de cálculos.'],
        'band_background' => ['default' => '#EEF6FB', 'label' => 'Fondo de la franja de datos', 'help' => 'Franja con titular, agente y fecha de emisión.'],
        'surface' => ['default' => '#FFFFFF', 'label' => 'Fondo de las tablas', 'help' => 'Filas de beneficios y tarjetas de tarifas.'],
        'border' => ['default' => '#D7E7F2', 'label' => 'Líneas y bordes', 'help' => 'Separadores de filas, bordes de tablas y del pie.'],
        'text' => ['default' => '#1F2937', 'label' => 'Texto', 'help' => 'Texto general de la página.'],
        'muted' => ['default' => '#6B7280', 'label' => 'Texto secundario', 'help' => 'Etiquetas pequeñas, vigencia y pie de página.'],
    ];

    /**
     * Fuentes incluidas en el motor de PDF. Cualquier otra (Montserrat, IBM Plex…) se sube en TTF.
     *
     * @var array<string, string>
     */
    public const SANS_FAMILIES = [
        'Helvetica' => 'Helvetica (sin serifa, compacta)',
        'DejaVu Sans' => 'DejaVu Sans (sin serifa, amplia)',
        'Times-Roman' => 'Times (con serifa)',
    ];

    /** @var array<string, string> */
    public const MONO_FAMILIES = [
        'DejaVu Sans Mono' => 'DejaVu Sans Mono',
        'Courier' => 'Courier',
    ];

    public const MAX_CONDITIONS = 15;

    public const MAX_FIXED_PAGES = 8;

    /**
     * @param  array<string, string>  $colors
     * @param  list<string>  $conditions
     * @param  list<string>  $pagesBefore  rutas del disco `public`, ya normalizadas a A4
     * @param  list<string>  $pagesAfter
     */
    public function __construct(
        public array $colors,
        public string $sansFamily,
        public string $monoFamily,
        public string $overline,
        public string $benefitsHeading,
        public string $validityNote,
        public bool $showNote,
        public string $noteTitle,
        public string $noteBody,
        public array $conditions,
        public string $footerLeft,
        public string $footerRightPrefix,
        public string $footerRightBrand,
        public ?string $logoPath,
        public ?string $fontRegularPath,
        public ?string $fontBoldPath,
        public ?string $fontMonoPath,
        public array $pagesBefore,
        public array $pagesAfter,
    ) {}

    /**
     * Tema por defecto: el que se propone al crear un diseño nuevo.
     *
     * @return array<string, mixed>
     */
    public static function defaultThemeData(): array
    {
        $note = QuotePdfPlanStructure::acuteIllnessNote();

        return [
            ...array_map(static fn (array $color): string => $color['default'], self::COLORS),
            'sans_family' => 'Helvetica',
            'mono_family' => 'DejaVu Sans Mono',
            'overline' => 'Propuesta económica',
            'benefits_heading' => 'Beneficios del plan',
            'validity_note' => QuotePdfPlanStructure::validityNote(),
            'show_note' => false,
            'note_title' => $note['title'],
            'note_body' => $note['body'],
            'conditions' => QuotePdfPlanStructure::conditions(),
            'footer_left' => '',
            'footer_right_prefix' => 'Un producto de',
            'footer_right_brand' => 'Tu Dr. Group',
        ];
    }

    /**
     * Copia que se guarda en cada versión: todo lo que el PDF necesita, sin depender del diseño
     * editable.
     *
     * @return array<string, mixed>
     */
    public static function snapshotFrom(
        ?array $theme,
        ?array $pagesBefore,
        ?array $pagesAfter,
        ?string $logoPath,
        ?string $fontRegularPath,
        ?string $fontBoldPath,
        ?string $fontMonoPath,
    ): array {
        return [
            'theme' => self::sanitizeThemeData($theme ?? []),
            'pages_before' => self::sanitizePaths($pagesBefore),
            'pages_after' => self::sanitizePaths($pagesAfter),
            'logo_path' => self::sanitizePath($logoPath),
            'font_regular_path' => self::sanitizePath($fontRegularPath),
            'font_bold_path' => self::sanitizePath($fontBoldPath),
            'font_mono_path' => self::sanitizePath($fontMonoPath),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        $theme = self::sanitizeThemeData((array) ($snapshot['theme'] ?? []));

        $colors = [];

        foreach (array_keys(self::COLORS) as $key) {
            $colors[$key] = (string) $theme[$key];
        }

        return new self(
            colors: $colors,
            sansFamily: (string) $theme['sans_family'],
            monoFamily: (string) $theme['mono_family'],
            overline: (string) $theme['overline'],
            benefitsHeading: (string) $theme['benefits_heading'],
            validityNote: (string) $theme['validity_note'],
            showNote: (bool) $theme['show_note'],
            noteTitle: (string) $theme['note_title'],
            noteBody: (string) $theme['note_body'],
            conditions: $theme['conditions'],
            footerLeft: (string) $theme['footer_left'],
            footerRightPrefix: (string) $theme['footer_right_prefix'],
            footerRightBrand: (string) $theme['footer_right_brand'],
            logoPath: self::existingPath($snapshot['logo_path'] ?? null),
            fontRegularPath: self::existingPath($snapshot['font_regular_path'] ?? null),
            fontBoldPath: self::existingPath($snapshot['font_bold_path'] ?? null),
            fontMonoPath: self::existingPath($snapshot['font_mono_path'] ?? null),
            pagesBefore: array_values(array_filter(array_map(self::existingPath(...), self::sanitizePaths($snapshot['pages_before'] ?? null)))),
            pagesAfter: array_values(array_filter(array_map(self::existingPath(...), self::sanitizePaths($snapshot['pages_after'] ?? null)))),
        );
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, mixed>
     */
    public static function sanitizeThemeData(array $theme): array
    {
        $defaults = self::defaultThemeData();
        $clean = [];

        foreach (array_keys(self::COLORS) as $key) {
            $clean[$key] = self::normalizeColor($theme[$key] ?? null) ?? $defaults[$key];
        }

        $clean['sans_family'] = array_key_exists((string) ($theme['sans_family'] ?? ''), self::SANS_FAMILIES)
            ? (string) $theme['sans_family']
            : $defaults['sans_family'];

        $clean['mono_family'] = array_key_exists((string) ($theme['mono_family'] ?? ''), self::MONO_FAMILIES)
            ? (string) $theme['mono_family']
            : $defaults['mono_family'];

        foreach (['overline' => 60, 'benefits_heading' => 60, 'validity_note' => 200, 'note_title' => 120, 'note_body' => 600, 'footer_left' => 120, 'footer_right_prefix' => 60, 'footer_right_brand' => 60] as $key => $max) {
            $value = array_key_exists($key, $theme) ? self::cleanText($theme[$key], $max) : (string) $defaults[$key];
            $clean[$key] = $value;
        }

        /** Sin encabezado ni vigencia la página queda coja: esos dos nunca quedan vacíos. */
        $clean['benefits_heading'] = $clean['benefits_heading'] !== '' ? $clean['benefits_heading'] : (string) $defaults['benefits_heading'];
        $clean['validity_note'] = $clean['validity_note'] !== '' ? $clean['validity_note'] : (string) $defaults['validity_note'];

        $clean['show_note'] = filter_var($theme['show_note'] ?? $defaults['show_note'], FILTER_VALIDATE_BOOLEAN);

        $conditions = $theme['conditions'] ?? $defaults['conditions'];
        $clean['conditions'] = self::sanitizeConditions(is_array($conditions) ? $conditions : []);

        return $clean;
    }

    /**
     * Acepta `#abc`, `#aabbcc` (con o sin `#`, mayúsculas o minúsculas) y `rgb(r, g, b)`.
     * Devuelve `#AABBCC` o `null` si no es un color.
     */
    public static function normalizeColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/', $value, $rgb) === 1) {
            $channels = array_map(static fn (string $channel): int => min(255, (int) $channel), array_slice($rgb, 1, 3));

            return sprintf('#%02X%02X%02X', ...$channels);
        }

        $value = ltrim($value, '#');

        if (preg_match('/^[0-9a-f]{3}$/', $value) === 1) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        if (preg_match('/^[0-9a-f]{6}$/', $value) !== 1) {
            return null;
        }

        return '#'.strtoupper($value);
    }

    public function color(string $key): string
    {
        return $this->colors[$key] ?? self::COLORS[$key]['default'] ?? '#000000';
    }

    public function hasFixedPages(): bool
    {
        return $this->pagesBefore !== [] || $this->pagesAfter !== [];
    }

    public function absolutePath(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->path($path);
    }

    /**
     * @param  array<int|string, mixed>  $conditions
     * @return list<string>
     */
    private static function sanitizeConditions(array $conditions): array
    {
        $clean = [];

        foreach ($conditions as $condition) {
            /** El repetidor del formulario guarda `['text' => …]`; la versión, el texto plano. */
            $text = self::cleanText(is_array($condition) ? ($condition['text'] ?? '') : $condition, 300);

            if ($text !== '') {
                $clean[] = $text;
            }
        }

        return array_slice($clean, 0, self::MAX_CONDITIONS);
    }

    private static function cleanText(mixed $value, int $max): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '';

        return mb_substr(trim($text), 0, $max);
    }

    /**
     * @return list<string>
     */
    private static function sanitizePaths(mixed $paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        $clean = array_values(array_filter(array_map(self::sanitizePath(...), $paths)));

        return array_slice($clean, 0, self::MAX_FIXED_PAGES);
    }

    /**
     * Solo rutas relativas dentro de la carpeta de diseños: nada de `..` ni rutas absolutas.
     */
    private static function sanitizePath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || ! str_starts_with($path, QuoteDesign::DIRECTORY.'/')) {
            return null;
        }

        return $path;
    }

    private static function existingPath(mixed $path): ?string
    {
        $path = self::sanitizePath($path);

        if ($path === null) {
            return null;
        }

        return Storage::disk('public')->exists($path) ? $path : null;
    }
}
