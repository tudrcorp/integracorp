<?php

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Models\Fee;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Business\Resources\Plans\PlanResource;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        session()->put('edades', $data['edades']);
        session()->put('coberturas', $data['coberturas']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // dd($this->getRecord()->toArray(), session()->all());
        try {

            //...Cargamos las coberturas en la tabla de coverages
            $coberturas = session()->get('coberturas');

            for ($i = 0; $i < count($coberturas); $i++) {
                $this->getRecord()->coverages()->create([
                    'plan_id'       => $this->getRecord()->id,
                    'price'         => $coberturas[$i]['price_coverages'],
                    'status'        => 'ACTIVO',
                    'created_by'    => auth()->user()->name
                ]);
            }

            //...Cargamos las edades en la tabla de ages
            $edades = session()->get('edades');
            // dd($edades);
            for ($i = 0; $i < count($edades); $i++) {
                $this->getRecord()->ageRanges()->create([
                    'plan_id'       => $this->getRecord()->id,
                    'range'         => $edades[$i]['range'],
                    'status'        => 'ACTIVO',
                    'created_by'    => auth()->user()->name,
                    'age_init'      => $edades[$i]['age_init'],
                    'age_end'       => $edades[$i]['age_end'],
                ]);
            }

            $ageRange = $this->getRecord()->ageRanges()->get()->toArray();
            $coverages = $this->getRecord()->coverages()->get()->toArray();

            //..Cargamos la tabla de fees
            for ($i = 0; $i < count($ageRange); $i++) {
                $fee = new Fee();
                $fee->age_range_id  = $ageRange[$i]['id'];
                $fee->coverage_id   = $coverages[$i]['id'];
                $fee->price         = $coberturas[$i]['price_fees'];
                $fee->status        = 'ACTIVO';
                $fee->created_by    = auth()->user()->name;
                $fee->range         = $edades[$i]['range'];
                $fee->coverage      = $coberturas[$i]['price_coverages'];
                $fee->save();
            }
            

            
        } catch (\Throwable $th) {
            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }
}