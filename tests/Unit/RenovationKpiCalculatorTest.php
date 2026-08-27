<?php

declare(strict_types=1);

use App\Support\Renovations\RenovationKpiAcceptorRow;
use App\Support\Renovations\RenovationKpiCalculator;
use App\Support\Renovations\RenovationKpiSnapshot;

it('calcula retención y abandono sobre aceptadas más retraso', function (): void {
    expect(RenovationKpiCalculator::retentionRate(9, 10))
        ->toEqualWithDelta(9 / 19, 0.0001)
        ->and(RenovationKpiCalculator::churnRate(9, 10))
        ->toEqualWithDelta(10 / 19, 0.0001)
        ->and(RenovationKpiCalculator::retentionRate(9, 0))
        ->toBe(1.0)
        ->and(RenovationKpiCalculator::churnRate(9, 0))
        ->toBe(0.0);
});

it('no inventa retención ni abandono si no hay aceptadas ni retraso', function (): void {
    expect(RenovationKpiCalculator::retentionRate(0, 0))->toBeNull()
        ->and(RenovationKpiCalculator::churnRate(0, 0))->toBeNull()
        ->and(RenovationKpiSnapshot::formatRate(null))->toBe('—')
        ->and(RenovationKpiSnapshot::formatRate(9 / 19))->toBe('47 %');
});

it('formatea prima, anticipación y etiquetas de empresa o póliza', function (): void {
    $individual = new RenovationKpiSnapshot(
        periodLabel: 'Agosto 2026',
        isCorporate: false,
        acceptedCount: 9,
        retainedPremium: 5890.0,
        avgAnticipationDays: 22.8889,
        overdueOpenCount: 10,
        inWindowOpenCount: 11,
        retentionRate: 9 / 19,
        churnRate: 10 / 19,
        acceptors: [
            new RenovationKpiAcceptorRow('Gustavo Camacho', 7, 5460.0, 22.5714),
        ],
    );

    $corporate = new RenovationKpiSnapshot(
        periodLabel: 'Agosto 2026',
        isCorporate: true,
        acceptedCount: 0,
        retainedPremium: 0.0,
        avgAnticipationDays: null,
        overdueOpenCount: 0,
        inWindowOpenCount: 0,
        retentionRate: null,
        churnRate: null,
        acceptors: [],
    );

    expect($individual->acceptedLabel())->toBe('Pólizas aceptadas')
        ->and($individual->unitLabel())->toBe('Pólizas')
        ->and($individual->formattedAcceptedCount())->toBe('9')
        ->and($individual->formattedRetention())->toBe('47 %')
        ->and($individual->formattedChurn())->toBe('53 %')
        ->and($individual->formattedPremium())->toBe('US$ 5.890,00')
        ->and($individual->formattedAnticipation())->toBe('23 días')
        ->and($corporate->acceptedLabel())->toBe('Empresas aceptadas')
        ->and($corporate->unitLabel())->toBe('Empresas')
        ->and($corporate->formattedRetention())->toBe('—')
        ->and($corporate->formattedAnticipation())->toBe('—')
        ->and($corporate->formattedPremium())->toBe('US$ 0,00');
});

it('consulta aceptadas del mes y cola abierta sin mezclar cotizaciones', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Renovations/RenovationKpiCalculator.php');

    expect($source)
        ->toContain('startOfMonth')
        ->toContain("where('accepted_at', '>=', \$from)")
        ->toContain("where('accepted_at', '<', \$to)")
        ->toContain('COUNT(DISTINCT {$entityColumn})')
        ->toContain('affiliation_corporate_id')
        ->toContain("where('remaining_days', '<', 0)")
        ->toContain("where('remaining_days', '>=', 0)")
        ->toContain('STATUS_RENOVATION_PERIOD')
        ->toContain('groupBy(\'accepted_by\')')
        ->not->toContain('DATE_FORMAT')
        ->not->toContain('IndividualQuote')
        ->not->toContain('HelpDeskCsat');
});
