<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use RuntimeException;

/**
 * Otro usuario guardó el uso clínico del plan entre que el analista abrió la
 * pantalla y pulsó «Guardar». No se pisa: se pide recargar.
 */
final class PlanClinicalUsageConflictException extends RuntimeException {}
