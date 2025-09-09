<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarjeta de Afiliado</title>

    <style>
        /* Estilos generales */
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            /* Centra horizontalmente */
            align-items: center;
            /* Centra verticalmente */
            /* width: 100vw; */
            min-height: 100vh;
            /* Altura mínima de la ventana */
            /* background-color: #f4f4f9; */

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

        /* Contenedor padre */
        .container {
            width: 700px;
            /* Ancho fijo del contenedor */
            display: flex;
            /* Activa Flexbox */
            justify-content: space-between;
            /* Espacio entre los divs */
            border: 1px solid #ccc;
            /* Borde para visualizar el contenedor */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            /* Sombra suave */
            border-radius: 8px;
            /* Bordes redondeados */
            overflow: hidden;
            /* Asegura que los bordes redondeados se vean bien */
        }

        .parent {
            display: flex;
            /* Activa Flexbox */
            width: 100vw;
            /* Ancho total de la ventana */
            height: 155px;
            /* Altura fija */
            background-color: #f4f4f9;
            /* Fondo claro */
            border: 1px solid #ccc;
            /* Borde para visualizar el contenedor */
            box-sizing: border-box;
            /* Incluye el borde en el cálculo del tamaño */
        }

        /* Divs hijos */
        .child {
            flex: 1;
            /* Cada div ocupa el mismo espacio (50% del ancho del padre) */
            display: flex;
            justify-content: center;
            /* Centra horizontalmente */
            align-items: center;
            /* Centra verticalmente */
            text-align: center;
            /* Alinea el texto al centro */
            font-size: 18px;
            color: #ffffff;
            /* Texto blanco */
        }

        /* Estilo específico para cada div */
        .left {
            background-color: #00539c;
            /* Azul oscuro */
        }

        .right {
            background-color: #333333;
            /* Gris oscuro */
        }

        /* Estilos de la tabla */
        table {
            width: 100%;
            /* Ancho total */
            border-collapse: separate;
            /* Necesario para bordes redondeados */
            border-spacing: 0;
            /* Elimina el espacio entre celdas */
            margin: 0 auto;
            /* Centra la tabla */
            max-width: 800px;
            /* Ancho máximo */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            /* Sombra suave */
            font-size: 14px;
        }

        /* Encabezados de columna */
        thead tr th {
            background-color: #cccccc;
            /* Color gris */
            color: #333333;
            /* Texto oscuro */
            padding: 5px;
            /* Espaciado interno */
            text-align: center;
            /* Alineación centrada */
            border-radius: 30px;
            /* Esquinas redondeadas solo en la parte superior */
        }

        /* Celdas de la tabla */
        tbody tr td {
            background-color: #e6f7ff;
            /* Azul muy claro */
            color: #333333;
            /* Texto oscuro */
            padding: 5px;
            /* Espaciado interno */
            text-align: center;
            /* Alineación centrada */
            border-radius: 30px;
            /* Esquinas redondeadas */
        }

        /* Separación entre filas */
        tbody tr {
            margin-bottom: 5px;
            /* Espacio entre filas */
        }

        /* Efecto hover en las filas */
        tbody tr:hover {
            background-color: #d9edff;
            /* Cambia el color al pasar el cursor */
        }

        .table_info_ti {
            width: 100%;
            /* Ancho total */
            font-size: 14px;
        }

        .tr_table_info_ti .td_table_info_ti {

            background-color: #ffffff;
            /* Color gris */
            text-align: left;
            /* Alineación centrada */
            background-color: none;
            padding: 2px;
            /* Espaciado interno */

        }

        .tr_table_info_ti .td_table_info_ti p {
            line-height: 0.5;
        }

        footer {
            display: flex;
            position: fixed;
            bottom: 0px;
            left: 0px;
            right: 0px;
            align-items: center;
            text-align: center;
        }

    </style>


