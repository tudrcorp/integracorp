<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineCases\Schemas\TelemedicineCaseInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de caso de telemedicina sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicineCaseInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
