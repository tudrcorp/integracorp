<?php

declare(strict_types=1);

namespace App\Support\Viveplus;

use Illuminate\Http\Client\Response;

final class ViveplusDocumentWebhookResult
{
    /**
     * @param  array<string, mixed>|list<string>|string|null  $errors
     */
    public function __construct(
        public int $status,
        public bool $accepted,
        public bool $retryable,
        public mixed $errors = null,
        public string $body = '',
    ) {}

    public static function fromResponse(Response $response): self
    {
        $status = $response->status();

        return new self(
            status: $status,
            accepted: in_array($status, [201, 409], true),
            retryable: $status >= 500,
            errors: $response->json('errors'),
            body: $response->body(),
        );
    }

    public function isDuplicateOrStale(): bool
    {
        return $this->status === 409;
    }
}
