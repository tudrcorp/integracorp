@php
use App\Models\Coverage;
use App\Models\Plan;
use App\Models\BenefitPlan;

    $planId = 3;

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
    // dd($coverages);
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

@endphp
<div style="
    background-color: #ffffff; /* bg-white /
    border: 1px solid #e5e7eb; / border-gray-200 /
    border-radius: 0.5rem; / rounded-lg /
    padding: 1rem; / p-4 /
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); / shadow-sm */
">

    @if ($planId && count($coverages) > 0)

    {{-- Contenedor de la Tabla --}}
    <div style="
        overflow-x: auto; 
        margin-top: 1rem; 
        border: 1px solid #e5e7eb; 
        border-radius: 0.5rem; 
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    ">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left; color: #6b7280;">
            <thead style="
                background-color: #f9fafb; /* bg-gray-50 */
                color: #374151; /* text-gray-700 */
                text-transform: uppercase; 
                font-size: 0.75rem;
            ">
                <tr>
                    {{-- Primera Columna Fija: Beneficio --}}
                    <th scope="col" style="
                            padding: 0.75rem 1.5rem; 
                            font-weight: 600; 
                            text-align: left;
                            background-color: #f9fafb; /* Fondo para el encabezado */
                        ">
                        Beneficio
                    </th>
                    {{-- Columnas Dinámicas: Coberturas --}}
                    @foreach ($coverages as $coverage)
                    <th scope="col" style="padding: 0.75rem 1.5rem; text-align: center; font-weight: 600;">
                        {{ $coverage->price }}
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{-- Filas Dinámicas: Beneficios --}}
                @foreach ($matrix as $benefitId => $data)
                    <tr style="
                            background-color: #ffffff; /* bg-white */
                            border-bottom: 1px solid #f3f4f6; /* border-b-gray-100 */
                        ">
                        {{-- Nombre del Beneficio --}}
                        <th scope="row" style="
                                    padding: 1rem 1.5rem; 
                                    font-weight: 500; 
                                    color: #1f2937; /* text-gray-900 */
                                    white-space: nowrap;
                                    text-align: left;
                                ">
                            {{ $data['nombre'] }}
                        </th>
                        {{-- Celdas de Límites de Uso --}}
                        @foreach ($coverages as $coverage)
                            <td style="padding: 1rem 1.5rem; text-align: center;">
                                @php
                                    $isNumeric  = is_numeric($data['limits'][$coverage->id]);
                                    $color      = $isNumeric ? '#2563eb' : '#9ca3af'; /* primary-600 o gray-400 */
                                    $fontWeight = $isNumeric ? '700' : '500'; /* font-semibold */
                                @endphp
                                <span style="color: {{ $color }}; font-weight: {{ $fontWeight }};">
                                    {{ $data['limits'][$coverage->id] }}
                                </span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @elseif ($selectedPlanId)
        <div style="padding: 1rem; text-align: center; color: #9ca3af;">
            El Plan seleccionado no tiene coberturas asignadas.
        </div>
    @else
        <div style="padding: 1rem; text-align: center; color: #9ca3af;">
            Por favor, selecciona un Plan para ver la matriz de límites.
        </div>
    @endif
</div>
