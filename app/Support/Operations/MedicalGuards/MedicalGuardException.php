<?php

declare(strict_types=1);

namespace App\Support\Operations\MedicalGuards;

use RuntimeException;

/**
 * Regla de negocio incumplida al cargar una guardia. El mensaje va directo al
 * usuario, en español.
 */
final class MedicalGuardException extends RuntimeException {}
