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
            bottom: 50px;
            left: 50px;
            right: 50px;
            align-items: center;
            text-align: center;
        }

    </style>


</head>
<body>
    <!-- Content here -->
    <div style="position: absolute; top: 50px; left: 50px; width: 650px;">
        <table style="width: 650px; border-collapse: collapse; border-spacing: 0; box-shadow: none; max-width: none;">
            <tr>
                <td style="width: 460px; vertical-align: top; text-align: left; padding: 0; background-color: transparent;">
                    <p class="sin-margen" style="margin: 0 0 5px; font-size: 30px;">
                        <span style="font-weight: bold; color: #052F60; font-size: 25px; font-style: italic;">CARTA DE
                        </span>
                        <span style="font-weight: bold; color: #7ab2db; font-size: 25px; font-style: italic;">BIENVENIDA
                        </span>
                    </p>
                    <p class="sin-margen" style="margin: 0 0 3px; font-size: 1.2rem;">
                        <span style="font-weight: normal; color: #000000; font-family: 'Century Regular', Century, sans-serif; font-style: italic">
                            Agencia:
                        </span>
                    </p>
                    <p class="sin-margen" style="margin: 0; font-size: 1.2rem;">
                        <span style="font-family: 'Century Regular', Century, sans-serif; font-style: italic">
                            {{ $name }}
                        </span>
                    </p>
                </td>
                <td style="width: 210px; vertical-align: top; text-align: right; padding: 0; background-color: transparent;">
                    <img src="{{ public_path('image/logo-tdg-carta-bienvenida.png') }}" style="display: block; width: 210px; height: auto; margin-top: 2px; margin-left: auto;" alt="">
                </td>
            </tr>
        </table>
    </div>


    <div style="position: absolute; top: 210px; left: 50px; width: 650px;">
        <p style="margin: 0 0 14px; text-align: justify; font-size: 1.2rem; font-weight: normal; font-family: 'Century Regular', Century, sans-serif; font-style: italic">
            En nombre de todo el equipo que integra Tu Doctor Group queremos agradecerles por permitirnos formar parte de su portafolio de productos, y a través de ustedes poder brindar cuidados especializados a nuestros clientes en común.
        </p>
        <p style="margin: 0 0 14px; text-align: justify; font-size: 1.2rem; font-weight: normal; font-family: 'Century Regular', Century, sans-serif; font-style: italic">
            Le informamos que ha sido registrado satisfactoriamente y puede identificarse con el código <span style="font-weight: bold; color: #014886">{{ $code }}</span>.
        </p>
        <p style="margin: 0; text-align: justify; font-size: 1.2rem; font-weight: normal; font-family: 'Century Regular', Century, sans-serif; font-style: italic">
            Puede contar con nuestro apoyo para cualquier inquietud que le pueda surgir.
        </p>
    </div>

    <footer>
        <img src="{{ public_path('storage/firma_sol_dos.png') }}" style="width: 35%; margin-bottom: 20px" alt="">
        <img src="{{ public_path('storage/footer-carta-de-bienvenida.png') }}" style="width: 100%; margin-top: 5px" alt="">
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

