<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Una afiliación individual no puede cambiar de plan ahora. El mensaje se muestra tal cual
 * al analista: dice el motivo y qué hacer para destrabarlo.
 */
class IndividualPlanChangeBlockedException extends RuntimeException {}
