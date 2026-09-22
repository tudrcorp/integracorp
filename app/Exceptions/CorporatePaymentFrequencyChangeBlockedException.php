<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * La afiliación no admite cambiar la frecuencia de pago en su estado actual.
 *
 * No es un fallo del sistema: el mensaje explica al analista qué resolver
 * antes de intentarlo de nuevo, y no se aplica ningún cambio.
 */
final class CorporatePaymentFrequencyChangeBlockedException extends RuntimeException {}
