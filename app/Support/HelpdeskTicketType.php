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
            .'Elija la categoría que mejor describa su solicitud.'
            .'</p>'
            .'</div>'
        );
    }

    /**
     * @return array<string, array{
     *     title: string,
     *     subtitle: string,
     *     definition: string,
     *     not_this: string,
     *     tone: string
     * }>
     */
    public static function catalog(): array
    {
        return [
            self::INCIDENT => [
                'title' => 'Incidencia',
                'subtitle' => 'Algo que antes funcionaba bien, ahora se rompió o dejó de funcionar.',
                'definition' => 'Es una interrupción imprevista, una degradación de la calidad de un servicio tecnológico o un error en el sistema que impide que los usuarios realicen su trabajo habitual.',
                'not_this' => 'No es pedir un acceso nuevo ni planificar un cambio.',
                'tone' => 'danger',
            ],
            self::SERVICE_REQUEST => [
                'title' => 'Requerimiento de servicio',
                'subtitle' => 'Nada está roto, nada está fallando; el usuario solo necesita algo que está predefinido.',
                'definition' => 'Es una solicitud formal por parte de un usuario para obtener asistencia, información, acceso o la entrega de un recurso tecnológico estándar ya existente.',
                'not_this' => 'No es un fallo ni una mejora del sistema.',
                'tone' => 'info',
            ],
            self::PROBLEM => [
                'title' => 'Problema',
                'subtitle' => 'La misma falla ocurre una y otra vez y aún no sabemos exactamente por qué sucede.',
                'definition' => 'Es la causa raíz subyacente de una o múltiples incidencias recurrentes que afectan al sistema.',
                'not_this' => 'No es un error puntual de hoy (eso es incidencia).',
                'tone' => 'warning',
            ],
            self::CHANGE_REQUEST => [
                'title' => 'Cambio (RFC)',
                'subtitle' => 'Una modificación planificada que conlleva un riesgo y requiere aprobación.',
                'definition' => 'Es una solicitud formal para modificar, añadir, eliminar o actualizar un componente de la infraestructura tecnológica o del entorno de producción que pueda alterar la continuidad del servicio.',
                'not_this' => 'No es una falla inesperada ni una función nueva.',
                'tone' => 'primary',
            ],
            self::FEATURE_REQUEST => [
                'title' => 'Nuevo desarrollo / mejora',
                'subtitle' => 'No es un error ni un mantenimiento; es evolución de software para agregar valor.',
                'definition' => 'Es una propuesta orientada a construir e incorporar nuevas capacidades, módulos o flujos de trabajo que actualmente el sistema no posee.',
                'not_this' => 'Si algo está roto, use Incidencia.',
                'tone' => 'success',
            ],
        ];
    }
}
