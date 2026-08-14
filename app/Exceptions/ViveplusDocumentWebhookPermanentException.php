<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class ViveplusDocumentWebhookPermanentException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|list<string>|string|null  $errors
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly mixed $errors = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
