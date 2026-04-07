<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Schemas\AgencyInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agencia business sin error', function (): void {
    $schema = Schema::make();
    $configured = AgencyInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
