<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Schemas\AgencyForm;
use Filament\Schemas\Schema;

it('configura el formulario de agencia business sin error', function (): void {
    $schema = Schema::make();
    $configured = AgencyForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('usa pestañas en el formulario de agencia', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php';
    $source = file_get_contents($path);

    expect($source)->toContain("Tabs::make('agencyFormTabs')");
    expect($source)->toContain('Tab::make(');
});
