<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use RuntimeException;

/**
 * Regla de la estructura organizacional que se incumplió. El mensaje está escrito
 * para el analista y se muestra tal cual en la notificación.
 */
class OrgStructureException extends RuntimeException {}
