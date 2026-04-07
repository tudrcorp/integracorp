<?php

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Filament\Business\Resources\Plans\PlanResource;
use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Fee;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // dd($data);

        // BENEFICIOS
        $beneficios = $data['package_benefit_ids'];
        session()->put('beneficios', $beneficios);

        // COBERTURAS
        $coberturas = $data['general_coverages'];
        session()->put('coberturas', $coberturas);

        return $data;
    }

    protected function beforeCreate(): void
    {
        $beneficios = session()->get('beneficios');
        $coberturas = session()->get('coberturas');
        // dd($coberturas);
    }

    protected function afterCreate(): void
    {

        // Guardamos los beneficios y coberturas en la tabla que asocia los beneficios con el plan
        $beneficios = session()->get('beneficios');

        for ($i = 0; $i < count($beneficios); $i++) {
            BenefitPlan::create([
                'plan_id' => $this->getRecord()->id,
                'benefit_id' => $beneficios[$i],
                'description' => Benefit::find($beneficios[$i])->description,
                'created_by' => auth()->user()->name,
            ]);
        }

        // ACTUALIZO las coberturas en la tabla de cobertura con el ID del plan
        $coberturas = session()->get('coberturas');
        for ($i = 0; $i < count($coberturas); $i++) {
            $coverage = Coverage::find($coberturas[$i]['coverage_id']);
            $coverage->plan_id = $this->getRecord()->id;
            $coverage->status = 'ACTIVO';
            $coverage->created_by = auth()->user()->name;
            $coverage->save();
        }

        // Actualizo la tabla de rangos de edad con el ID del plan y el ID de la cobertura
        $coberturas = session()->get('coberturas');
        for ($i = 0; $i < count($coberturas); $i++) {
            for ($j = 0; $j < count($coberturas[$i]['age_rates']); $j++) {
                $ageRange = AgeRange::find($coberturas[$i]['age_rates'][$j]['age_range_id']);
                $ageRange->plan_id = $this->getRecord()->id;
                $ageRange->coverage_id = $coberturas[$i]['coverage_id'];
                $ageRange->fee = $coberturas[$i]['age_rates'][$j]['rate'];
                $ageRange->range = AgeRange::find($coberturas[$i]['age_rates'][$j]['age_range_id'])->range;
                $ageRange->status = 'ACTIVO';
                $ageRange->created_by = auth()->user()->name;
                $ageRange->save();
            }
        }

        // Creo el registro en la tabla de fees con el age_range_id y el coverage_id y el precio
        $coberturas = session()->get('coberturas');
        for ($i = 0; $i < count($coberturas); $i++) {
            for ($j = 0; $j < count($coberturas[$i]['age_rates']); $j++) {
                $fee = Fee::create([
                    'age_range_id' => $coberturas[$i]['age_rates'][$j]['age_range_id'],
                    'coverage_id' => $coberturas[$i]['coverage_id'],
                    'price' => $coberturas[$i]['age_rates'][$j]['rate'],
                    'range' => AgeRange::find($coberturas[$i]['age_rates'][$j]['age_range_id'])->range,
                    'coverage' => Coverage::find($coberturas[$i]['coverage_id'])->price,
                    'status' => 'ACTIVO',
                    'created_by' => auth()->user()->name,
                ]);
            }
        }
    }
}
