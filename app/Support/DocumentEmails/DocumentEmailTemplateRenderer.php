<?php

declare(strict_types=1);

namespace App\Support\DocumentEmails;

use App\Enums\DocumentEmailType;
use App\Models\DocumentEmailTemplate;
use Illuminate\Support\Str;

/**
 * Convierte un copy de correo (recibo de pago, aviso de cobro) en el asunto y el cuerpo que recibe cada destinatario.
 *
 * El cuerpo viene del editor enriquecido: se sanea al guardar y otra vez al renderizar, y solo
 * sobreviven etiquetas de texto (párrafos, negritas, listas, enlaces) sin atributos de estilo,
 * que es lo que los clientes de correo muestran de forma consistente.
 *
 * Las variables van entre llaves (`{afiliado}`). Sus valores se escapan antes de insertarse,
 * así un nombre con `<` no rompe el HTML del correo.
 */
final class DocumentEmailTemplateRenderer
{
    /**
     * Etiquetas que conserva el cuerpo del correo.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a'];

    /**
     * @param  array<string, string>  $values
     * @return array{subject: string, body_html: string}
     */
    public static function render(DocumentEmailTemplate $template, array $values): array
    {
        return [
            'subject' => self::renderSubject((string) $template->subject, $values),
            'body_html' => self::renderBody((string) $template->body, $values),
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function renderSubject(string $subject, array $values): string
    {
        $plain = trim(strip_tags(self::replace($subject, $values, escape: false)));

        return Str::limit(preg_replace('/\s+/u', ' ', $plain) ?? $plain, 200, '');
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function renderBody(string $body, array $values): string
    {
        $html = self::replace(self::sanitizeBody($body), $values, escape: true);

        return preg_replace('/<p>/i', '<p style="margin:0 0 14px 0;">', $html) ?? $html;
    }

    /**
     * Deja solo etiquetas de texto, sin atributos salvo el `href` seguro de los enlaces.
     */
    public static function sanitizeBody(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $html = (string) Str::of($html)->sanitizeHtml();
        $html = strip_tags($html, self::ALLOWED_TAGS);

        $simpleTags = implode('|', array_diff(self::ALLOWED_TAGS, ['a']));
        $html = preg_replace('/<('.$simpleTags.')\b[^>]*?(\/?)>/i', '<$1$2>', $html) ?? '';

        $html = preg_replace_callback('/<a\b([^>]*)>/i', function (array $match): string {
            if (preg_match('/href\s*=\s*"([^"]*)"/i', $match[1], $href) !== 1
                && preg_match("/href\s*=\s*'([^']*)'/i", $match[1], $href) !== 1) {
                return '<a>';
            }

            $url = html_entity_decode($href[1], ENT_QUOTES | ENT_HTML5);

            if (preg_match('/^(https?:|mailto:)/i', $url) !== 1) {
                return '<a>';
            }

            return '<a href="'.e($url).'" style="color:#0f4c81;">';
        }, $html) ?? '';

        return trim($html);
    }

    /**
     * Variables usadas en el texto que no existen (p. ej. `{nombre}` mal escrito).
     *
     * @return list<string>
     */
    public static function unknownVariables(string $text, DocumentEmailType $document): array
    {
        preg_match_all('/\{\s*([a-z_]+)\s*\}/i', $text, $matches);

        $known = array_keys($document->variables());

        return array_values(array_unique(array_filter(
            array_map(static fn (string $name): string => strtolower($name), $matches[1]),
            static fn (string $name): bool => ! in_array($name, $known, true),
        )));
    }

    /**
     * Texto del cuerpo sin etiquetas, para saber si el copy quedó vacío.
     */
    public static function plainText(string $html): string
    {
        return trim(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], ' ', $html)), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function replace(string $text, array $values, bool $escape): string
    {
        return preg_replace_callback('/\{\s*([a-z_]+)\s*\}/i', function (array $match) use ($values, $escape): string {
            $key = strtolower($match[1]);

            if (! array_key_exists($key, $values)) {
                return $match[0];
            }

            return $escape ? e($values[$key]) : $values[$key];
        }, $text) ?? $text;
    }
}
