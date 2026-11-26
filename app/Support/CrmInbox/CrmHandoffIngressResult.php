<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

final class CrmHandoffIngressResult
{
    public const ACCEPTED = 'accepted';

    public const DUPLICATE = 'duplicate';

    public const UNAUTHORIZED = 'unauthorized';

    public const INVALID = 'invalid';

    public const UNAVAILABLE = 'unavailable';

    public const TOO_LARGE = 'too_large';

    public const RATE_LIMITED = 'rate_limited';

    private function __construct(
        public string $status,
        public ?string $reason = null,
    ) {}

    public static function accepted(): self
    {
        return new self(self::ACCEPTED);
    }

    public static function duplicate(): self
    {
        return new self(self::DUPLICATE);
    }

    public static function unauthorized(string $reason): self
    {
        return new self(self::UNAUTHORIZED, $reason);
    }

    public static function invalid(string $reason): self
    {
        return new self(self::INVALID, $reason);
    }

    public static function unavailable(): self
    {
        return new self(self::UNAVAILABLE);
    }

    public static function tooLarge(): self
    {
        return new self(self::TOO_LARGE);
    }

    public static function rateLimited(): self
    {
        return new self(self::RATE_LIMITED);
    }

    public function httpStatus(): int
    {
        return match ($this->status) {
            self::ACCEPTED, self::DUPLICATE => 202,
            self::UNAUTHORIZED => 401,
            self::INVALID => 422,
            self::TOO_LARGE => 413,
            self::RATE_LIMITED => 429,
            default => 503,
        };
    }

    /**
     * @return array{ok: bool, accepted?: bool, duplicate?: bool, error?: string, reason?: string}
     */
    public function body(): array
    {
        if ($this->status === self::ACCEPTED) {
            return ['ok' => true, 'accepted' => true];
        }

        if ($this->status === self::DUPLICATE) {
            return ['ok' => true, 'accepted' => true, 'duplicate' => true];
        }

        $body = [
            'ok' => false,
            'error' => $this->status,
        ];

        if ($this->reason !== null && $this->reason !== '') {
            $body['reason'] = $this->reason;
        }

        return $body;
    }
}
