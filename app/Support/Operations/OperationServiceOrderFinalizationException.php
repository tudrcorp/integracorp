<?php

declare(strict_types=1);

namespace App\Support\Operations;

use RuntimeException;

/**
 * La orden no pudo finalizarse por una regla de negocio (ya no está en gestión,
 * caducó, sin documentos). El mensaje es para el analista: se muestra tal cual.
 */
final class OperationServiceOrderFinalizationException extends RuntimeException {}
