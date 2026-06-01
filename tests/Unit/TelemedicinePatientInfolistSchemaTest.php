<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePatients\Schemas\TelemedicinePatientInfolist as OperationsTelemedicinePatientInfolist;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\Schemas\TelemedicinePatientInfolist as TelemedicinaTelemedicinePatientInfolist;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

it('configura el infolist de paciente de telemedicina sin error', function (): void {
    $schema = Schema::make();
    $configured = OperationsTelemedicinePatientInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);

    $components = $configured->getComponents();
    expect($components)->toHaveCount(1)
        ->and($components[0])->toBeInstanceOf(Tabs::class);
});

it('configura el infolist de paciente del panel telemedicina sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicinaTelemedicinePatientInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);

    $components = $configured->getComponents();
    expect($components)->toHaveCount(1)
        ->and($components[0])->toBeInstanceOf(Tabs::class);
});

it('aplica estilos visuales tipo AgentForm master en infolist de paciente', function (string $path): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

    expect($contents)
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain("Tabs::make('telemedicinePatientInfolistTabs')")
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("'class' => self::SECTION_CARD");
})->with([
    'operations' => 'app/Filament/Operations/Resources/TelemedicinePatients/Schemas/TelemedicinePatientInfolist.php',
    'telemedicina' => 'app/Filament/Telemedicina/Resources/TelemedicinePatients/Schemas/TelemedicinePatientInfolist.php',
]);

it('oculta la pestaña de beneficios del plan para médicos en contexto ATENMEDI', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/Schemas/TelemedicinePatientInfolist.php');

    expect($contents)
        ->toContain('shouldHidePlanBenefitsTab')
        ->toContain('TelemedicineCaseFilamentListQuery::userIsInAtenmediTelemedicinaContext(Auth::user())');
});
