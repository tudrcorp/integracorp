<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Planes Tu Doctor en Casa</title>
    <style>
         body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            text-align: center;
            /* Centra todo el contenido */
         }


         .header {
            /* background-color: #00539C; */
            /* Azul oscuro */
            color: white;
            padding: 20px;
            text-align: center;
         }

         .content {
            margin-top: 20px;
            text-align: justify;
            /* Justifica el texto */
         }


         .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 0.9em;
            color: #555;

         }

         .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
         }

         .social-icons img {
            width: 40px;
            height: 40px;
         }

         ul {
         list-style-type: disc;
         padding-left: 20px;
         }

         li {
         margin-bottom: 10px;
         }


         h1,
         h2 {
         color: #333;
         }

         table {
         width: 100%;
         border-collapse: collapse;
         margin: 20px 0;
         }

         table,
         th,
         td {
         border: 1px solid #ddd;
         }

         th,
         td {
         padding: 10px;
         text-align: left;
         }

         th {
         background-color: #f4f4f4;
         }



    </style>
</head>
<body>
    @php
    // Agrupar los datos por plan_id y age_range_id
    $groupedData = [];
        foreach ($details['data'] as $item) {
            $key = $item->plan_id . '-' . $item->age_range_id; // Clave única: plan_id-age_range_id
            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'plan_id' => $item->plan,
                    'age_range_id' => $item->age_range_id,
                    'range' => $item->age_range, // Almacenar el rango de edad
                    'items' => [],
                ];
            }
            $groupedData[$key]['items'][] = $item;
        }
    @endphp

    <div class="header">
        <img src="https://app.piedy.com/images/BANER-GUSTAVO-1.png" alt="Logo Bancamiga" style="max-width: 100%;">
    </div>

    <div style="margin: auto; width: 600px; padding: 10px; text-align: center;">

         <!-- Mostrar una tabla para cada plan_id -->
         @foreach ($groupedData as $group)
        <h2>Plan: {{ $group['plan_id'] }} - Edad: {{ $group['range'] }}años</h2>
        <table>
            <thead>
                <tr>
                    <th>Coberturas</th>
                    <th>Personas</th>
                    <th>Total Anual</th>
                    <th>Total Trimestral</th>
                    <th>Total Semestral</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['items'] as $item)
                    <tr>
                        <td>${{ number_format($item->coverage, 2) }}</td>
                        <td>{{ $item->total_persons }}</td>
                        <td>${{ number_format($item->subtotal_anual, 2) }}</td>
                        <td>${{ number_format($item->subtotal_quarterly, 2) }}</td>
                        <td>${{ number_format($item->subtotal_biannual, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach


    </div>

    <div class="footer">
        <img src="https://app.piedy.com/images/BANER-GUSTAVO-2.png" alt="Logo Tubanca" style="max-width: 100%;">
        <p style="font-size: 0.8em; font-style: italic;">Gracias por confiar en nosotros para gestionar las necesidades médicas de tu empresa</p>
    </div>


    {{-- @php
        // Agrupar los datos por plan_id y age_range_id
        $groupedData = [];
        foreach ($details['data'] as $item) {
            $key = $item->plan_id . '-' . $item->age_range_id; // Clave única: plan_id-age_range_id
            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'plan_id' => $item->plan,
                    'age_range_id' => $item->age_range_id,
                    'range' => $item->age_range, // Almacenar el rango de edad
                    'items' => [],
                ];
            }
            $groupedData[$key]['items'][] = $item;
        }
    @endphp

    <!-- Mostrar una tabla para cada combinación de plan_id y age_range_id -->
    @foreach ($groupedData as $group)
        <h2>Plan ID: {{ $group['plan_id'] }} - Rango de Edad: {{ $group['range'] }}</h2>
        <table>
            <thead>
                <tr>
                    <th>Precio</th>
                    <th>Total de Personas</th>
                    <th>Subtotal Anual</th>
                    <th>Subtotal Trimestral</th>
                    <th>Subtotal Semestral</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['items'] as $item)
                    <tr>
                        <td>${{ number_format($item->coverage, 2) }}</td>
                        <td>{{ $item->total_persons }}</td>
                        <td>${{ number_format($item->subtotal_anual, 2) }}</td>
                        <td>${{ number_format($item->subtotal_quarterly, 2) }}</td>
                        <td>${{ number_format($item->subtotal_biannual, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach --}}


</body>
</html>

