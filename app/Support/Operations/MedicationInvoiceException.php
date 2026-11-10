<?php

declare(strict_types=1);

namespace App\Support\Operations;

use RuntimeException;

/**
 * Regla de negocio incumplida al facturar, pagar o anular una factura de medicamentos.
 * El mensaje está en español y se muestra tal cual al analista.
 */
final class MedicationInvoiceException extends RuntimeException {}
