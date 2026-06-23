<?php

declare(strict_types=1);

use App\Services\PublicAiAgent\ChatIndividualQuoteService;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('valida payload de cotizacion individual del chat', function (): void {
    $service = new ChatIndividualQuoteService;

    $result = $service->register([
        'full_name' => '',
        'agent_name' => '',
        'entries' => [],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('obligatorio');
});

it('resuelve rango de edad segun plan_id y edad en age_ranges', function (): void {
    if (! Schema::hasTable('age_ranges')) {
        $this->markTestSkipped('Tabla age_ranges no disponible.');
    }

    $service = new ChatIndividualQuoteService;

    $idealRange = $service->resolveAgeRangeForPlanAndAge(2, 56);
    $especialRange = $service->resolveAgeRangeForPlanAndAge(3, 56);

    if ($idealRange === null || $especialRange === null) {
        $this->markTestSkipped('No hay rangos configurados para los planes 2 y 3.');
    }

    expect($idealRange->plan_id)->toBe(2)
        ->and((string) $idealRange->range)->toBe('46 a 75')
        ->and($especialRange->plan_id)->toBe(3)
        ->and((string) $especialRange->range)->toBe('31 a 65')
        ->and($service->resolveAgeRangeId(3, 56))->toBe((int) $especialRange->id);
});
