<?php

declare(strict_types=1);

namespace App\Support;

final class HelpdeskWorkGroupValidationResult
{
    /**
     * @param  list<int>  $colaboradorIds
     * @param  list<array{id: int, name: string, telefono_corporativo: string|null}>  $members
     */
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $errorTitle = null,
        public readonly ?string $errorBody = null,
        public readonly string $name = '',
        public readonly string $status = 'ACTIVO',
        public readonly int $ticketQuota = 0,
        public readonly array $colaboradorIds = [],
        public readonly array $members = [],
    ) {}

    public static function failure(string $title, string $body): self
    {
        return new self(valid: false, errorTitle: $title, errorBody: $body);
    }

    /**
     * @param  list<int>  $colaboradorIds
     * @param  list<array{id: int, name: string, telefono_corporativo: string|null}>  $members
     */
    public static function success(
        string $name,
        string $status,
        int $ticketQuota,
        array $colaboradorIds,
        array $members,
    ): self {
        return new self(
            valid: true,
            name: $name,
            status: $status,
            ticketQuota: $ticketQuota,
            colaboradorIds: $colaboradorIds,
            members: $members,
        );
    }
}
