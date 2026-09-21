<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use RuntimeException;

final class ClinicalEntitlementException extends RuntimeException
{
    public static function planIncomplete(string $message): self
    {
        return new self($message);
    }

    public static function exhausted(string $message): self
    {
        return new self($message);
    }

    public static function overrideRequired(string $message): self
    {
        return new self($message);
    }

    public static function unauthorized(string $message): self
    {
        return new self($message);
    }
}
