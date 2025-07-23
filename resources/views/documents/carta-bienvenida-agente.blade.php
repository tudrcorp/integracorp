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
    {{-- <div class="cover" style="background-image: url('{{ public_path('storage/carta-bienvenida-agente-agencia.jpg') }}');">


    <div style="position: absolute; top: 0px; left: 0px; margin-top: 15px; padding: 20px; margin-left: 20px">
        <p class="sin-margen" style="margin-bottom: 5px; font-size: 18px;">
            <span style="font-weight: bold; color: #305B93; font-size: 25px; font-style: italic;">AGT-000{{ $id }}</span>
        </p>
    </div>

    </div> --}}
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

        <!-- Content here -->
        <div style="position: absolute; top: 0px; left: 33px; margin-top: 15px; padding: 20px; margin-left: 20px">
            <p class="sin-margen" style="margin-bottom: 5px; font-size: 30px;">
                <span style="font-weight: bold; color: #052F60; font-size: 25px; font-style: italic;">CARTA DE 
                </span>
                <span style="font-weight: bold; color: #7ab2db; font-size: 25px; font-style: italic;">BIENVENIDA
                </span>
            </p>
            <p class="sin-margen" style=" margin-bottom: 3px; font-size: 1.2rem;">
                <span style="font-weight: normal; color: #000000; font-family: 'Century Regular', Century, sans-serif; font-style: italic">
                    Agente:
                </span>
            </p>
            <p class="sin-margen" style="font-size: 1.2rem;">
                <span style="font-family: 'Century Regular', Century, sans-serif; font-style: italic">
                    {{ $name }}
                </span>
            </p>
        </div>


        <div style="position: absolute; top: 0px; left: 530px; margin-top: 15px; padding: 20px; margin-left: 20px">
            <div>
                <img class="logo-bottom-left" src="{{ public_path('storage/logo1-pdf.png') }}" style="width: 150px; height: 70px;" alt="">
            </div>
        </div>

        {{-- <div style="position: absolute; top: 100px; left: 180px; margin-top: 15px; padding: 5px; margin-left: 20px; margin-bottom: 20px; color: #014886">

            <p style="text-align: center; margin-bottom: 5px; font-weight: bold; font-size: 2rem; text-transform: uppercase;">CARTA DE BIENVENIDA</p>
        </div> --}}



        <div style="position: absolute; top: 120px; left: 50px; margin-top: 40px; padding: 20px; width: 650px;">
            <p style="text-align: justify; font-size: 1.2rem; font-weight: normal; font-family: 'Century Regular', Century, sans-serif; font-style: italic">
                En nombre de todo el equipo que integra Tu Doctor Group queremos agradecerles por permitirnos formar parte de su portafolio de productos, y a través de ustedes poder brindar cuidados especializados a nuestros clientes en común.
            </p>
            <p style="text-align: justify; font-size: 1.2rem; font-weight: normal; font-family: 'Century Regular', Century, sans-serif; font-style: italic">
                Le informamos que ha sido registrador satisfactoriamente y puede identificarse con el codigo <span style="font-size: bold; color: #014886">AGT-000{{ $id }}</span>.
            </p>
            <p style="text-align: justify; font-size: 1.2rem; font-weight: normal; font-family: 'Century Regular', Century, sans-serif; font-style: italic">
                Puede contar con nuestro apoyo para cualquier inquietud que le pueda surgir
            </p>

        </div>


        <footer>
            <img src="{{ public_path('storage/firma-sra-sol.png') }}" style="width: 35%; margin-bottom: 20px" alt="">
            <img src="{{ public_path('storage/footer-carta-bienvenida.png') }}" style="width: 100%; margin-top: 5px" alt="">
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



</body>
</html>



