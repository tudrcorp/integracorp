<?php

declare(strict_types=1);

namespace App\Support\Errors;

use RuntimeException;

/**
 * Falla esperada al reportar un error (reporte en curso, datos vencidos): su
 * mensaje está en español y se le muestra tal cual al usuario.
 */
final class ErrorReportException extends RuntimeException {}
