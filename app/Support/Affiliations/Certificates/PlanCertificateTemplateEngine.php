<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Motor del diseño HTML de la hoja del certificado de un plan (Generador de Certificado).
 *
 * El HTML que sube el analista NUNCA se ejecuta como código (no es Blade ni PHP):
 *  - Marcadores `{{grupo.dato}}` que se reemplazan con datos de la base, siempre escapados.
 *  - Bloques que se repiten: `<!-- inicio:beneficios --> … <!-- fin:beneficios -->` y
 *    `<!-- inicio:afiliados --> … <!-- fin:afiliados -->`.
 *  - Bloques condicionales: `<!-- si:pagado --> … <!-- fin:pagado -->` (y `no_pagado`, etc.).
 *
 * Al subirlo se limpia: sin scripts, marcos, formularios, eventos `on…` ni recursos externos.
 * Las imágenes solo pueden ser las del sistema (`{{imagen.…}}`) o incrustadas (data:image).
 */
final class PlanCertificateTemplateEngine
{
    /** 5 MB: un diseño con imágenes incrustadas en base64 pesa varios cientos de KB. */
    public const MAX_BYTES = 5242880;

    /** Afiliados que admite el bloque «afiliados» en la hoja (la relación completa va aparte). */
    public const COVER_PEOPLE_LIMIT = 15;

