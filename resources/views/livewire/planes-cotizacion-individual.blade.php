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

        th, td {
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
        }

        table.blueTable {
            border: 1px solid #1C6EA4;
            background-color: #EEEEEE;
            width: 500px;
            text-align: left;
            border-collapse: collapse;
        }
        table.blueTable td, table.blueTable th {
            border: 1px solid #AAAAAA;
            padding: 3px 2px;
        }
        table.blueTable tbody td {
            font-size: 13px;
        }
        table.blueTable tr:nth-child(even) {
            background: #D0E4F5;
        }
        table.blueTable thead {
            background: #1C6EA4;
            background: -moz-linear-gradient(top, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
            background: -webkit-linear-gradient(top, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
            background: linear-gradient(to bottom, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
            border-bottom: 2px solid #444444;
        }
        table.blueTable thead th {
            font-size: 15px;
            font-weight: bold;
            color: #FFFFFF;
            border-left: 2px solid #D0E4F5;
        }
        table.blueTable thead th:first-child {
            border-left: none;
        }



    </style>
</head>
<body>
    <!-- Primera página: Imagen de fondo -->
    <div class="cover" style="background-image: url('{{ public_path('storage/footer-plan-inicial.webp') }}');">

        <div style="position: absolute; top: 0px; left: 0px; margin-top: 15px; padding: 20px; margin-left: 20px">
            <p class="sin-margen" style="margin-bottom: 5px; font-size: 18px;">
                <span style="font-weight: bold; color: #305B93; font-size: 25px; font-style: italic;">Propuesta

                </span>
                <span style="font-weight: bold; color: #5488AE; font-size: 25px; font-style: italic;">Económica
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
        <div style="position: absolute; top: 130px; right: 10px; margin-top: 15px; padding: 20px; margin-right: 20px">
            <img src="{{ public_path('storage/beneficios-plan-inicial.png') }}" style="width: 700px; height: auto;" alt="">
        </div>

        <!--prueba-->
        {{-- @php
            use App\Models\BenefitPlan;
            $benefits = BenefitPlan::where('plan_id', 1)->get();
        @endphp
        <div style="position: absolute; top: 130px; right: 10px; margin-top: 30px; padding: 20px; margin-right: 20px; width: 700px;">
            <table style="width: 100%; font-type: Helvetica, sans-serif; border-radius: 30px;">
                <thead style="background-color: #305B93; ">
                    <tr>
                        <th style="padding: 10px; color: white">Beneficios Domiciliarios o In Situ</th>
                        <th style="padding: 10px; color: white">Plan Inicial</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($benefits as $value)
                        <tr>
                            <td style="padding: 10px; font-size: 10px;">{{ $value->description }}</td>
                            <td style="text-align: center; item-align: center; vertical-align: middle;">
                                <img src="{{ public_path('storage/checkPng.png') }}" style="width: 20px; height: auto;" alt="">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div> --}}

       

        <div style="position: absolute; top: 475px; right: 10px; margin-top: 30px; padding: 20px; margin-right: 20px; width: 700px;">
            <table style="width: 100%; font-type: Helvetica, sans-serif;">

                <tr>
                    <td style="font-weight: bold; color: white; background-color: #305B93; font-size: 10px;">RANGO DE EDAD</td>
                    <td style="font-weight: bold; font-size: 10px;">{{ $data['age_range'] }} años</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; color: white; background-color: #305B93; font-size: 10px;">POBLACIÓN</td>
                    <td style="font-weight: bold; font-size: 10px;">{{ $data['total_persons'] }} persona(s)</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; color: white; background-color: #305B93; font-size: 10px;">TARIFA INDIVIDUAL</td>
                    <td style="font-weight: bold; font-size: 10px;">{{ round($data['fee']) }} US$</td>
                </tr>
            </table>

            <table style="width: 100%; font-type: Helvetica, sans-serif; margin-top: 15px">
                <tr>
                    <td style="font-weight: bold; color: white; background-color: #305B93; font-size: 10px; width: 411px">TOTAL ANUAL</td>
                    <td style="font-weight: bold; font-size: 10px;">{{ round($data['subtotal_anual']) }} US$</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; color: white; background-color: #305B93; font-size: 10px; width: 411px">TOTAL SEMESTRAL</td>
                    <td style="font-weight: bold; font-size: 10px;">{{ round($data['subtotal_biannual']) }} US$</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; color: white; background-color: #305B93; font-size: 10px; width: 411px">TOTAL TRIMESTRAL</td>
                    <td style="font-weight: bold; font-size: 10px;">{{ round($data['subtotal_quarterly']) }} US$</td>
                </tr>
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


