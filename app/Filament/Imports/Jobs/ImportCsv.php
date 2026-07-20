<?php

namespace App\Filament\Imports\Jobs;

use Filament\Actions\Imports\Jobs\ImportCsv as FilamentImportCsv;

class ImportCsv extends FilamentImportCsv
{
    /**
     * Enough attempts for Redis/Horizon workers that briefly contend on the same batch.
     */
    public int $tries = 50;

    /**
     * Seconds a single chunk may run before the worker kills it.
     */
    public int $timeout = 600;

    public ?int $maxExceptions = 3;
}
