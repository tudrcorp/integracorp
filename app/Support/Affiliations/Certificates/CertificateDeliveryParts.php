<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\AffiliationCertificateIssue;

/**
 * Partes del documento del Generador de Certificado que el analista elige
 * enviar por correo: el certificado (portada y beneficios), la relación de
 * afiliados y los carnets. Cualquier combinación; las tres juntas, con todos
 * los carnets, es el documento completo de la emisión.
 */
final class CertificateDeliveryParts
{
    public const CERTIFICATE = 'certificate';

    public const AFFILIATES = 'affiliates';

    public const CARNETS = 'carnets';

    /** Orden en que las partes aparecen en el PDF. */
    public const ORDER = [self::CERTIFICATE, self::AFFILIATES, self::CARNETS];

    /** Tipos de página de {@see CertificateDocumentData::paginate()} que trae cada parte. */
    private const PAGE_KINDS = [
        self::CERTIFICATE => ['cert', 'cont'],
        self::AFFILIATES => ['rel'],
        self::CARNETS => ['carnet'],
    ];

    /**
     * Partes que tiene esta emisión. La relación solo existe con más de un
     * afiliado y los carnets, si la emisión los incluye.
     *
     * @return list<string>
     */
    public static function availableFor(AffiliationCertificateIssue $issue): array
    {
        $parts = [self::CERTIFICATE];

        if ((int) $issue->affiliates_count > 1) {
            $parts[] = self::AFFILIATES;
        }

        if (($issue->carnet_affiliate_ids ?? []) !== []) {
            $parts[] = self::CARNETS;
        }

        return $parts;
    }

    /**
     * Opciones para el formulario, con el tamaño de cada parte.
     *
     * @return array<string, string>
     */
    public static function optionsFor(AffiliationCertificateIssue $issue): array
    {
        $affiliates = (int) $issue->affiliates_count;
        $carnets = count($issue->carnet_affiliate_ids ?? []);
        $labels = [
            self::CERTIFICATE => 'Certificado',
            self::AFFILIATES => 'Lista de afiliados ('.number_format($affiliates, 0, ',', '.').')',
            self::CARNETS => $carnets === 1 ? 'Carnet (1)' : 'Carnets ('.number_format($carnets, 0, ',', '.').')',
        ];

        return array_intersect_key($labels, array_flip(self::availableFor($issue)));
    }

    /**
     * @return array<string, string>
     */
    public static function descriptionsFor(AffiliationCertificateIssue $issue): array
    {
        return array_intersect_key([
            self::CERTIFICATE => 'Portada y beneficios del plan',
            self::AFFILIATES => 'Relación con todos los afiliados',
            self::CARNETS => 'Todos o solo algunos',
        ], array_flip(self::availableFor($issue)));
    }

    /**
     * Partes válidas para la emisión, sin repetir y en el orden del documento.
     *
     * @param  iterable<mixed>  $parts
     * @return list<string>
     */
    public static function clean(iterable $parts, AffiliationCertificateIssue $issue): array
    {
        $wanted = [];

        foreach ($parts as $part) {
            if (is_string($part)) {
                $wanted[$part] = true;
            }
        }

        return array_values(array_filter(
            self::availableFor($issue),
            static fn (string $part): bool => isset($wanted[$part]),
        ));
    }

    /**
     * @param  list<string>  $parts
     * @return list<string>
     */
    public static function pageKinds(array $parts): array
    {
        $kinds = [];

        foreach ($parts as $part) {
            array_push($kinds, ...(self::PAGE_KINDS[$part] ?? []));
        }

        return $kinds;
    }

    /**
     * Cómo se nombra lo enviado en el correo: «el certificado de afiliación, la
     * relación de afiliados y los 3 carnets de afiliado».
     *
     * @param  list<string>  $parts
     */
    public static function describe(array $parts, int $carnetsCount): string
    {
        $names = [];

        if (in_array(self::CERTIFICATE, $parts, true)) {
            $names[] = 'el certificado de afiliación';
        }

        if (in_array(self::AFFILIATES, $parts, true)) {
            $names[] = 'la relación de afiliados';
        }

        if (in_array(self::CARNETS, $parts, true) && $carnetsCount > 0) {
            $names[] = $carnetsCount === 1 ? 'el carnet de afiliado' : 'los '.$carnetsCount.' carnets de afiliado';
        }

        if ($names === []) {
            return 'el documento';
        }

        $last = array_pop($names);

        return $names === [] ? $last : implode(', ', $names).' y '.$last;
    }

    /**
     * Asunto del correo según lo enviado.
     *
     * @param  list<string>  $parts
     */
    public static function subject(array $parts, int $carnetsCount): string
    {
        if (in_array(self::CERTIFICATE, $parts, true)) {
            return 'Certificado de afiliación';
        }

        if ($parts === [self::AFFILIATES]) {
            return 'Relación de afiliados';
        }

        if ($parts === [self::CARNETS]) {
            return $carnetsCount === 1 ? 'Carnet de afiliado' : 'Carnets de afiliado';
        }

        return 'Documentos de afiliación';
    }
}
