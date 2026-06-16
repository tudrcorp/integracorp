<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Filament\Business\Resources\Plans\PlanResource;
use App\Models\AgeRange;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Fee;
use App\Support\Plans\PlanCodeGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = filled($data['code'] ?? null)
            ? (string) $data['code']
            : PlanCodeGenerator::next();

        if (! filled($data['type'] ?? null) && filled($data['category'] ?? null)) {
            $data['type'] = $data['category'];
        }

        session()->put('beneficios', $data['package_benefit_ids'] ?? []);
        session()->put('coberturas', $data['general_coverages'] ?? []);

        unset(
            $data['category'],
            $data['is_package'],
            $data['package_benefit_ids'],
            $data['benefits'],
            $data['general_coverages'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $beneficios = session()->get('beneficios', []);
        $coberturas = session()->get('coberturas', []);

        if (is_array($beneficios) && $beneficios !== []) {
            $this->syncPackageBenefits($beneficios);
        }

        if (! is_array($coberturas) || $coberturas === []) {
            return;
        }

        $createdBy = Auth::user()?->name;

        for ($i = 0; $i < count($coberturas); $i++) {
            $coverage = Coverage::find($coberturas[$i]['coverage_id']);
            if ($coverage === null) {
                continue;
            }

            $coverage->plan_id = $this->getRecord()->id;
            $coverage->status = 'ACTIVO';
            if ($createdBy !== null) {
                $coverage->created_by = $createdBy;
            }
            $coverage->save();
        }

        for ($i = 0; $i < count($coberturas); $i++) {
            for ($j = 0; $j < count($coberturas[$i]['age_rates']); $j++) {
                $ageRange = AgeRange::find($coberturas[$i]['age_rates'][$j]['age_range_id']);
                if ($ageRange === null) {
                    continue;
                }

                $ageRange->plan_id = $this->getRecord()->id;
                $ageRange->coverage_id = $coberturas[$i]['coverage_id'];
                $ageRange->fee = $coberturas[$i]['age_rates'][$j]['rate'];
                $ageRange->range = AgeRange::find($coberturas[$i]['age_rates'][$j]['age_range_id'])->range;
                $ageRange->status = 'ACTIVO';
                if ($createdBy !== null) {
                    $ageRange->created_by = $createdBy;
                }
                $ageRange->save();
            }
        }

        for ($i = 0; $i < count($coberturas); $i++) {
            for ($j = 0; $j < count($coberturas[$i]['age_rates']); $j++) {
                $ageRange = AgeRange::find($coberturas[$i]['age_rates'][$j]['age_range_id']);
                $coverage = Coverage::find($coberturas[$i]['coverage_id']);

                if ($ageRange === null || $coverage === null) {
                    continue;
                }

                Fee::create([
                    'age_range_id' => $coberturas[$i]['age_rates'][$j]['age_range_id'],
                    'coverage_id' => $coberturas[$i]['coverage_id'],
                    'price' => $coberturas[$i]['age_rates'][$j]['rate'],
                    'range' => $ageRange->range,
                    'coverage' => $coverage->price,
                    'status' => 'ACTIVO',
                    'created_by' => $createdBy,
                ]);
            }
        }
    }

    /**
     * @param  list<int|string>  $benefitIds
     */
    private function syncPackageBenefits(array $benefitIds): void
    {
        foreach ($benefitIds as $benefitId) {
            BenefitPlan::create([
                'plan_id' => $this->getRecord()->id,
                'benefit_id' => $benefitId,
            ]);
        }
    }
}
