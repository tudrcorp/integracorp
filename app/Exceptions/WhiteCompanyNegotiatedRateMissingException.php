<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class WhiteCompanyNegotiatedRateMissingException extends RuntimeException
{
    public static function make(
        string $companyName,
        ?string $affiliationCode,
        ?int $planId,
        ?int $coverageId,
    ): self {
        $affiliation = filled($affiliationCode) ? $affiliationCode : 'N/A';
        $plan = $planId !== null ? (string) $planId : 'N/A';
        $coverage = $coverageId !== null ? (string) $coverageId : 'sin cobertura';

        return new self(
            "La empresa aliada {$companyName} no tiene precio de venta y neta pactados para esta afiliación. "
            ."Afiliación: {$affiliation} · Plan: {$plan} · Cobertura: {$coverage}. "
            .'Acción: cargar la matriz de negociación en Empresas aliadas. No se realizó ningún cambio.'
        );
    }

    public static function forPerson(
        string $companyName,
        ?string $affiliationCode,
        string $personName,
        ?int $planId,
        ?int $coverageId,
        ?int $age,
    ): self {
        $affiliation = filled($affiliationCode) ? $affiliationCode : 'N/A';
        $plan = $planId !== null ? (string) $planId : 'N/A';
        $coverage = $coverageId !== null ? (string) $coverageId : 'sin cobertura';
        $ageLabel = $age !== null ? (string) $age.' años' : 'edad desconocida';

        return new self(
            "La empresa aliada {$companyName} no tiene neta pactada para {$personName}. "
            ."Afiliación: {$affiliation} · Plan: {$plan} · Cobertura: {$coverage} · {$ageLabel}. "
            .'Acción: cargar esa combinación en la matriz de negociación. No se realizó ningún cambio.'
        );
    }
}
