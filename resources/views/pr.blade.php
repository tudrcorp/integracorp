@php
use App\Models\Coverage;
use App\Models\Plan;
use App\Models\BenefitPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

// AVISO: El número de columnas se genera dinámicamente según las coberturas asociadas al plan.
$planId = 3;

if (!$planId) {
return [
'coverages' => new Collection(),
'benefits' => new Collection(),
'matrix' => [],
];
}

// 1. Obtener las Coberturas asociadas al Plan (Headers de la tabla)
$coverages = Coverage::where('plan_id', $planId)->orderBy('price', 'asc')
->get(['id', 'price'])
->keyBy('id');

// Calcular el ancho restante para las columnas de cobertura
$totalCoverages = count($coverages);
// 70% del ancho de la tabla dividido entre el número de coberturas.
$coverageColumnWidth = $totalCoverages > 0 ? (70 / $totalCoverages) : 0;


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
$limitRecord = $pivotData->first(
fn($item) => $item->benefit_id == $benefit->benefit_id && $item->coverage_id == $coverage->id
);
$matrix[$benefit->benefit_id]['limits'][$coverage->id] = $limitRecord ? $limitRecord->limit : 'N/A';
}
}


@endphp

<div style="
/* Contenedor general: se reducen los bordes y el padding exterior /
background-color: #ffffff;
border: 1px solid #e5e7eb;
border-radius: 0.3rem; / Bordes más pequeños /
padding: 0.2rem; / Padding exterior muy pequeño */
box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
width: 100%;
margin: 0;
">

    @if ($planId && count($coverages) > 0)

    {{-- Contenedor de la Tabla --}}
    <div style="
    margin-top: 0.3rem; /* Margen superior reducido */
    border: 1px solid #e5e7eb; 
    border-radius: 0.3rem; 
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
">
        <table style="
        width: 100%; 
        border-collapse: collapse; 
        font-size: 0.5rem; /* ¡TAMAÑO DE FUENTE EXTREMADAMENTE PEQUEÑO! */
        text-align: left; 
        color: #6b7280;
        table-layout: fixed; 
    ">
            <thead style="
            background-color: #f9fafb; 
            color: #374151; 
            text-transform: uppercase; 
            font-size: 0.5rem;
        ">
                <tr>
                    {{-- Primera Columna Fija: Beneficio (30% del ancho) --}}
                    <th scope="col" style="
                        padding: 0.2rem; /* ¡PADDING DE CELDA MUY REDUCIDO! */
                        font-weight: 700; 
                        text-align: left;
                        width: 30%; 
                        background-color: #f9fafb;
                    ">
                        Beneficio
                    </th>
                    {{-- Columnas Dinámicas: Coberturas (70% del ancho restante) --}}
                    @foreach ($coverages as $coverage)
                    <th scope="col" style="
                    padding: 0.2rem; 
                    text-align: center; 
                    font-weight: 700;
                    width: {{ $coverageColumnWidth }}%; 
                    word-break: break-word; 
                ">
                        {{ $coverage->price }}
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{-- Filas Dinámicas: Beneficios --}}
                @foreach ($matrix as $benefitId => $data)
                <tr style="
                        background-color: #ffffff; 
                        border-bottom: 1px solid #f3f4f6; 
                    ">
                    {{-- Nombre del Beneficio --}}
                    <th scope="row" style="
                                padding: 0.2rem; 
                                font-weight: 500; 
                                color: #1f2937; 
                                text-align: left;
                                word-break: break-word; 
                            ">
                        {{ $data['nombre'] }}
                    </th>
                    {{-- Celdas de Límites de Uso --}}
                    @foreach ($coverages as $coverage)
                    <td style="
                            padding: 0.2rem; 
                            text-align: center;
                            word-break: break-word;
                        ">
                        @php
                        $isNumeric = is_numeric($data['limits'][$coverage->id]);
                        $color = $isNumeric ? '#2563eb' : '#9ca3af';
                        $fontWeight = $isNumeric ? '700' : '500';
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
    @elseif ($planId)
    <div style="padding: 1rem; text-align: center; color: #9ca3af; font-size: 0.8rem;">
        El Plan seleccionado no tiene coberturas asignadas.
    </div>
    @else
    <div style="padding: 1rem; text-align: center; color: #9ca3af; font-size: 0.8rem;">
        Por favor, selecciona un Plan para ver la matriz de límites.
    </div>
    @endif


</div>
