<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use RuntimeException;

final class ReferidorAssignmentException extends RuntimeException
{
    public static function notReferrerAgency(): self
    {
        return new self('Solo una agencia o un agente marcado como referidor puede asignar agencias generales o agentes.');
    }

    public static function agencyNotAssignable(): self
    {
        return new self('Una o más agencias generales no están disponibles para este referidor. Recargue el formulario e intente de nuevo.');
    }

    public static function agentNotAssignable(): self
    {
        return new self('Uno o más agentes o subagentes no están disponibles para este referidor. Recargue el formulario e intente de nuevo.');
    }
}