</head>
<body>
    <div>
        <!-- Content here -->
        <div>
            <img class="logo-top-right" src="{{ public_path('storage/logo2-pdf.png') }}" style="width: 150px; height: 70px;" alt="">
            <img class="logo-bottom-left" src="{{ public_path('storage/logo1-pdf.png') }}" style="width: 150px; height: 70px;" alt="">
        </div>
    </div>

    <div style="display: blog; justify-content: center; align-items: center; text-align: center; margin-top: 20px; color: #00539c">
        <h1>CERTIFICADO DE AFILIACIÓN</h1>
    </div>

    <div style="display: blog; justify-content: center; align-items: center; text-align: center; margin-top: 30px">
        <table class="table_info_ti">
            <tbody class="tb_table_info_ti">
                <tr class="tr_table_info_ti">
                    <td class="td_table_info_ti" style="font-weight: bold">Contratante:</td>
                    <td class="td_table_info_ti">{{ $data['name_corporate'] }}</td>
                    <td class="td_table_info_ti" style="font-weight: bold">Agente:</td>
                    <td class="td_table_info_ti">{{ $data['name_agent'] }}</td>
                </tr>
                <tr class="tr_table_info_ti">
                    <td class="td_table_info_ti" style="font-weight: bold">Código de Afiliación:</td>
                    <td class="td_table_info_ti">{{ $data['code'] }}</td>
                    <td class="td_table_info_ti" style="font-weight: bold">Tarifa Anual:</td>
                    <td class="td_table_info_ti">US$ {{ app('App\Http\Controllers\UtilsController')->formatMount($data['fee_anual']) }}</td>

                </tr>
                <tr class="tr_table_info_ti">
                    {{-- <td class="td_table_info_ti" style="font-weight: bold">Plan:</td>
                    <td class="td_table_info_ti">{{ $data['plan'] }}</td> --}}
                    <td class="td_table_info_ti" style="font-weight: bold">Frecuencia de Pago:</td>
                    <td class="td_table_info_ti">{{ $data['payment_frequency'] }}</td>
                </tr>
                <tr class="tr_table_info_ti">
                    <td class="td_table_info_ti" style="font-weight: bold">Fecha de Afiliación:</td>
                    <td class="td_table_info_ti">{{ now()->format('d-m-Y') }}</td>
                    <td class="td_table_info_ti" style="font-weight: bold">Tarifa Periodo:</td>
                    <td class="td_table_info_ti">US$ {{ app('App\Http\Controllers\UtilsController')->formatMount($data['total_amount']) }}</td>

                </tr>

                <tr class="tr_table_info_ti">
                    <td class="td_table_info_ti" style="font-weight: bold">Vigencia:</td>
                    <td class="td_table_info_ti">
                        <p class="td_table_info_ti" style="font-weight: bold">Desde: {{ date('d-m-Y') }}</p>
                        <p class="td_table_info_ti" style="font-weight: bold">Hasta: {{ date('d-m-Y', strtotime('+1 years')); }}</p>
                    </td>

                    <td class="td_table_info_ti" style="font-weight: bold">Periodo Facturado:</td>
                    <td class="td_table_info_ti">
                        <p class="td_table_info_ti" style="font-weight: bold">Desde: {{ date('d-m-Y') }}</p>
                        <p class="td_table_info_ti" style="font-weight: bold">Hasta: {{ date('d-m-Y') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="display: blog; justify-content: center; align-items: center; text-align: center; margin-top: 5px">
        <p style="text-align: center; margin-bottom: 15px; color: #00539c; font-weight: bold; font-size: 20px; text-transform: uppercase;">Datos del Afiliado y Beneficiarios</p>
        <table>
            <thead>
                <tr>
                    <th>Nombre y Apellido</th>
                    <th>Documento de Identidad</th>
                    <th>Fecha de Nacimiento</th>
                    <th>Plan</th>
                    <th>Cobertura</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($afiliates as $affiliate)
                <tr>
                    <td>{{ $affiliate['first_name'] }} {{ $affiliate['last_name'] }}</td>
                    <td>{{ $affiliate['nro_identificacion'] }}</td>
                    <td>{{ $affiliate['birth_date'] }}</td>
                    <td>{{ App\Models\Plan::find($affiliate['plan_id'])->description }}</td>
                    <td>{{ 
                            app('App\Http\Controllers\UtilsController')->formatMount(App\Models\Coverage::find($affiliate['coverage_id'])->price)
                        }}
                    </td>

                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <footer>
        <img src="{{ public_path('storage/firma-pdf.png') }}" style="width: 35%" alt="">
        <img src="{{ public_path('storage/bannerFooter.png') }}" style="width: 100%; margin-top: 5px" alt="">
    </footer>


    <script type="text/php">
        if ( isset($pdf) ) {
            $pdf->page_script('
                $font = $fontMetrics->get_font("Arial, Helvetica, sans-serif", "normal");
                $pdf->text(500, 790, "Pag $PAGE_NUM/$PAGE_COUNT", $font, 10);
            ');
        }
    </script>
</body>

</html>

