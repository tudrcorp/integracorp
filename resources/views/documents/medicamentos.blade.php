<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarjeta de Afiliado</title>

    <style>
        @page {
            margin: 10px;
            /* Sin margen */
            size: A4 landscape;
            /* Tamaño de la hoja A4 en landscape */
        }

        p {
            line-height: 0.5;
            /* Ajusta este valor según tus necesidades */
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
            width: 600px;
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


        footer {
            display: flex;
            position: fixed;
            bottom: 0px;
            left: 0px;
            right: 0px;
            align-items: center;
            text-align: center;
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

        .texto-dinamico {
            border: 1px solid #000000;

            /* Propiedades clave para el crecimiento y el formato del texto */
            line-height: 1.4;

            min-height: 50px;
            /* Altura mínima inicial */
            vertical-align: top;
            text-align: justify;
            /* Justificación del texto */

            /* pre-wrap respeta saltos de línea y ajusta el texto automáticamente */
            white-space: pre-wrap;
        }

    </style>


</head>

<body>

    <!-- Primera página: Imagen de fondo -->
    <div class="cover" style="background-image: url('{{ public_path('storage/telemedicina/medicamentos.png') }}'); ">


        <!-- Fecha Izquierda -->
        <div style="position: absolute; top: -29px; left: 255px; margin-top: 0px; padding: 10px; margin-left: 0px; height: 50px;">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1; 
                        font-size: 13px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['fecha'] }}
                </span>
            </p>
        </div>
        <!-- Fecha Derecha -->
        <div style="position: absolute; top: -29px; left: 779px; margin-top: 0px; padding: 10px; margin-left: 0px; height: 50px;">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1; 
                        font-size: 13px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['fecha'] }}
                </span>
            </p>
        </div>


        <!-- Clave del servicio Izaquierda -->
        <div style="position: absolute; top: 17.5px; left: 410px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #000000; 
                        font-size: 16px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['code_reference'] }}
                </span>
            </p>
        </div>
        <!-- Clave del servicio Derecha -->
        <div style="position: absolute; top: 17.5px; left: 950px; margin-top: 0px; padding: 0px; margin-left: 0px">

            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #000000; 
                        font-size: 16px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['code_reference'] }}
                </span>
            </p>
        </div>


        <!-- Nombre Izquierda -->
        <div style="position: absolute; top: 43.5px; left: 183px; margin-top: 0px; padding: 10px; margin-left: 0px; height: 50px;">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1; 
                        font-size: 13px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['name_patiente'] }}
                </span>
            </p>
        </div>
        <!-- Nombre Derecha -->
        <div style="position: absolute; top: 43.5px; left: 722px; margin-top: 0px; padding: 10px; margin-left: 0px; height: 50px;">

            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1; 
                        font-size: 13px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['name_patiente'] }}
                </span>
            </p>
        </div>


        <!-- Cedula Izquierda -->
        <div style="position: absolute; top: 65px; left: 95px; margin-top: 0px; padding: 10px; margin-left: 0px; height: 50px;">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1; 
                        font-size: 13px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['ci_patiente'] }}
                </span>
            </p>
        </div>
        <!-- Cedula Derecha -->
        <div style="position: absolute; top: 65px; left: 635px; margin-top: 0px; padding: 10px; margin-left: 0px; height: 50px;">


            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1; 
                        font-size: 13px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['ci_patiente'] }}
                </span>
            </p>
        </div>


        <!-- Edad Izquierda -->
        <div style="position: absolute; top: 85px; left: 80px; margin-top: 0px; padding: 10px; margin-left: 0px; height: 50px;">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1; 
                        font-size: 13px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['age_patiente'].' Años' }}
                </span>
            </p>
        </div>
        <!-- Edad Derecha -->
        <div style="position: absolute; top: 85px; left: 620px; margin-top: 0px; padding: 10px; margin-left: 0px; height: 50px;">

            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1; 
                        font-size: 13px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['age_patiente'].' Años' }}
                </span>
            </p>
        </div>


        <!-- Lista de medicamentos -->
        <div style="position: absolute; top: 200px; left: 36px; margin-top: 0px; padding: 0px; margin-left: 0px;">
            <ul style="
                margin: 0; 
                padding-left: 20px;
                font-weight: bold;
                color: #a1a1a1;
                font-size: 14px;
                font-style: sans-serif;
                font-family: 'Helvetica', Century, sans-serif;
                text-transform: uppercase;
            ">
                @foreach ($data['medicationsArr'] as $item)
                    <li>
                        {{ $item['medicines'] }}
                    </li>
                @endforeach

            </ul>
        </div>
        <!-- Lista de indicaciones -->
        <div style="position: absolute; top: 200px; left: 580px; margin-top: 0px; padding: 0px; margin-left: 0px; width: 480px;">
            <ul style="
                margin: 0; 
                padding-left: 20px;
                font-weight: bold;
                color: #a1a1a1;
                font-size: 14px;
                font-style: sans-serif;
                font-family: 'Helvetica', Century, sans-serif;
                text-transform: uppercase;
            ">
                @foreach ($data['medicationsArr'] as $item)
                <li>
                    {{ $item['indications'] }}
                </li>
                @endforeach


            </ul>
        </div>


        <!-- Firma del medico izquierda -->
        <div style="position: absolute; top: 530px; left: 185px; margin-top: 0px; padding: 0px; margin-left: 0px; width: auto;">
            <img src="{{ public_path('storage/firma-pdf.png') }}" style="width: 150px; height: 70px;" alt="">
        </div>
        <!-- Firma del medico derecha -->
        <div style="position: absolute; top: 530px; left: 720px; margin-top: 0px; padding: 0px; margin-left: 0px; width: auto;">
            <img src="{{ public_path('storage/firma-pdf.png') }}" style="width: 150px; height: 70px;" alt="">
        </div>


        <!-- CM izquierda -->
        <div style="position: absolute; top: 614.5px; left: 195px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1;
                        font-size: 14px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['code_cm'] }}
                </span>
            </p>
        </div>
        <!-- MPPS Izquierfda -->
        <div style="position: absolute; top: 614.5px; left: 295px; margin-top: 0px; padding: 0px; margin-left: 0px">

            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1;
                        font-size: 14px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['code_mpps'] }}
                </span>
            </p>
        </div>
        <!-- CM derecha -->
        <div style="position: absolute; top: 622px; left: 730px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1;
                        font-size: 14px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['code_cm'] }}
                </span>
            </p>
        </div>
        <!-- MPPS derecha -->
        <div style="position: absolute; top: 622px; left: 830px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #a1a1a1;
                        font-size: 14px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    {{ $data['code_mpps'] }}
                </span>
            </p>
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

