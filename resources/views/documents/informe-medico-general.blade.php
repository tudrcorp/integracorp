<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarjeta de Afiliado</title>

    <style>
        @page {
            margin:0px;
            /* background-color: white; */
        }

        p {
            line-height: 0.5; /* Ajusta este valor según tus necesidades */
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
     
    <div style="position: absolute; top: 45px; left: 50px; margin-top: 0px; padding: 0px; margin-left: 0px">
        <img class="logo-top-left" src="{{ public_path('storage/logoNewPdfTDEC.png') }}" style="width: auto; height: 50px;" alt="">
    </div>

    {{-- Linea horizontal debajo del encabezado --}}
    <div style="position: absolute; top: 78px; left: 59px; height: 2px; background-color: #add8e6; width: 670px; margin: 0; padding: 0;"></div>

    {{-- Línea vertical derecha --}}
    <div style="position: absolute; top: 78px; left: 728px; width: 2px; background-color: #add8e6; height: 100%; display: inline-block;"></div>
  

    <div style="position: absolute; top: 32px; left: 568px; margin-top: 0px; padding: 0px; margin-left: 0px">
        <p class="sin-margen" style="font-size: 30px;">
            <span style="color: #7ab2db; font-size: 22px; font-style: sans-serif; font-family: 'Helvetica', Century, sans-serif;">Informe Médico</span>
        </p>
    </div>

    <div style="
        position: absolute;
        top: 80px;
        right: 70px; /* ← Fija el borde DERECHO */
        text-align: right;
        font-family: 'Helvetica', sans-serif;
        font-size: 15px;
        padding: 4px 8px;
        display: inline-block; /* ← clave: el ancho se ajusta al contenido */
        max-width: 100%; /* evita que se salga del contenedor */
    ">
        Fecha: {{ now()->format('d/m/Y') }}<br>
        Clave de Servicio: 78654-09<br>
        Tipo de Servicio: Atencion Medica Domiciliaria
    </div>

    
    <div style="
        position: absolute;
        top: 140px;
        left: 50px; /* ← Fija el borde DERECHO */
        text-align: left;
        font-family: 'Helvetica', sans-serif;
        font-size: 15px;
        background-color: #ffffff; /* opcional: para ver el tamaño */
        padding: 4px 8px;
        display: inline-block; /* ← clave: el ancho se ajusta al contenido */
        max-width: 100%; /* evita que se salga del contenedor */
    ">
        Nombre y Apellido: Gustavo Camacho<br>
        Cedula de Identidad: 16007868
    </div>

    <div style=" position: absolute; top: 140px; left: 50px; text-align: left; font-family: 'Helvetica', sans-serif; font-size: 15px; background-color: #ffffff; padding: 4px 8px; display: inline-block; max-width: 100%;">
        Nombre y Apellido: Gustavo Camacho<br>
        Cedula de Identidad: 16007868
    </div>

    <div style=" position: absolute; top: 190px; left: 50px; text-align: left; font-family: 'Helvetica', sans-serif; font-size: 15px; background-color: #ffffff; padding: 4px 8px; display: inline-block; max-width: 100%;">
        Motivo de la Consulta:
    </div>
    {{-- Linea horizontal debajo del encabezado --}}
    <div style="position: absolute; top: 205px; left: 210px; height: 2px; background-color: #add8e6; width: 505px; margin: 0; padding: 0;"></div>
    
    <div style="position: absolute; top: 210px; left: 59px; width: 653px; padding: 0; margin: 0;">
        <p style="
            text-align: justify;
            text-justify: inter-word;
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 15px;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            hyphens: auto;
        ">
        Dolor de cabeza muy fuerte
        </p>
    </div>



    <div style=" position: absolute; top: 255px; left: 50px; text-align: left; font-family: 'Helvetica', sans-serif; font-size: 15px; background-color: #ffffff; padding: 4px 8px; display: inline-block; max-width: 100%;">
        Enfermedad Actual:
    </div>

    {{-- Linea horizontal debajo del encabezado --}}
    <div style="position: absolute; top: 269px; left: 190px; height: 2px; background-color: #add8e6; width: 520px; margin: 0; padding: 0;"></div>
    
    <div style="position: absolute; top: 280px; left: 59px; width: 653px; padding: 0; margin: 0;">
        <p style="
            text-align: justify;
            text-justify: inter-word;
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 15px;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            hyphens: auto;
        ">
        Lorem ipsum dolor sit amet consectetur adipisicing elit. Debitis totam minus quis unde voluptate repellendus ratione at molestiae laboriosam laudantium harum, illum saepe, earum eveniet suscipit. Distinctio dignissimos nulla sed.
        </p>
    </div>


    <div style=" position: absolute; top: 375px; left: 50px; text-align: left; font-family: 'Helvetica', sans-serif; font-size: 15px; background-color: #ffffff; padding: 4px 8px; display: inline-block; max-width: 100%;">
        Antecedentes:
    </div>

    {{-- Linea horizontal debajo del encabezado --}}
    <div style="position: absolute; top: 390px; left: 190px; height: 2px; background-color: #add8e6; width: 520px; margin: 0; padding: 0;"></div>
    
    <div style="position: absolute; top: 280px; left: 59px; width: 653px; padding: 0; margin: 0;">
        <p style="
            text-align: justify;
            text-justify: inter-word;
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 15px;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            hyphens: auto;
        ">
        Lorem ipsum dolor sit amet consectetur adipisicing elit. Debitis totam minus quis unde voluptate repellendus ratione at molestiae laboriosam laudantium harum, illum saepe, earum eveniet suscipit. Distinctio dignissimos nulla sed.
        </p>
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

