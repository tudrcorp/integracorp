<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Error al preparar o enviar el correo de asignación de ticket de soporte (helpdesk).
 */
final class HelpdeskTicketMailException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function ticketNotFound(int $ticketId): self
    {
        return new self(
            "No existe un ticket de soporte con el identificador {$ticketId}.",
            ['help_desk_id' => $ticketId],
        );
    }

    public static function ticketNotPersisted(): self
    {
        return new self(
            'El ticket de soporte debe estar guardado en base de datos antes de enviar el correo.',
            [],
        );
    }

    public static function contentBuildFailed(Throwable $previous): self
    {
        return new self(
            'No se pudo construir el contenido HTML del correo del ticket: '.$previous->getMessage(),
            [],
            $previous,
        );
    }
}
