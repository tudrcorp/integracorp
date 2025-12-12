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

    protected function beforeCreate()
    {
        $coberturas = session()->get('coberturas');
        $edades = session()->get('edades');

            // Validación detallada de cada cobertura
            foreach ($coberturas as $index => $cobertura) {
                // Verifica que el campo 'price_fees' exista y sea numérico
                if (! isset($cobertura['price_fees']) || ! is_numeric($cobertura['price_fees'])) {
                    Notification::make()
                        ->title('ERROR')
                        ->body('La cobertura en la posición ' . ($index + 1) . ' no tiene un precio válido.')
                        ->icon('heroicon-m-tag')
                        ->iconColor('danger')
                        ->danger()
                        ->send();
                    $this->halt();
                }

                if ($cobertura['price_fees'] <= 0) {
                    Notification::make()
                        ->title('ERROR')
                        ->body('El precio de la tarifa anual para el rango de edad debe ser mayor a 0.')
                        ->icon('heroicon-m-tag')
                        ->iconColor('danger')
                        ->danger()
                        ->send();
                    $this->halt();
                }
            }

            foreach ($edades as $index => $edad) {
                if (! isset($edad['range']) || empty($edad['range'])) {
                    Notification::make()
                        ->title('ERROR')
                        ->body('El rango de edad en la posición ' . ($index + 1) . ' no es válido.')
                        ->icon('heroicon-m-tag')
                        ->iconColor('danger')
                        ->danger()
                        ->send();
                    $this->halt();
                }

                //La edad inicial no puede ser mayor a la edad final
                if ($edad['age_init'] >= $edad['age_end']) {
                    Notification::make()
                        ->title('ERROR')
                        ->body('La edad inicial no puede ser mayor o igual a la edad final en la posición ' . ($index + 1) . '.')
                        ->icon('heroicon-m-tag')
                        ->iconColor('danger')
                        ->danger()
                        ->send();
                    $this->halt();
                }

                //la edad inicial no puede ser menor a 0
                if ($edad['age_init'] < 0) {
                    Notification::make()
                        ->title('ERROR')
                        ->body('La edad inicial no puede ser menor a 0 en la posición ' . ($index + 1) . '.')
                        ->icon('heroicon-m-tag')
                        ->iconColor('danger')
                        ->danger()
                        ->send();
                    $this->halt();
                }

                //la edad final no puede ser mayor a 120
                if ($edad['age_end'] > 120) {
                    Notification::make()
                        ->title('ERROR')
                        ->body('La edad final no puede ser mayor a 120 en la posición ' . ($index + 1) . '.')
                        ->icon('heroicon-m-tag')
                        ->iconColor('danger')
                        ->danger()
                        ->send();
                    $this->halt();
                }
            }
    }

    protected function afterCreate(): void
    {
        // dd($this->getRecord()->toArray(), session()->all());
        try {

            //...Cargamos las coberturas en la tabla de coverages
            $coberturas = session()->get('coberturas');

            for ($i = 0; $i < count($coberturas); $i++) {

                if($coberturas[$i]['price_coverages'] != null) { 
                    $this->getRecord()->coverages()->create([
                        'plan_id'       => $this->getRecord()->id,
                        'price'         => $coberturas[$i]['price_coverages'],
                        'status'        => 'ACTIVO',
                        'created_by'    => auth()->user()->name
                    ]);
                }
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

            // dd($ageRange, $coverages, count($ageRange), count($coverages));

            for ($i = 0; $i < count($coverages); $i++) {

                for ($j = 0; $j < count($ageRange); $j++) {
                    $fee = new Fee();
                    $fee->age_range_id  = $ageRange[$j]['id'];
                    $fee->coverage_id   = $coverages[$i]['id'];
                    $fee->price         = $coberturas[$i]['price_fees'];
                    $fee->status        = 'ACTIVO';
                    $fee->created_by    = auth()->user()->name;
                    $fee->range         = $edades[$j]['range'];
                    $fee->coverage      = $coberturas[$i]['price_coverages'];
                    $fee->save();
                }
            }

            // dd($ageRange, $coverages);
            //..Cargamos la tabla de fees
            // for ($i = 0; $i < count($ageRange); $i++) {
                
            //     $fee = new Fee();
            //     $fee->age_range_id  = $ageRange[$i]['id'];
            //     $fee->coverage_id   = isset($coverages[$i]['id']) ? $coverages[$i]['id'] : null;
            //     $fee->price         = $coberturas[$i]['price_fees'];
            //     $fee->status        = 'ACTIVO';
            //     $fee->created_by    = auth()->user()->name;
            //     $fee->range         = $edades[$i]['range'];
            //     $fee->coverage      = $coberturas[$i]['price_coverages'];
            //     $fee->save();
            // }
            
        } catch (\Throwable $th) {
            dd($th);
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