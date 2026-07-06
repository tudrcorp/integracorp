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
                min-height: 100vh;
            }

            .cover-template-image {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                z-index: 0;
            }

            .cover-field {
                position: absolute;
                z-index: 1;
                margin: 0;
                padding: 0;
                font-weight: bold;
                font-size: 12px;
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

        <!-- Primera página: plantilla completa del carnet -->
        <div class="cover">
            <img
                class="cover-template-image"
                src="{{ public_path('storage/certificados/tarjeta-afiliado.png') }}"
                alt=""
            >
            @if (! empty($data['plan_qr_absolute_path']))
                <div class="cover-field" style="top: {{ $data['plan_qr_top_px'] }}px; right: {{ $data['plan_qr_right_px'] }}px;">
                    <img
                        src="{{ $data['plan_qr_absolute_path'] }}"
                        style="width: {{ $data['plan_qr_size_px'] }}px; height: {{ $data['plan_qr_size_px'] }}px;"
                        alt=""
                    >
                </div>
            @endif
            <div class="cover-field" style="top: 335px; left: 251px;">
                {{ $data['code'] }}
            </div>
            <div class="cover-field" style="top: 354px; left: 115px;">
                {{ $data['name_first_part'] }}<br>{{ $data['name_second_part'] }}
            </div>
            <div class="cover-field" style="top: 402px; left: 80px;">
                {{ $data['ci'] }}
            </div>
            <div class="cover-field" style="top: 426px; left: 95px;">
                {{ $data['plan_tarjeta_etiqueta'] }}
            </div>
            <div class="cover-field" style="top: 423px; left: 440px;">
                {{ $data['desde'] }}
            </div>
            <div class="cover-field" style="top: 450px; left: 195px;">
                {{ $data['frecuencia'] }}
            </div>
            <div class="cover-field" style="top: 443px; left: 440px;">
                {{ $data['hasta'] }}
            </div>
            <div class="cover-field" style="top: 473.5px; left: 130px;">
                {{ $data['cobertura_display'] }}
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

