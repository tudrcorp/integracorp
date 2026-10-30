<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Regla única de quién sale en el certificado y los carnets: afiliados ACTIVO o
 * PRE-APROBADA. La usan el Generador de Certificado y el flujo antiguo
 * (`CER-…pdf` y `TAR-…pdf`), para que los dos documentos no se contradigan.
 *
 * Una afiliación que ya no es certificable (EXCLUIDO, ANULADA…) conserva su
 * lista completa: filtrarla dejaría un documento sin personas.
 */
final class CertificatePrintableAffiliates
{
    public const PRINTABLE_STATUSES = ['ACTIVO', 'PRE-APROBADA'];

    public const CERTIFIABLE_AFFILIATION_STATUSES = ['ACTIVA', 'PRE-APROBADA'];

    public static function isPrintableStatus(mixed $status): bool
    {
        return in_array(Str::upper(trim((string) $status)), self::PRINTABLE_STATUSES, true);
    }

    public static function appliesTo(mixed $affiliationStatus): bool
    {
        return in_array(Str::upper(trim((string) $affiliationStatus)), self::CERTIFIABLE_AFFILIATION_STATUSES, true);
    }

    /**
     * Acepta modelos o arreglos. Un arreglo sin `status` viene de la sesión del
     * alta (`session('affiliates')`): esa gente aún no puede estar excluida.
     *
     * @template TRow
     *
     * @param  Collection<int, TRow>|array<int, TRow>  $rows
     * @return ($rows is array ? array<int, TRow> : Collection<int, TRow>)
     */
    public static function filter(Collection|array $rows, mixed $affiliationStatus): Collection|array
    {
        if (! self::appliesTo($affiliationStatus)) {
            return $rows;
        }

        $keep = static fn (mixed $row): bool => is_array($row) && ! array_key_exists('status', $row)
            ? true
            : self::isPrintableStatus(data_get($row, 'status'));

        return is_array($rows)
            ? array_values(array_filter($rows, $keep))
            : $rows->filter($keep)->values();
    }
}
