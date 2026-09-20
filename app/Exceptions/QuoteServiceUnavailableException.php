<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * El microservicio de cotización no respondió o respondió mal.
 *
 * Cubre timeouts, errores de red, 401 por clave inválida y 5xx. Quien la
 * captura debe caer al generador local de la propuesta: nunca dejar al
 * usuario sin documento.
 */
final class QuoteServiceUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
