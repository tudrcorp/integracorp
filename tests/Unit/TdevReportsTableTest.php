<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\TdevReports\Tables\TdevReportsTable;

it('expone el configurador de la tabla de reportes TDEV', function () {
    expect(class_exists(TdevReportsTable::class))->toBeTrue()
        ->and(method_exists(TdevReportsTable::class, 'configure'))->toBeTrue();
});
