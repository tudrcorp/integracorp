<?php

declare(strict_types=1);

namespace App\Support\Operations;

use RuntimeException;

/**
 * Error de negocio del registro RETAIL: el mensaje va tal cual al analista, y
 * `errors` indica qué campo del formulario lo causó.
 */
final class RetailServiceRegistrationException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors  campo => mensaje
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public static function field(string $field, string $message): self
    {
        return new self($message, [$field => $message]);
    }
}
