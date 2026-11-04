<?php

declare(strict_types=1);

namespace App\Support\Filament;

/**
 * URL de descarga de archivos que nunca deben abrirse con la navegación SPA de un panel.
 *
 * Con `->spa()`, Filament agrega `wire:navigate` a los enlaces de botones y acciones: Livewire
 * trae la URL con fetch e inyecta la respuesta como si fuera una página. Para un PDF eso pinta
 * sus bytes como texto e ignora `Content-Disposition: attachment`.
 */
final class SpaDownloadExceptions
{
    /**
     * Patrones de `Str::is()` sobre la URL completa.
     *
     * @return list<string>
     */
    public static function patterns(): array
    {
        return [
            '*/business/certificados-afiliacion/*',
            '*/certificado/envio/*',
            '*/business/capitation-plans/pdf*',
        ];
    }
}
