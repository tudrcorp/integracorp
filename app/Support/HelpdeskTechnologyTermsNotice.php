<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

final class HelpdeskTechnologyTermsNotice
{
    public const ACCEPTANCE_FIELD = 'technology_terms_accepted';

    public static function bodyHtml(): HtmlString
    {
        return new HtmlString(
            '<div class="fi-helpdesk-technology-terms">'
            .'<p class="fi-helpdesk-technology-terms__lead">'
            .'Su solicitud será recibida por el <strong>Departamento de Tecnología y Sistemas</strong>. '
            .'A partir del momento en que se genere el ticket, el equipo de Tecnología y Sistemas '
            .'iniciará la fase de evaluación técnica de su requerimiento.'
            .'</p>'
            .'<p>'
            .'Durante este proceso, serán examinados el impacto y la complejidad de la solicitud para determinar '
            .'con precisión el tiempo estimado de análisis detallado y la resolución definitiva de la misma. '
            .'Nos comunicaremos nuevamente con usted en un plazo no mayor a <strong>24 horas</strong> después de '
            .'generado el ticket para asignar los plazos de resolución y pruebas necesarias.'
            .'</p>'
            .'<p class="fi-helpdesk-technology-terms__closing">'
            .'Agradecemos su paciencia y colaboración.'
            .'</p>'
            .'</div>'
        );
    }

    public static function acceptanceLabel(): string
    {
        return 'He leído y acepto la información anterior sobre el proceso de atención del Departamento de Tecnología y Sistemas.';
    }
}
