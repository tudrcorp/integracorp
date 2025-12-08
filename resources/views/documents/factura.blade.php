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
            padding: 5px;
            /* Espaciado interno */
            text-align: left;
            /* Alineación centrada */
        }

        /* Celdas de la tabla */
        tbody tr td {
            /* padding: 10px; */
            /* Espaciado interno */
            text-align: left;
            /* Alineación centrada */
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

        p {
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

    <div style="position: absolute; top: 100px; right: 0px; padding: 10px;">
        <p class="sin-margen" style="font-size: 14px; text-align: right; text-transform: uppercase;">
            <span style="font-weight: bold; color: #000000; ">Factura No. {{ $data_factura['invoice_number'] }}</span>
        </p>
        <p class="sin-margen" style="font-size: 14px; text-align: right; text-transform: uppercase;">
            <span style="font-weight: bold; color: #000000;">
                Fecha de Emisión: {{ $data_factura['emission_date'] }}
            </span>
        </p>
        <p class="sin-margen" style="font-size: 14px; text-align: right; text-transform: uppercase;">
            <span style="font-weight: bold; color: #000000;">
                Condiciones de Pago: Contado
            </span>
        </p>
    </div>

    <div style="position: absolute; top: 230px; left: 15px; padding: 5px; width: 100%;">
        <table style="width: 100%;
        border-collapse: collapse;
        border-spacing: 0;">

            <tbody>
                <tr>
                    <td style="padding: 2 !important;
                        margin: 0 !important;
                        line-height: 1;">

                        <span style="font-size: 14px;
                                text-transform: uppercase;
                                font-weight: bold;
                                color: #000000;
                                display: block;
                                margin: 0;
                                padding: 0;
                        ">
                            A Nombre de: {{ $data_factura['full_name_ti'] }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2 !important;
                        margin: 0 !important;
                        line-height: 1;">
                        <span style="font-size: 14px;
                                text-transform: uppercase;
                                font-weight: bold;
                                color: #000000;
                                display: block;
                                margin: 0;
                                padding: 0;
                        ">
                            Documento: V-{{ $data_factura['ci_rif_ti'] }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2 !important;
                        margin: 0 !important;
                        line-height: 1;">
                        <span style="font-size: 14px;
                                text-transform: uppercase;
                                font-weight: bold;
                                color: #000000;
                                display: block;
                                margin: 0;
                                padding: 0;
                        ">Dirección: {{ $data_factura['address_ti'] }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2 !important;
                        margin: 0 !important;
                        line-height: 1;">
                        <span style="font-size: 14px;
                                text-transform: uppercase;
                                font-weight: bold;
                                color: #000000;
                                display: block;
                                margin: 0;
                                padding: 0;
                        ">
                            Teléfono: {{ $data_factura['phone_ti'] }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2 !important;
                        margin: 0 !important;
                        line-height: 1;">
                        <span style="font-size: 14px;
                                text-transform: uppercase;
                                font-weight: bold;
                                color: #000000;
                                display: block;
                                margin: 0;
                                padding: 0;
                        ">
                            Correo: {{ $data_factura['email_ti'] }}</span>
                    </td>
                </tr>
            </tbody>
        </table>

    </div>

    <div style="display: blog; justify-content: center; align-items: center; text-align: center; margin-top: 370px; padding: 20px">
        <table>
            <thead>
                <tr>
                    <th style="border-bottom: 2px solid #000000; text-transform: uppercase;">Descripción</th>
                    <th style="border-bottom: 2px solid #000000; text-align: right; text-transform: uppercase;">Monto</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="font-weight: bold; padding: 5px">
                        <p style="text-transform: uppercase; line-height: 1.2;">
                            @if($data_factura['plan'] == 'PLAN ESPECIAL')
                            {{ $data_factura['plan'] }} - AFILIACION {{ $data_factura['frequency'] }}
                            <br> ASISTENCIA MEDICA POR PATOLOGIAS LISTADAS
                            <br> COBERTURA GEOGRAFICA – LOCAL VENEZUELA US$ {{ number_format($data_factura['coverage'], 2) }} <br>
                            @endif
                            @if($data_factura['plan'] == 'PLAN IDEAL')
                            {{ $data_factura['plan'] }}
                            <br> ASISTENCIA MEDICA POR ACCIDENTES PERSONALES
                            <br> COBERTURA GEOGRAFICA – LOCAL VENEZUELA US$ {{ number_format($data_factura['coverage'], 2) }} <br>
                            @endif
                            @if($data_factura['plan'] == 'PLAN INICIAL')
                            {{ $data_factura['plan'] }}
                            <br> ASISTENCIA MEDICA <br>
                            @endif
                            {{-- PERÍODO DE VIGENCIA DESDE EL {{ $data_factura['desde'] }} HASTA EL {{ $data_factura['hasta'] }} number_format(Affiliation::where('status', 'ACTIVA')->sum('total_amount'), 2, ',', '.') --}}

                        </p>
                    </td>
                    <td style="font-weight: bold; text-align: right;">{{ number_format($data_factura['total_amount'], 2, ',', '.') }}Bs.</td>


                </tr>

            </tbody>
        </table>
    </div>

    <div style="position: absolute; top: 580px; left: 15px; padding: 10px; width: 100%;">
        <p class="sin-margen" style="font-size: 14px; text-transform: uppercase;">
            <span style="font-weight: bold; ">Ref. de Pago: {{ $data_factura['reference'] }}</span>

        </p>
        <p class="sin-margen" style="font-size: 14px; text-transform: uppercase;">
            <span style="font-weight: bold; ">{{ $data_factura['payment_method'] }}</span>
        </p>
        <p class="sin-margen" style="font-size: 14px; text-transform: uppercase;">
            <span style="font-weight: bold; ">Validado: Dpto. Finanzas: {{ $data_factura['emission_date'] }}</span>
        </p>
    </div>

    <div style="position: absolute; top: 617px; left: 503px; padding: 10px; width: 100%;">
        <p class="sin-margen" style="font-size: 14px; text-transform: uppercase;">
            <span style="font-weight: bold; ">Monto Total: {{ number_format($data_factura['total_amount'], 2, ',', '.') }}Bs.</span>
        </p>
    </div>

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

