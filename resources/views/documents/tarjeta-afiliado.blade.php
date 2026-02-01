<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tarjeta de Afiliado</title>

        <style>
            @page {
                margin: 0px;
            }

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


            /* Estilos de la tabla */
            table {
                width: 100%;
                /* Ancho total */
                border-collapse: separate;
                /* Necesario para bordes redondeados */
                border-spacing: 0;
                /* Elimina el espacio entre celdas */
                margin: 0;
                /* Centra la tabla */
                max-width: 800px;
                /* Ancho máximo */
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                /* Sombra suave */
                font-size: 10px;
            }


            /* Separación entre filas */


            /* Efecto hover en las filas */
            tbody tr:hover {
                background-color: #d9edff;
                /* Cambia el color al pasar el cursor */
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

            .titulos_table_uno {
                color: #575757;
                font-size: 12px;
                text-align: left;
                font-weight: bold;
                text-transform: uppercase;
                font-style: sans-serif;
                font-family: 'Helvetica', Century, sans-serif;

            }

            .contenido_table_uno {
                color: #000000;
                font-size: 12px;
                text-align: left;
                text-transform: uppercase;
                font-style: sans-serif;
                font-family: 'Helvetica', Century, sans-serif;

            }

        </style>

    </head>
    <body>

        <!-- Primera página: Imagen de fondo -->
        <div class="cover" style="background-image: url('{{ public_path('storage/certificados/fondo-certificado.png') }}'); ">
            <div style="position: absolute; top: 300px; right: 385px; margin-top: 0px; padding: 0px; margin-left: 0px">
                <p><span style="font-weight: bold; color: #305B93; font-size: 25px; font-style: arial;">TARJETA DEL AFILIADO</span></p>
            </div>
            <div style="position: absolute; top: 10px; right: 75px; margin-top: 0px; padding: 0px; margin-left: 0px">
                <img src="{{ public_path('storage/certificados/tarjeta-afiliado.png') }}" style="width: 100%;" alt="">
            </div>
            <div style="position: absolute; top: 423px; right: 458px; margin-top: 0px; padding: 0px; margin-left: 0px; font-weight: bold; font-size: 12px;">
                {{ $data['code'] }}
            </div>
            @php
                $name = App\Http\Controllers\UtilsController::splitName($data['name']);
            @endphp
            <div style="position: absolute; top: 440px; left: 138px; margin-top: 0px; padding: 0px; margin-left: 0px; font-weight: bold; font-size: 12px;">
                {{ $name['first_part'] }}<br>{{ $name['second_part'] }}
            </div>
            <div style="position: absolute; top: 475px; left: 155px; margin-top: 0px; padding: 0px; margin-left: 0px; font-weight: bold; font-size: 12px;">
                {{ $data['ci'] }}
            </div>
            <div style="position: absolute; top: 497px; left: 164px; margin-top: 0px; padding: 0px; margin-left: 0px; font-weight: bold; font-size: 12px;">
                @if($data['plan'] == 'PLAN INICIAL')
                    INICIAL  
                @endif
                @if($data['plan'] == 'PLAN IDEAL')
                    IDEAL
                @endif
                @if($data['plan'] == 'PLAN ESPECIAL')
                    ESPECIAL
                @endif
            </div>
            <div style="position: absolute; top: 494px; right: 323px; margin-top: 0px; padding: 0px; margin-left: 0px; font-weight: bold; font-size: 12px;">
                {{ $data['desde'] }}
            </div>
            <div style="position: absolute; top: 515px; left: 235px; margin-top: 0px; padding: 0px; margin-left: 0px; font-weight: bold; font-size: 12px;">
                {{ $data['frecuencia'] }}
            </div>
            <div style="position: absolute; top: 511px; right: 323px; margin-top: 0px; padding: 0px; margin-left: 0px; font-weight: bold; font-size: 12px;">
                {{ $data['hasta'] }}
            </div>
            <div style="position: absolute; top: 533px; left: 190px; margin-top: 0px; padding: 0px; margin-left: 0px; font-weight: bold; font-size: 12px;">
                @if($data['cobertura'] != null || $data['cobertura'] != '')
                    {{ number_format($data['cobertura'], 2, ',', '.') }} US$
                @endif 
            </div>
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

