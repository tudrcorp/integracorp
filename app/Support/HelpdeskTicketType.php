<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Catálogo ITIL simplificado para clasificar tickets de mesa de ayuda.
 */
final class HelpdeskTicketType
{
    public const INCIDENT = 'incident';

    public const SERVICE_REQUEST = 'service_request';

    public const PROBLEM = 'problem';

    public const CHANGE_REQUEST = 'change_request';

    public const FEATURE_REQUEST = 'feature_request';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::catalog())
            ->mapWithKeys(static fn (array $item, string $key): array => [
                $key => $item['title'].' — '.$item['subtitle'],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function radioDescriptions(): array
    {
        return collect(self::catalog())
            ->mapWithKeys(static fn (array $item, string $key): array => [
                $key => $item['definition'],
            ])
            ->all();
    }

    public static function label(?string $type): string
    {
        if ($type === null || $type === '') {
            return 'Sin tipo';
        }

        return self::catalog()[$type]['title'] ?? $type;
    }

    public static function filamentColor(?string $type): string
    {
        if ($type === null || $type === '' || ! isset(self::catalog()[$type])) {
            return 'gray';
        }

        return match (self::catalog()[$type]['tone']) {
            'danger' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            'primary' => 'primary',
            default => 'info',
        };
    }

    public static function tabIntro(): HtmlString
    {
        return new HtmlString(
            '<div class="fi-helpdesk-ticket-type-intro">'
            .'<p class="fi-helpdesk-ticket-type-intro__title">Paso 2 — Tipo de ticket</p>'
            .'<p class="fi-helpdesk-ticket-type-intro__text">'
            .'Elija la categoría que mejor describa su solicitud. Al seleccionar, verá un resumen debajo.'
            .'</p>'
            .'</div>'
        );
    }

    public static function selectedTypeGuide(?string $type): HtmlString
    {
        if ($type === null || $type === '' || ! isset(self::catalog()[$type])) {
            return new HtmlString('');
        }

        $item = self::catalog()[$type];
        $tone = e($item['tone']);
        $notThis = e($item['not_this']);

        return new HtmlString(
            '<div class="fi-helpdesk-ticket-type-hero fi-helpdesk-ticket-type-hero--'.$tone.'" role="status">'
            .'<div class="fi-helpdesk-ticket-type-hero__head">'
            .'<span class="fi-helpdesk-ticket-type-hero__badge" aria-hidden="true"></span>'
            .'<div>'
            .'<p class="fi-helpdesk-ticket-type-hero__title">'.e($item['title']).'</p>'
            .'<p class="fi-helpdesk-ticket-type-hero__subtitle">'.e($item['subtitle']).'</p>'
            .'</div>'
            .'</div>'
            .'<p class="fi-helpdesk-ticket-type-hero__definition">'.e($item['definition']).'</p>'
            .'<p class="fi-helpdesk-ticket-type-hero__example"><span>Ejemplo:</span> '.e($item['example']).'</p>'
            .'<p class="fi-helpdesk-ticket-type-hero__hint"><span>No usar si:</span> '.e($notThis).'</p>'
            .'</div>'
        );
    }

    public static function emptySelectionHint(): HtmlString
    {
        return new HtmlString(
            '<p class="fi-helpdesk-ticket-type-empty">Seleccione un tipo arriba para ver el resumen confirmado.</p>'
        );
    }

    /**
     * @return array<string, array{
     *     title: string,
     *     subtitle: string,
     *     definition: string,
     *     example: string,
     *     not_this: string,
     *     tone: string
     * }>
     */
    public static function catalog(): array
    {
        return [
            self::INCIDENT => [
                'title' => 'Incidencia',
                'subtitle' => 'Algo dejó de funcionar',
                'definition' => 'Servicio interrumpido o error que antes no existía; hay que restaurar la operación.',
                'example' => 'El botón «Enviar masivos» devuelve error 500.',
                'not_this' => 'No es pedir un acceso nuevo ni planificar un cambio.',
                'tone' => 'danger',
            ],
            self::SERVICE_REQUEST => [
                'title' => 'Requerimiento de servicio',
                'subtitle' => 'Solicitud rutinaria',
                'definition' => 'Necesita algo estándar; nada está roto, solo falta entregarlo o habilitarlo.',
                'example' => 'Crear usuario administrador en Filament.',
                'not_this' => 'No es un fallo ni una mejora del sistema.',
                'tone' => 'info',
            ],
            self::PROBLEM => [
                'title' => 'Problema',
                'subtitle' => 'Falla que se repite',
                'definition' => 'La misma incidencia ocurre una y otra vez y aún no se conoce la causa raíz.',
                'example' => 'Apache se cae todos los martes a las 4:00 p. m.',
                'not_this' => 'No es un error puntual de hoy (eso es incidencia).',
                'tone' => 'warning',
            ],
            self::CHANGE_REQUEST => [
                'title' => 'Cambio (RFC)',
                'subtitle' => 'Modificación planificada',
                'definition' => 'Ajuste de infraestructura o configuración que puede impactar producción.',
                'example' => 'Subir innodb_buffer_pool_size a 12 GB en producción.',
                'not_this' => 'No es una falla inesperada ni una función nueva.',
                'tone' => 'primary',
            ],
            self::FEATURE_REQUEST => [
                'title' => 'Nuevo desarrollo / mejora',
                'subtitle' => 'Funcionalidad nueva',
                'definition' => 'Propuesta que agrega valor y hoy no existe en el sistema.',
                'example' => 'Widget de progreso con botón de pánico en el dashboard.',
                'not_this' => 'Si algo está roto, use Incidencia.',
                'tone' => 'success',
            ],
        ];
    }
}
