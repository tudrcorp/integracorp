<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Support\ProspectAgents\ProspectCollaboratorLabels;

it('expone conteos por colaborador y por cambios de estatus', function (): void {
    expect(method_exists(ProspectCollaboratorLabels::class, 'prospectCountsOrdered'))->toBeTrue()
        ->and(method_exists(ProspectCollaboratorLabels::class, 'statusChangeCountsForCollaborator'))->toBeTrue();
});

it('filtra por actualizaciones del colaborador y altas sin updated_by', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Support/ProspectAgents/ProspectCollaboratorLabels.php');

    expect($src)->not->toBeFalse()
        ->and($src)->toContain("->where('updated_by', \$collaborator)")
        ->and($src)->toContain("->where('created_by', \$collaborator)")
        ->and($src)->toContain('whereBetween(\'updated_at\'')
        ->and($src)->toContain('whereBetween(\'created_at\'')
        ->and($src)->toContain('ProspectAgentLabels::statusOptions()')
        ->and($src)->toContain('ProspectAgentLabels::normalizeStatus');
});

it('siempre incluye los cinco estatus del embudo aunque el conteo sea cero', function (): void {
    $result = ProspectCollaboratorLabels::statusChangeCountsForCollaborator('', 2026, 10);
    $expectedLabels = array_values(ProspectAgentLabels::statusOptions());

    expect($result['labels'])->toBe($expectedLabels)
        ->and($result['counts'])->toHaveCount(count($expectedLabels))
        ->and($result['counts'])->toBe(array_fill(0, count($expectedLabels), 0));

    $src = file_get_contents(dirname(__DIR__, 2).'/app/Support/ProspectAgents/ProspectCollaboratorLabels.php');
    expect($src)->not->toBeFalse()
        ->and($src)->toContain('$labels[] = $label;')
        ->and($src)->toContain('$counts[] = (int) ($totalsByCanonical[$canonical] ?? 0);');
});
