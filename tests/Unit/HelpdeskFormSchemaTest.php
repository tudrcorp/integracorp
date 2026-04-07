<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Helpdesks\Schemas\HelpdeskForm;
use Filament\Schemas\Schema;

it('configura el schema del formulario helpdesk sin error', function (): void {
    $schema = Schema::make();
    $configured = HelpdeskForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
