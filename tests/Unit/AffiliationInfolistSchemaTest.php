<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de afiliación individual sin error', function (): void {
    $schema = Schema::make();
    $configured = AffiliationInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
