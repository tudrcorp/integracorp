<?php

declare(strict_types=1);

namespace App\Support\Renovations;

/**
 * Autorización explícita para aceptar renovaciones fuera del período de renovación.
 * Solo la construye `EarlyRenovationAcceptance::authorizationFromFormData()`, que
 * valida permiso y motivo.
 */
final readonly class EarlyRenovationAuthorization
{
    public function __construct(
        public string $reason,
        public int $userId,
        public string $userName,
        public ?string $userEmail,
    ) {}
}
