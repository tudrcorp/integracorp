<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use DomainException;

/**
 * El almuerzo no se puede iniciar ahora. El mensaje se muestra tal cual al colaborador.
 */
final class WorkBreakException extends DomainException {}
