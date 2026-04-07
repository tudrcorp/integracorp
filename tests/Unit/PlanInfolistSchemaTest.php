<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Plans\Schemas\PlanInfolist;
use Filament\Schemas\Schema;

it('configura el infolist del plan sin error', function (): void {
    $schema = Schema::make();
    $configured = PlanInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
