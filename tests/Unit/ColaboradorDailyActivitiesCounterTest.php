<?php

declare(strict_types=1);

use App\Support\IndicadoresDeDesempeno\ColaboradorDailyActivitiesCounter;

it('define umbrales de desempeño diario según la escala del medidor', function (): void {
    expect(ColaboradorDailyActivitiesCounter::performanceMeta(9)['level'])->toBe('bajo')
        ->and(ColaboradorDailyActivitiesCounter::performanceMeta(9)['label'])->toBe('Bajo desempeño')
        ->and(ColaboradorDailyActivitiesCounter::performanceMeta(10)['level'])->toBe('medio')
        ->and(ColaboradorDailyActivitiesCounter::performanceMeta(15)['level'])->toBe('medio')
        ->and(ColaboradorDailyActivitiesCounter::performanceMeta(15)['color'])->toBe('#ffcc00')
        ->and(ColaboradorDailyActivitiesCounter::performanceMeta(20)['level'])->toBe('medio')
        ->and(ColaboradorDailyActivitiesCounter::performanceMeta(20)['color'])->toBe('#ffcc00')
        ->and(ColaboradorDailyActivitiesCounter::performanceMeta(21)['level'])->toBe('alto')
        ->and(ColaboradorDailyActivitiesCounter::performanceMeta(21)['color'])->toBe('#34c759')
        ->and(ColaboradorDailyActivitiesCounter::performanceMeta(25)['label'])->toBe('Alto desempeño');
});

it('calcula la rotación de la aguja dentro del rango del velocímetro', function (): void {
    expect(ColaboradorDailyActivitiesCounter::needleRotationDegrees(0))->toBe(-90.0)
        ->and(ColaboradorDailyActivitiesCounter::needleRotationDegrees(15))->toBe(0.0)
        ->and(ColaboradorDailyActivitiesCounter::needleRotationDegrees(30))->toBe(90.0)
        ->and(ColaboradorDailyActivitiesCounter::needleRotationDegrees(40))->toBe(90.0);
});

it('registra el widget de velocímetro interactivo en indicadores de desempeño', function (): void {
    $widgetPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Widgets/ColaboradorActivitiesSpeedometerWidget.php';
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Pages/ListIndicadoresDeDesempeno.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/operations/colaborador-activities-speedometer.blade.php';
    $counterPath = dirname(__DIR__, 2).'/app/Support/IndicadoresDeDesempeno/ColaboradorDailyActivitiesCounter.php';

    expect(file_exists($widgetPath))->toBeTrue()
        ->and(file_exists($viewPath))->toBeTrue()
        ->and(file_exists($counterPath))->toBeTrue();

    expect(file_get_contents($widgetPath))->toContain('ColaboradorDailyActivitiesCounter::breakdownForCollaboratorOnDate')
        ->toContain("protected string \$view = 'filament.operations.colaborador-activities-speedometer';")
        ->toContain('public ?string $selectedCollaborator = null')
        ->toContain('public ?string $activityDate = null');

    expect(file_get_contents($pagePath))->toContain('ColaboradorActivitiesSpeedometerWidget::class');

    expect(file_get_contents($viewPath))->toContain('wire:model.live="selectedCollaborator"')
        ->toContain('wire:model.live="activityDate"')
        ->toContain('displayRotation')
        ->toContain(':transform="`rotate(${displayRotation})`"')
        ->toContain('wire:loading.class="opacity-60"')
        ->toContain('entre 10 y 20 actividades')
        ->not->toContain('#ff9500');
});
