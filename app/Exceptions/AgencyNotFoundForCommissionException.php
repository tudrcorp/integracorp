<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Agencia referenciada en una afiliación inexistente al calcular comisiones en compensación.
 */
final class AgencyNotFoundForCommissionException extends RuntimeException
{
    public static function make(
        string $codeAgency,
        ?string $affiliationCode,
        int|string|null $paidMembershipId,
    ): self {
        $agency = $codeAgency !== '' ? $codeAgency : '(vacío)';
        $affiliation = filled($affiliationCode) ? $affiliationCode : 'N/A';
        $comprobante = $paidMembershipId !== null && $paidMembershipId !== ''
            ? '#'.$paidMembershipId
            : 'N/A';

        return new self(
            "No se encontró la agencia {$agency} al calcular comisiones. "
            ."Afiliación: {$affiliation} · Comprobante: {$comprobante} · Agencia en afiliación: {$agency}. "
            .'Acción: verificar que esa agencia exista en el catálogo o corregir el code_agency de la afiliación. '
            .'No se realizó ningún cambio.'
        );
    }
}
