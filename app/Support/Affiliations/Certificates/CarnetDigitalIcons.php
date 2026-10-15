<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Íconos del carnet digital, incrustados como SVG.
 *
 * El diseño los pide al CDN de Lucide (lucide-static 0.427.0, licencia ISC) en
 * cada visita; aquí van copiados para que la página abra igual sin conexión al
 * CDN y sin un parpadeo de íconos vacíos.
 */
final class CarnetDigitalIcons
{
    /** @var array<string, string> */
    private const PATHS = [
        'ambulance' => '<path d="M10 10H6"/> <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/> <path d="M19 18h2a1 1 0 0 0 1-1v-3.28a1 1 0 0 0-.684-.948l-1.923-.641a1 1 0 0 1-.578-.502l-1.539-3.076A1 1 0 0 0 16.382 8H14"/> <path d="M8 8v4"/> <path d="M9 18h6"/> <circle cx="17" cy="18" r="2"/> <circle cx="7" cy="18" r="2"/>',
        'arrow-up-right' => '<path d="M7 7h10v10"/> <path d="M7 17 17 7"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'clipboard-plus' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/> <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/> <path d="M9 14h6"/> <path d="M12 17v-6"/>',
        'contact' => '<path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"/> <rect width="18" height="18" x="3" y="4" rx="2"/> <circle cx="12" cy="10" r="2"/> <line x1="8" x2="8" y1="2" y2="4"/> <line x1="16" x2="16" y1="2" y2="4"/>',
        'credit-card' => '<rect width="20" height="14" x="2" y="5" rx="2"/> <line x1="2" x2="22" y1="10" y2="10"/>',
        'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/> <path d="M14 2v4a2 2 0 0 0 2 2h4"/> <path d="M10 9H8"/> <path d="M16 13H8"/> <path d="M16 17H8"/>',
        'id-card' => '<path d="M16 10h2"/> <path d="M16 14h2"/> <path d="M6.17 15a3 3 0 0 1 5.66 0"/> <circle cx="9" cy="11" r="2"/> <rect x="2" y="5" width="20" height="14" rx="2"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/> <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'mouse-pointer-click' => '<path d="M14 4.1 12 6"/> <path d="m5.1 8-2.9-.8"/> <path d="m6 12-1.9 2"/> <path d="M7.2 2.2 8 5.1"/> <path d="M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'search' => '<circle cx="11" cy="11" r="8"/> <path d="m21 21-4.3-4.3"/>',
        'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/> <path d="m9 12 2 2 4-4"/>',
        'sparkles' => '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/> <path d="M20 3v4"/> <path d="M22 5h-4"/> <path d="M4 17v2"/> <path d="M5 18H3"/>',
        'stethoscope' => '<path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/> <path d="M8 15v1a6 6 0 0 0 6 6a6 6 0 0 0 6-6v-4"/> <circle cx="20" cy="10" r="2"/>',
        'triangle-alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/> <path d="M12 9v4"/> <path d="M12 17h.01"/>',
        'x' => '<path d="M18 6 6 18"/> <path d="m6 6 12 12"/>',
    ];

    public static function svg(string $name, int $size = 20, string $color = 'currentColor'): HtmlString
    {
        $paths = self::PATHS[$name] ?? throw new InvalidArgumentException("Ícono «{$name}» no disponible en el carnet digital.");

        return new HtmlString(sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="%2$s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
            $size,
            e($color),
            $paths,
        ));
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::PATHS);
    }
}
