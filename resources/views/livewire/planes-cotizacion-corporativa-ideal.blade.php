<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación</title>
    <style>
        @page {
            margin: 0px;
            /* background-color: white;  */
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;

            /* margin-top: 0cm;
            margin-left: 0cm;
            margin-right: 0cm;
            margin-bottom: 0cm; */
            /* Centra todo el contenido */
        }

        .page-break {
            page-break-before: always;
        }

        .cover {
            position: relative;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            /* page-break-after: always; */
        }

        .content {
            margin-top: 30px;
            padding: 20px;
        }

        /* Página vacía */
        .blank-page {
            width: 100%;
            height: 100vh;
            page-break-after: auto;
        }

        .caja {
            position: relative;
            width: 300px;
            height: 5px;
            background-color: rgba(255, 255, 255, 0.3);
            /* Blanco con 30% de opacidad */
            border-radius: 20px;
            /* Esquinas redondeadas */
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            font-family: Arial, sans-serif;
            color: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px auto;

        }

        h1 {
            font-size: 30px;
            margin-bottom: 10px;
            color: white;
        }

        /* Logos */
        .logo-top-right {
            width: 50px;
            height: 50px;
            float: right;
        }

        .logo-bottom-left {
            width: 50px;
            height: 50px;
            float: left;
        }

        .sin-margen {
            margin: 2;
        }

        table {
            /* width: 100%;
            max-width: 800px; */
            border-collapse: collapse;
            margin: 20px auto;
            font-family: Arial, sans-serif;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
        }

    </style>
</head>
<body>
    <!-- Primera página: Imagen de fondo -->
    <div class="cover" style="background-image: url('{{ public_path('storage/footer-ideal.png') }}');">

        <div style="position: absolute; top: 0px; left: 0px; margin-top: 15px; padding: 20px; margin-left: 20px">
            <p class="sin-margen" style="margin-bottom: 5px; font-size: 18px;">
                <span style="font-weight: bold; color: #052F60; font-size: 25px; font-style: italic;">Propuesta
                </span>
                <span style="font-weight: bold; color: #7ab2db; font-size: 25px; font-style: italic;">Económica
                </span>
            </p>

            <p class="sin-margen" style="font-size: 16px;">
                <span style="font-weight: bold; color: #000000;">
                    Datos del afiliado titular:
                </span>
                <span style="">
                    Sr(a): {{ $name }}
                </span>
            </p>
            <p class="sin-margen" style="font-size: 16px;">
                <span style="font-weight: bold; color: #000000;">
                    Nombre del Agente:
                </span>
                <span style="margin-left: 38px">
                    TuDrEnCasa
                </span>
            </p>
            <p class="sin-margen" style="font-size: 16px; margin-top: 2px">
                <span style="font-weight: bold; color: #000000;">
                    Fecha de emisión:
                </span>
                <span style="margin-left: 50px">
                    21/05/2025
                    <br>
                    <span style="font-size: 12px; font-style: italic; font-weight: bold">
                        Propuesta válida por 15 días a partir de la fecha de emisión.
                    </span>
                </span>
            </p>
        </div>
        <div style="position: absolute; top: 140px; right: 10px; margin-top: 20px; padding: 20px; margin-right: 20px">
            <img src="{{ public_path('storage/beneficios-plan-ideal.png') }}" style="width: 700px; height: auto;" alt="">
        </div>

        <div style="position: absolute; top: 500px; right: 10px; margin-top: 20px; padding: 20px; margin-right: 20px; width: 700px;">
            <table style="width: 100%; font-type: Helvetica, sans-serif;">
                <tr style="background-color: #082f62; font-size: 10px;">

                    <th style="font-weight: bold; color: white;">RANGO DE EDAD</th>
                    <th style="font-weight: bold; color: white;">POBLACIÓN</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 1K</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 2K</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 3K</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 5K</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 10K</th>

                </tr>
                @foreach ($data as $key => $value)
                <tr>
                    <td style="font-weight: bold; font-size: 10px;">{{ $key }} años</td>
                    <td style="font-weight: bold; font-size: 10px;">{{ $value[0]->total_persons > 1 ? $value[0]->total_persons . ' Persona(s)' : '' }}</td>

                    @foreach ($value as $value2)
                    <td style="font-weight: bold; font-size: 10px;">{{ round($value2->subtotal_anual) }} US$
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </table>
            @php
            // Inicializar array para almacenar los totales por columna
            $totalColumns = [0, 0, 0, 0, 0]; // Para US$1K a US$10K (5 columnas)

            // Recorrer los datos para sumar por columna
            foreach ($data as $key => $value) {
            if($value[0]->total_persons == 1){
            foreach ($value[0] as $index => $item) {
            if (isset($totalColumns[$index])) {
            $totalColumns[$index] = round($item[0]->subtotal_anual);
            }
            }
            }
            if($value[0]->total_persons > 1){
            foreach ($value as $index => $item) {
            if (isset($totalColumns[$index])) {
            $totalColumns[$index] += round($item->subtotal_anual);
            }
            }
            }
            }

            @endphp
            <table style="width: 100%; border-collapse: collapse; font-type: Helvetica, sans-serif;">
                <tbody>
                    <tr>
                        <td colspan="3" style="font-weight: bold; color: white; font-size: 10px; width: 88px; background-color: #082f62">TARIFA GRUPAL ANUAL</td>

                        @foreach ($totalColumns as $total)
                        <td style="text-align: center; font-weight: bold; font-size: 10px;">
                            {{ round($total, 2) }} US$
                        </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td colspan="3" style="font-weight: bold; color: white; font-size: 10px; width: 88px; background-color: #082f62">TARIFA GRUPAL SEMESTRAL</td>


                        @foreach ($totalColumns as $total)
                        <td style="text-align: center; font-weight: bold; font-size: 10px;">
                            {{ round($total / 2) }} US$
                        </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td colspan="3" style="font-weight: bold; color: white; font-size: 10px; width: 88px; background-color: #082f62">TARIFA GRUPAL TRIMESTRAL</td>


                        @foreach ($totalColumns as $total)
                        <td style="text-align: center; font-weight: bold; font-size: 10px;">
                            {{ round($total / 4) }} US$
                        </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>



        <div style="position: absolute; top: 0px; right: 0px; margin-top: 20px; padding: 20px; margin-right: 20px">
            <div>
                <img class="logo-bottom-left" src="{{ public_path('storage/logo2-pdf.png') }}" style="width: 150px; height: 70px;" alt="">
            </div>
        </div>
        <!-- Primera página: Imagen de fondo -->


    </div>





</body>
</html>

