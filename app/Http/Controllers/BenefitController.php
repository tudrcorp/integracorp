<?php

namespace App\Http\Controllers;

use App\Models\Coverage;
use App\Models\BenefitPlan;
use Illuminate\Http\Request;
use PhpParser\Node\Stmt\TryCatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class BenefitController extends Controller
{
    public static function getTableBenefit($planId)
    {
        try {

            if (!$planId) {
                // Devuelve colecciones vacías si no hay plan seleccionado
                return [
                    'coverages' => new Collection(),
                    'benefits' => new Collection(),
                    'matrix' => [],
                ];
            }

            // 1. Obtener las Coberturas asociadas al Plan (Headers de la tabla)
            $coverages = Coverage::where('plan_id', $planId)->orderBy('price', 'asc')
                ->get(['id', 'price'])
                ->keyBy('id'); // Indexamos por ID para fácil acceso

            // 2. Obtener todos los Beneficios (Filas de la tabla)
            $benefits = BenefitPlan::where('plan_id', $planId)->get(['benefit_id', 'description']);


            // 3. Obtener los datos del pivot (limite_uso) para las coberturas de este plan
            $pivotData = DB::table('benefit_coverages')
                ->select('benefit_id', 'coverage_id', 'limit')
                ->whereIn('coverage_id', $coverages->keys())
                ->get();

            // 4. Construir la matriz pivote
            $matrix = [];

            foreach ($benefits as $benefit) {
                $matrix[$benefit->benefit_id] = [
                    'nombre' => $benefit->description,
                    'limits' => [],
                ];

                foreach ($coverages as $coverage) {
                    // Buscar el límite de uso para el par (Beneficio, Cobertura)
                    $limitRecord = $pivotData->first(
                        fn($item) => $item->benefit_id == $benefit->benefit_id && $item->coverage_id == $coverage->id
                    );

                    // Si existe el límite, úsalo; si no, marca 'N/A'
                    $matrix[$benefit->benefit_id]['limits'][$coverage->id] = $limitRecord ? $limitRecord->limit : 'N/A';
                }
            }

            return [
                'coverages' => $coverages,
                'benefits' => $benefits,
                'matrix' => $matrix,
            ];
            
            //code...
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
        
    }
}