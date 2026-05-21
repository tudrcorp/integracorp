<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePatients\Schemas\TelemedicinePatientInfolist;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

it('configura el infolist de paciente de telemedicina sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicinePatientInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);

    $components = $configured->getComponents();
    expect($components)->toHaveCount(1)
        ->and($components[0])->toBeInstanceOf(Tabs::class);
});

it('aplica estilos visuales tipo AgentForm master en infolist de paciente', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Schemas/TelemedicinePatientInfolist.php');

    expect($contents)
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain("Tabs::make('telemedicinePatientInfolistTabs')")
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("'class' => self::SECTION_CARD");
});
