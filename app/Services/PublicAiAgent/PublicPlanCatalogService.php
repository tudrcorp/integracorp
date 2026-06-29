<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Http\Controllers\BenefitController;
use App\Models\BenefitPlan;
use App\Models\Plan;
use Illuminate\Support\Facades\Schema;

class PublicPlanCatalogService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlanCatalog(): array
    {
        $planColumns = ['id', 'description', 'type', 'status'];
        if (Schema::hasColumn('plans', 'code')) {
            $planColumns[] = 'code';
        }

        $plans = Plan::query()
            ->where('type', 'BASICO')
            ->with([
                'coverages' => fn ($query) => $query
                    ->select('id', 'plan_id', 'price')
                    ->orderBy('price'),
                'ageRanges' => fn ($query) => $query
                    ->select('id', 'plan_id', 'coverage_id', 'range', 'age_init', 'age_end')
                    ->orderBy('age_init')
                    ->orderBy('id'),
                'ageRanges.fees' => fn ($query) => $query
                    ->select('id', 'age_range_id', 'coverage_id', 'price', 'range')
                    ->orderBy('price'),
                'ageRanges.fees.coverage:id,plan_id,price',
            ])
            ->orderBy('id')
            ->get($planColumns);

        return $plans->map(function (Plan $plan): array {
            return [
                'plan_id' => (int) $plan->id,
                'code' => (string) ($plan->code ?? ''),
                'description' => (string) $plan->description,
                'status' => (string) $plan->status,
                'coverages' => $plan->coverages->map(fn ($coverage): array => [
                    'coverage_id' => (int) $coverage->id,
                    'price' => (float) $coverage->price,
                ])->values()->all(),
                'age_ranges' => $plan->ageRanges->map(fn ($ageRange): array => [
                    'age_range_id' => (int) $ageRange->id,
                    'range' => (string) ($ageRange->range ?? ''),
                    'age_init' => $ageRange->age_init !== null ? (int) $ageRange->age_init : null,
                    'age_end' => $ageRange->age_end !== null ? (int) $ageRange->age_end : null,
                    'coverage_id' => $ageRange->coverage_id !== null ? (int) $ageRange->coverage_id : null,
                    'fees' => $ageRange->fees->map(fn ($fee): array => [
                        'fee_id' => (int) $fee->id,
                        'price' => (float) $fee->price,
                        'coverage_id' => $fee->coverage_id !== null ? (int) $fee->coverage_id : null,
                        'coverage_price' => $fee->coverage?->price !== null ? (float) $fee->coverage?->price : null,
                    ])->values()->all(),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlanBenefits(int $planId): array
    {
        $benefits = BenefitPlan::query()
            ->where('plan_id', $planId)
            ->orderBy('id')
            ->get(['benefit_id', 'description']);

        return $benefits->map(fn (BenefitPlan $benefit): array => [
            'benefit_id' => (int) $benefit->benefit_id,
            'description' => (string) $benefit->description,
        ])->values()->all();
    }

    /**
     * @return array{
     *   coverages: array<int, array{id: int, price: float}>,
     *   benefits: array<int, array{benefit_id: int, description: string}>,
     *   matrix: array<string, mixed>
     * }
     */
    public function getPlanBenefitsMatrix(int $planId): array
    {
        $table = BenefitController::getTableBenefit($planId);
        if (! is_array($table)) {
            $table = [];
        }

        return [
            'coverages' => collect($table['coverages'] ?? [])
                ->map(fn ($coverage): array => [
                    'id' => (int) $coverage->id,
                    'price' => (float) $coverage->price,
                ])->values()->all(),
            'benefits' => collect($table['benefits'] ?? [])
                ->map(fn ($benefit): array => [
                    'benefit_id' => (int) $benefit->benefit_id,
                    'description' => (string) $benefit->description,
                ])->values()->all(),
            'matrix' => (array) ($table['matrix'] ?? []),
        ];
    }

    public function formatCoverageAmountForChat(float $price): string
    {
        return 'US$'.number_format($price, 0, ',', '.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $coverages
     */
    public function formatCoverageListForChat(array $coverages): string
    {
        $amounts = collect($coverages)
            ->map(fn (array $coverage): string => $this->formatCoverageAmountForChat((float) ($coverage['price'] ?? 0)))
            ->unique()
            ->values();

        if ($amounts->isEmpty()) {
            return 'consultar con un asesor';
        }

        return $amounts->implode(', ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $plans
     */
    public function buildPlanCatalogChatSummary(array $plans): string
    {
        if ($plans === []) {
            return 'Planes disponibles: Inicial (ID 1), Ideal (ID 2), Especial (ID 3). También puedes escribir inicial, ideal o especial.';
        }

        $lines = collect($plans)->map(function (array $plan): string {
            $planId = (int) ($plan['plan_id'] ?? 0);
            $description = trim((string) ($plan['description'] ?? ''));
            $label = $description !== '' ? mb_strtoupper($description) : "Plan {$planId}";
            $coverages = is_array($plan['coverages'] ?? null) ? $plan['coverages'] : [];

            return sprintf(
                "• Plan %d — %s\n  Coberturas: %s",
                $planId,
                $label,
                $this->formatCoverageListForChat($coverages),
            );
        });

        return 'Planes disponibles:'."\n\n"
            .$lines->implode("\n\n")
            ."\n\n¿Deseas cotizar o necesitas conocer los beneficios del plan a cotizar?"
            ."\n• Escribe «cotizar» para iniciar una cotización."
            ."\n• Escribe el ID del plan seguido de «beneficios» (ejemplo: 1 beneficios, 2 beneficios, 3 beneficios).";
    }

    /**
     * @param  array<string, mixed>|null  $selectedPlan
     */
    public function buildCoverageChatSummaryForPlan(?array $selectedPlan): string
    {
        if ($selectedPlan === null) {
            return 'No encontré coberturas para ese plan. Indica el ID de cobertura numérico.';
        }

        $coverages = is_array($selectedPlan['coverages'] ?? null) ? $selectedPlan['coverages'] : [];

        $lines = collect($coverages)->map(function (array $coverage): string {
            $coverageId = (int) ($coverage['coverage_id'] ?? 0);
            $amount = $this->formatCoverageAmountForChat((float) ($coverage['price'] ?? 0));

            return sprintf('• Cobertura %d — %s', $coverageId, $amount);
        });

        if ($lines->isEmpty()) {
            return 'No hay coberturas listadas para este plan. Indica el ID de cobertura numérico.';
        }

        return 'Coberturas del plan seleccionado:'."\n\n".$lines->implode("\n");
    }

    public function buildOurPlansOverviewMessage(PublicPlanBenefitsService $benefitsService, ?array $plans = null): string
    {
        $plans ??= $this->getPlanCatalog();

        if ($plans === []) {
            $plans = $this->fallbackPlanCatalog();
        }

        $quickLines = collect($plans)->map(function (array $plan) use ($benefitsService): string {
            $planId = (int) ($plan['plan_id'] ?? 0);
            $title = $benefitsService->planTitles()[$planId] ?? ('Plan '.$planId);
            $ageSummary = $this->summarizeAgeRangesForPlan($plan, $planId);
            $coverageSummary = $this->summarizeCoveragesForPlan($plan, $planId);

            return sprintf(
                '• %d · %s — %s — %s',
                $planId,
                $title,
                $ageSummary,
                $coverageSummary,
            );
        });

        $detailSections = collect($plans)->map(function (array $plan) use ($benefitsService): string {
            return $this->buildPlanDetailSection($plan, $benefitsService);
        });

        return "VISTA RÁPIDA\n"
            .$quickLines->implode("\n")
            ."\n\n"
            .$detailSections->implode("\n\n");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackPlanCatalog(): array
    {
        return [
            [
                'plan_id' => 1,
                'description' => 'Plan Inicial',
                'coverages' => [],
                'age_ranges' => [],
            ],
            [
                'plan_id' => 2,
                'description' => 'Plan Ideal',
                'coverages' => [
                    ['coverage_id' => 2, 'price' => 1000.0],
                    ['coverage_id' => 6, 'price' => 10000.0],
                ],
                'age_ranges' => [],
            ],
            [
                'plan_id' => 3,
                'description' => 'Plan Especial',
                'coverages' => [
                    ['coverage_id' => 10, 'price' => 50000.0],
                ],
                'age_ranges' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function buildPlanDetailSection(array $plan, PublicPlanBenefitsService $benefitsService): string
    {
        $planId = (int) ($plan['plan_id'] ?? 0);
        $title = mb_strtoupper($benefitsService->planTitles()[$planId] ?? ('Plan '.$planId));

        $ageLines = $this->formatAgeRangesForPlan($plan, $planId);
        $coverageLines = $this->formatCoveragesForPlan($plan, $planId);
        $benefitLines = collect($benefitsService->benefitBulletLines($planId))
            ->map(fn (string $line): string => '• '.$line)
            ->implode("\n");

        return implode("\n", [
            '━━━━━━━━━━━━━━━━━━━━',
            sprintf('PLAN %d — %s', $planId, $title),
            '━━━━━━━━━━━━━━━━━━━━',
            '',
            'Rango de edad',
            $ageLines,
            '',
            'Coberturas disponibles',
            $coverageLines,
            '',
            'Beneficios — '.$benefitsService->benefitCategoryTitle($planId),
            $benefitLines,
        ]);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function summarizeAgeRangesForPlan(array $plan, int $planId): string
    {
        $ageRanges = is_array($plan['age_ranges'] ?? null) ? $plan['age_ranges'] : [];

        if ($ageRanges === []) {
            return 'Edades: '.$this->defaultAgeRangeDescription($planId);
        }

        $firstRange = trim((string) ($ageRanges[0]['range'] ?? ''));

        if ($firstRange !== '') {
            return 'Edades: '.$firstRange.' años';
        }

        return 'Edades: '.$this->defaultAgeRangeDescription($planId);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function summarizeCoveragesForPlan(array $plan, int $planId): string
    {
        $coverages = is_array($plan['coverages'] ?? null) ? $plan['coverages'] : [];

        if ($coverages === []) {
            return $planId === 1
                ? 'Asistencia en sitio'
                : 'Coberturas: consultar con un asesor';
        }

        return 'Coberturas: '.$this->formatCoverageListForChat($coverages);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function formatAgeRangesForPlan(array $plan, int $planId): string
    {
        $ageRanges = is_array($plan['age_ranges'] ?? null) ? $plan['age_ranges'] : [];

        if ($ageRanges === []) {
            return '• '.$this->defaultAgeRangeDescription($planId);
        }

        $lines = collect($ageRanges)
            ->map(function (array $ageRange): ?string {
                $range = trim((string) ($ageRange['range'] ?? ''));

                if ($range !== '') {
                    return '• '.$range.' años';
                }

                $ageInit = $ageRange['age_init'] ?? null;
                $ageEnd = $ageRange['age_end'] ?? null;

                if ($ageInit !== null && $ageEnd !== null) {
                    return '• '.$ageInit.' a '.$ageEnd.' años';
                }

                if ($ageInit !== null) {
                    return '• Desde '.$ageInit.' años';
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($lines->isEmpty()) {
            return '• '.$this->defaultAgeRangeDescription($planId);
        }

        return $lines->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function formatCoveragesForPlan(array $plan, int $planId): string
    {
        $coverages = is_array($plan['coverages'] ?? null) ? $plan['coverages'] : [];

        if ($coverages === []) {
            return $planId === 1
                ? '• Asistencia médica en sitio (sin monto de cobertura monetaria asociado)'
                : '• Consultar montos disponibles con un asesor comercial';
        }

        return collect($coverages)
            ->map(fn (array $coverage): string => '• '.$this->formatCoverageAmountForChat((float) ($coverage['price'] ?? 0)))
            ->unique()
            ->implode("\n");
    }

    private function defaultAgeRangeDescription(int $planId): string
    {
        return match ($planId) {
            1 => '0 a +99 años (ilimitado)',
            2, 3 => '0 a 85 años',
            default => 'Consultar con un asesor',
        };
    }
}
