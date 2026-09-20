<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * La cotización tiene un rango de edad sin todas sus coberturas.
 *
 * Ocurre en cotizaciones antiguas cuyo plan ganó coberturas después. Dibujar
 * esas casillas como «0 US$» sería inventarle un precio al cliente, así que el
 * documento lo arma el generador local, que muestra solo lo que existe.
 */
final class IncompleteQuoteDetailException extends RuntimeException
{
    public function __construct(
        public readonly int $planId,
        public readonly string $range,
        public readonly int $expected,
        public readonly int $found,
    ) {
        parent::__construct(
            'El plan '.$planId.' tiene el rango «'.$range.'» con '.$found.' de '.$expected.' coberturas.'
        );
    }
}
