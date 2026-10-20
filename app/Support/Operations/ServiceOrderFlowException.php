<?php

declare(strict_types=1);

namespace App\Support\Operations;

use RuntimeException;

/**
 * Una regla del flujo cotización → orden impide la acción. El mensaje va tal cual
 * al analista y a la traza, así que se escribe en español y dice qué hacer.
 */
final class ServiceOrderFlowException extends RuntimeException {}
