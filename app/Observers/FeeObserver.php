<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Fee;
use App\Support\TuDrQuote\QuoteFeeMatrix;

/**
 * El portal es el maestro de tarifas: si una cambia, la matriz que se envía al
 * microservicio de cotización deja de ser válida de inmediato.
 */
class FeeObserver
{
    public function saved(Fee $fee): void
    {
        QuoteFeeMatrix::flush();
    }

    public function deleted(Fee $fee): void
    {
        QuoteFeeMatrix::flush();
    }
}