    private const FORBIDDEN_TAGS = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'base', 'audio', 'video', 'source', 'track', 'portal', 'noscript', 'template', 'foreignobject'];

    private const URL_ATTRIBUTES = ['src', 'href', 'xlink:href', 'background', 'poster', 'data', 'action', 'formaction', 'srcset', 'lowsrc', 'dynsrc'];

    private const LOOPS = ['beneficios', 'afiliados'];

    /**
     * Marcadores generales: clave => [descripción, ejemplo].
     *
     * @return array<string, array<string, array{0: string, 1: string}>>
     */
    public static function catalog(): array
    {
        return [
            'Certificado' => [
                'certificado.codigo' => ['Código de la afiliación', 'TDEC-COR-00054'],
                'certificado.tipo' => ['Individual o Corporativo', 'Corporativo'],
                'certificado.emision' => ['Fecha de emisión', '11/11/2026'],
                'certificado.verificacion' => ['Clave de verificación', 'CER-7K2Q-…'],
                'certificado.verificacion_url' => ['Enlace de verificación', 'https://…/certificado/verificar/…'],
                'pagina.total' => ['Total de páginas del documento', '12'],
                'relacion.paginas' => ['Dónde está la relación de afiliados', 'las páginas 2 a 4'],
            ],
            'Plan y cobertura' => [
                'plan.nombre' => ['Nombre corto del plan', 'IDEAL'],
                'plan.etiqueta' => ['Nombre completo del plan', 'PLAN IDEAL'],
                'plan.subtitulo' => ['Subtítulo del plan', 'Asistencia médica domiciliaria'],
                'cobertura.titulo' => ['Rótulo del monto destacado', 'Cobertura'],
                'cobertura.valor' => ['Monto destacado', 'US$ 50.000,00'],
                'cobertura.nota' => ['Nota del monto', 'por evento'],
                'cobertura.carnet' => ['Cobertura que va en el carnet', 'US$ 50.000'],
                'beneficios.total' => ['Cantidad de beneficios', '9'],
            ],
            'Vigencia y pago' => [
                'vigencia.desde' => ['Inicio de la vigencia', '21/10/2026'],
                'vigencia.hasta' => ['Fin de la vigencia', '20/10/2027'],
                'periodo.facturado' => ['Período que se factura', 'Oct. 2026 – Ene. 2027'],
                'periodo.pagado' => ['Período pagado', 'Oct. 2026 – Ene. 2027'],
                'pago.estado' => ['Al día o Pendiente', 'Al día'],
                'tarifa.anual' => ['Tarifa anual', 'US$ 437.998,00'],
                'afiliacion.fecha_inicial' => ['Fecha de afiliación inicial', '21/10/2025'],
            ],
            'Contratante y afiliados' => [
                'contratante.nombre' => ['Empresa o titular contratante', 'MAPFRE LA SEGURIDAD, C.A.'],
                'contratante.documento' => ['RIF o C.I. del contratante', 'J-00002152-4'],
                'agente.nombre' => ['Agente', 'MARÍA PÉREZ'],
                'afiliados.total' => ['Cantidad de afiliados', '2.702'],
                'afiliados.titulo' => ['Título del grupo', 'Colaboradores afiliados'],
            ],
            'Colores del plan' => [
                'tema.fondo' => ['Color de fondo del plan', '#26B4E8'],
                'tema.texto' => ['Color de texto sobre el fondo', '#0B3D52'],
                'tema.subtitulo' => ['Color de subtítulos', '#0B3D52'],
                'tema.titulo' => ['Color del título', '#FFFFFF'],
                'tema.valor' => ['Color de montos', '#FFFFFF'],
                'tema.divisor' => ['Color de líneas', '#7FD0EF'],
            ],
            'Imágenes (usar en src)' => [
                'imagen.logo' => ['Logo Tu Doctor en Casa', 'src="{{imagen.logo}}"'],
                'imagen.logo_tema' => ['Logo según el fondo del plan', 'src="{{imagen.logo_tema}}"'],
                'imagen.sello' => ['Sello', 'src="{{imagen.sello}}"'],
                'imagen.firma' => ['Firma autorizada', 'src="{{imagen.firma}}"'],
                'imagen.qr' => ['QR de verificación', 'src="{{imagen.qr}}"'],
                'imagen.ola' => ['Ola decorativa del plan', 'src="{{imagen.ola}}"'],
            ],
        ];
    }

    /**
     * Marcadores de cada elemento de un bloque que se repite.
     *
     * @return array<string, array<string, string>>
     */
    public static function loopCatalog(): array
    {
        return [
            'beneficios' => [
                'beneficio.descripcion' => 'Descripción del beneficio',
                'beneficio.detalle' => 'Detalle o tope del beneficio',
                'beneficio.seccion' => 'Título de sección (solo en filas de sección)',
            ],
            'afiliados' => [
                'afiliado.numero' => 'N.º en la lista',
                'afiliado.nombre' => 'Nombre y apellido',
                'afiliado.documento' => 'Documento',
                'afiliado.nacimiento' => 'Fecha de nacimiento',
                'afiliado.parentesco' => 'Parentesco o plan',
                'afiliado.cobertura' => 'Cobertura del afiliado',
            ],
        ];
    }

    /**
     * Condiciones disponibles para `<!-- si:… -->` (y su versión `no_…`).
     *
     * @return array<string, string>
     */
    public static function conditions(): array
    {
        return [
            'pagado' => 'La cuota vigente está pagada',
            'individual' => 'Afiliación de una sola persona',
            'grupo' => 'Más de un afiliado',
            'con_cobertura' => 'Hay un monto destacado para mostrar',
            'nota_preexistencia' => 'Debe ir la nota de patologías preexistentes',
            'seccion' => 'Dentro de «beneficios»: la fila es un título de sección',
            'limitado' => 'Dentro de «beneficios»: el beneficio tiene tope',
        ];
    }

    /**
     * Quita todo lo que pueda ejecutar código o cargar recursos de afuera.
     *
     * @return array{html: string, removed: list<string>}
     */
    public static function sanitize(string $html): array
    {
        $removed = [];
        $html = (string) preg_replace('/<\?(?:php|=)?.*?\?>/is', '', $html, -1, $phpCount);
        if ($phpCount > 0) {
            $removed[] = 'Código PHP ('.$phpCount.')';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);

        foreach (self::FORBIDDEN_TAGS as $tag) {
            $nodes = iterator_to_array($xpath->query('//'.$tag) ?: []);
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }

            if ($nodes !== []) {
                $removed[] = '<'.$tag.'> ('.count($nodes).')';
            }
        }

        foreach (iterator_to_array($xpath->query('//meta') ?: []) as $meta) {
            if ($meta instanceof DOMElement && ! $meta->hasAttribute('charset')) {
                $meta->parentNode?->removeChild($meta);
                $removed[] = '<meta> sin charset';
            }
        }

        foreach (iterator_to_array($xpath->query('//*') ?: []) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = trim((string) $attribute->nodeValue);

                if (str_starts_with($name, 'on')) {
                    $element->removeAttribute($attribute->nodeName);
                    $removed[] = 'Evento '.$name;

                    continue;
                }

                if (in_array($name, self::URL_ATTRIBUTES, true) && ! self::isAllowedUrl($value, $name)) {
                    $element->removeAttribute($attribute->nodeName);
                    $removed[] = 'Recurso no permitido en '.$name.' ('.mb_substr($value, 0, 60).')';

                    continue;
                }

                if ($name === 'style') {
                    $clean = self::cleanCss($value, $removed);
                    if ($clean !== $value) {
                        $element->setAttribute('style', $clean);
                    }
                }
            }
        }

        foreach (iterator_to_array($xpath->query('//style') ?: []) as $style) {
            $clean = self::cleanCss((string) $style->textContent, $removed);
            if ($clean !== $style->textContent) {
                $style->textContent = $clean;
            }
        }

        $out = (string) $doc->saveHTML();
        $out = (string) preg_replace('/<\?xml encoding="UTF-8"\??>/i', '', $out);
        // libxml codifica las llaves dentro de atributos de URL: se devuelven a su forma.
        $out = (string) preg_replace_callback('/%7B%7B(.*?)%7D%7D/i', static fn (array $m): string => '{{'.rawurldecode($m[1]).'}}', $out);

        return ['html' => trim($out), 'removed' => array_values(array_unique($removed))];
    }

    /**
     * Errores de estructura: marcadores o bloques que no existen, bloques sin cerrar.
     *
     * @return list<string>
     */
    public static function validate(string $html): array
    {
        $errors = [];
        $known = [];
        foreach (self::catalog() as $group) {
            $known += $group;
        }

        $blockNames = [...self::LOOPS, ...array_keys(self::conditions()), ...array_map(static fn (string $c): string => 'no_'.$c, array_keys(self::conditions()))];

        preg_match_all('/<!--\s*(inicio|si|fin):([a-z_]+)\s*-->/', $html, $blocks, PREG_SET_ORDER);
        $open = [];
        foreach ($blocks as [$full, $type, $name]) {
            if ($type === 'inicio' && ! in_array($name, self::LOOPS, true)) {
                $errors[] = 'El bloque «inicio:'.$name.'» no existe. Use inicio:beneficios o inicio:afiliados.';

                continue;
            }

            if ($type === 'si' && ! in_array($name, $blockNames, true)) {
                $errors[] = 'La condición «si:'.$name.'» no existe.';

                continue;
            }

            if ($type === 'fin') {
                if (! isset($open[$name]) || $open[$name] < 1) {
                    $errors[] = '«fin:'.$name.'» no tiene su apertura.';
                } else {
                    $open[$name]--;
                }

                continue;
            }

            $open[$name] = ($open[$name] ?? 0) + 1;
        }

        foreach ($open as $name => $count) {
            if ($count > 0) {
                $errors[] = 'El bloque «'.$name.'» se abrió y no se cerró con <!-- fin:'.$name.' -->.';
            }
        }

        $inLoops = [];
        foreach (self::LOOPS as $loop) {
            preg_match_all('/<!--\s*inicio:'.$loop.'\s*-->(.*?)<!--\s*fin:'.$loop.'\s*-->/s', $html, $matches);
            foreach ($matches[1] as $body) {
                preg_match_all('/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/', $body, $markers);
                foreach ($markers[1] as $marker) {
                    $inLoops[$marker] = $loop;
                }
            }
        }

        preg_match_all('/\{\{\s*([^}]*?)\s*\}\}/', $html, $all);
        foreach (array_unique($all[1]) as $marker) {
            if (isset($known[$marker])) {
                continue;
            }

            $loop = $inLoops[$marker] ?? null;
            if ($loop !== null && isset(self::loopCatalog()[$loop][$marker])) {
                continue;
            }

            $loopOwner = collect(self::loopCatalog())->search(fn (array $items): bool => isset($items[$marker]));
            $errors[] = $loopOwner !== false
                ? 'El marcador {{'.$marker.'}} solo se puede usar dentro de <!-- inicio:'.$loopOwner.' --> … <!-- fin:'.$loopOwner.' -->.'
                : 'El marcador {{'.$marker.'}} no existe. Revise la guía de marcadores.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * Dibuja el HTML con los datos ya ensamblados del certificado.
     *
     * @param  array<string, mixed>  $data  salida de CertificateDocumentData::assemble (+ coverPeople)
     */
    public static function render(string $html, array $data): string
    {
        $values = self::values($data);

        foreach (self::LOOPS as $loop) {
            $html = (string) preg_replace_callback(
                '/<!--\s*inicio:'.$loop.'\s*-->(.*?)<!--\s*fin:'.$loop.'\s*-->/s',
                static function (array $m) use ($loop, $data): string {
                    $out = '';
                    foreach (self::loopItems($loop, $data) as $item) {
                        $body = self::conditionals($m[1], $item['_flags']);
                        $out .= self::markers($body, $item);
                    }

                    return $out;
                },
                $html,
            );
        }

        $html = self::conditionals($html, self::flags($data));

        return self::markers($html, $values);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function values(array $data): array
    {
        $th = (array) ($data['th'] ?? []);
        $img = (array) ($data['images'] ?? []);

        return [
            'certificado.codigo' => (string) ($data['codigo'] ?? ''),
            'certificado.tipo' => ($data['esIndividual'] ?? false) ? 'Individual' : 'Corporativo',
            'certificado.emision' => (string) ($data['emision'] ?? ''),
            'certificado.verificacion' => (string) ($data['verificationKey'] ?? ''),
            'certificado.verificacion_url' => (string) ($data['verificationUrl'] ?? ''),
            'pagina.total' => (string) ($data['totalPaginas'] ?? ''),
            'relacion.paginas' => (string) ($data['relPaginas'] ?? ''),
            'plan.nombre' => (string) ($data['planNombre'] ?? ''),
            'plan.etiqueta' => (string) ($data['planLabel'] ?? ''),
            'plan.subtitulo' => (string) ($data['heroSub'] ?? ''),
            'cobertura.titulo' => (string) ($data['heroLabel'] ?? ''),
            'cobertura.valor' => (string) ($data['heroValor'] ?? ''),
            'cobertura.nota' => (string) ($data['heroNota'] ?? ''),
            'cobertura.carnet' => (string) ($data['carnetCobertura'] ?? ''),
            'beneficios.total' => (string) ($data['nBeneficios'] ?? ''),
            'vigencia.desde' => (string) ($data['desde'] ?? ''),
            'vigencia.hasta' => (string) ($data['hasta'] ?? ''),
            'periodo.facturado' => (string) ($data['periodoFacturado'] ?? ''),
            'periodo.pagado' => (string) ($data['periodoPagado'] ?? ''),
            'pago.estado' => ($data['pagado'] ?? false) ? 'Al día' : 'Pendiente',
            'tarifa.anual' => (string) ($data['tarifaAnual'] ?? ''),
            'afiliacion.fecha_inicial' => (string) ($data['fechaAfiliacion'] ?? ''),
            'contratante.nombre' => (string) ($data['contratante'] ?? ''),
            'contratante.documento' => (string) ($data['contratanteId'] ?? ''),
            'agente.nombre' => (string) ($data['agente'] ?? ''),
            'afiliados.total' => number_format((int) ($data['nAfiliados'] ?? 0), 0, ',', '.'),
            'afiliados.titulo' => (string) ($data['grupoTitulo'] ?? ''),
            'tema.fondo' => (string) ($th['bg'] ?? ''),
            'tema.texto' => (string) ($th['ink'] ?? ''),
            'tema.subtitulo' => (string) ($th['sub'] ?? ''),
            'tema.titulo' => (string) ($th['title'] ?? ''),
            'tema.valor' => (string) ($th['value'] ?? ''),
            'tema.divisor' => (string) ($th['divider'] ?? ''),
            'imagen.logo' => (string) ($img['logo'] ?? ''),
            'imagen.logo_tema' => (string) ($img['logoTheme'] ?? ''),
            'imagen.sello' => (string) ($img['seal'] ?? ''),
            'imagen.firma' => (string) ($img['signature'] ?? ''),
            'imagen.qr' => (string) ($img['qr'] ?? ''),
            'imagen.ola' => (string) ($img['heroWave'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, bool>
     */
    private static function flags(array $data): array
    {
        return [
            'pagado' => (bool) ($data['pagado'] ?? false),
            'individual' => (bool) ($data['esIndividual'] ?? false),
            'grupo' => (bool) ($data['esGrupo'] ?? false),
            'con_cobertura' => ! (bool) ($data['heroHidden'] ?? false),
            'nota_preexistencia' => (bool) ($data['preexNote'] ?? false),
            'seccion' => false,
            'limitado' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private static function loopItems(string $loop, array $data): array
    {
        $base = self::flags($data);

        if ($loop === 'beneficios') {
            return array_map(static fn (array $row): array => [
                'beneficio.descripcion' => (string) ($row['t'] ?? ''),
                'beneficio.detalle' => (string) ($row['cob'] ?? ''),
                'beneficio.seccion' => (string) ($row['section'] ?? ''),
                '_flags' => ['seccion' => isset($row['section']), 'limitado' => (bool) ($row['limited'] ?? false)] + $base,
            ], array_values((array) ($data['benefitRows'] ?? [])));
        }

        return array_map(static fn (array $person): array => [
            'afiliado.numero' => (string) ($person['num'] ?? ''),
            'afiliado.nombre' => (string) ($person['nombre'] ?? ''),
            'afiliado.documento' => (string) ($person['docFmt'] ?? ''),
            'afiliado.nacimiento' => (string) ($person['nac'] ?? ''),
            'afiliado.parentesco' => (string) ($person['parentesco'] ?? ''),
            'afiliado.cobertura' => (string) ($person['coberturaMonto'] ?? $person['cobertura'] ?? ''),
            '_flags' => $base,
        ], array_slice(array_values((array) ($data['coverPeople'] ?? [])), 0, self::COVER_PEOPLE_LIMIT));
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private static function conditionals(string $html, array $flags): string
    {
        return (string) preg_replace_callback(
            '/<!--\s*si:((?:no_)?)([a-z_]+)\s*-->(.*?)<!--\s*fin:\1\2\s*-->/s',
            static function (array $m) use ($flags): string {
                $value = (bool) ($flags[$m[2]] ?? false);

                return ($m[1] === 'no_' ? ! $value : $value) ? $m[3] : '';
            },
            $html,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function markers(string $html, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/',
            static fn (array $m): string => array_key_exists($m[1], $values) && is_string($values[$m[1]])
                ? htmlspecialchars($values[$m[1]], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : $m[0],
            $html,
        );
    }

    private static function isAllowedUrl(string $value, string $attribute): bool
    {
        if ($value === '') {
            return true;
        }

        if (preg_match('/^(\{\{|%7B%7B)\s*imagen\.[a-z_]+\s*(\}\}|%7D%7D)$/i', $value) === 1) {
            return true;
        }

        if (preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml);base64,[a-z0-9+/=\s]+$#i', $value) === 1) {
            return true;
        }

        if ($attribute === 'src' && preg_match('#^data:font/(ttf|truetype|x-font-ttf);base64,[a-z0-9+/=\s]+$#i', $value) === 1) {
            return true;
        }

        return $attribute === 'href' && str_starts_with($value, '#');
    }

    /**
     * @param  list<string>  $removed
     */
    private static function cleanCss(string $css, array &$removed): string
    {
        $clean = (string) preg_replace('/@import[^;]*;?/i', '', $css, -1, $imports);
        $clean = (string) preg_replace('/expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', '', $clean, -1, $scripts);
        $clean = (string) preg_replace_callback('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', static function (array $m) use (&$removed): string {
            if (self::isAllowedUrl(trim($m[2]), 'src')) {
                return $m[0];
            }

            $removed[] = str_starts_with(strtolower(trim($m[2])), 'data:font/')
                ? 'Fuente incrustada no compatible con el PDF ('.mb_substr($m[2], 0, 22).'…)'
                : 'Recurso externo en CSS ('.mb_substr($m[2], 0, 60).')';

            return 'none';
        }, $clean);

        if ($imports > 0) {
            $removed[] = '@import en CSS';
        }

        if ($scripts > 0) {
            $removed[] = 'Código en CSS';
        }

        return $clean;
    }
}
