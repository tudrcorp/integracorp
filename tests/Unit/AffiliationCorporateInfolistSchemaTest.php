<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de afiliación corporativa sin error', function (): void {
    $schema = Schema::make();
    $configured = AffiliationCorporateInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
