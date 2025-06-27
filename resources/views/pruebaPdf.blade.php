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
            justify-content: center; /* Centra horizontalmente */
            align-items: center; /* Centra verticalmente */
            /* width: 100vw; */
            min-height: 100vh; /* Altura mínima de la ventana */
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
            width: 700px; /* Ancho fijo del contenedor */
            display: flex; /* Activa Flexbox */
            justify-content: space-between; /* Espacio entre los divs */
            border: 1px solid #ccc; /* Borde para visualizar el contenedor */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Sombra suave */
            border-radius: 8px; /* Bordes redondeados */
            overflow: hidden; /* Asegura que los bordes redondeados se vean bien */
         }

         .parent {
            display: flex; /* Activa Flexbox */
            width: 100vw; /* Ancho total de la ventana */
            height: 155px; /* Altura fija */
            background-color: #f4f4f9; /* Fondo claro */
            border: 1px solid #ccc; /* Borde para visualizar el contenedor */
            box-sizing: border-box; /* Incluye el borde en el cálculo del tamaño */
         }

         /* Divs hijos */
         .child {
            flex: 1; /* Cada div ocupa el mismo espacio (50% del ancho del padre) */
            display: flex;
            justify-content: center; /* Centra horizontalmente */
            align-items: center; /* Centra verticalmente */
            text-align: center; /* Alinea el texto al centro */
            font-size: 18px;
            color: #ffffff; /* Texto blanco */
         }

         /* Estilo específico para cada div */
         .left {
            background-color: #00539c; /* Azul oscuro */
         }

         .right {
            background-color: #333333; /* Gris oscuro */
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

    <div style="display: blog; justify-content: center; align-items: center; text-align: center; margin-top: 150px">
        <h1>TARJETA DE AFILIADO</h1>
    </div>

    <div style="position: relative;">
        <div style="display: blog; justify-content: center; align-items: center; text-align: center; margin-top: 30px">
            <img src="{{ public_path('storage/tarjetaAfiliacionBlanca.png') }}" style="width: 100%;" alt="">

        </div>

        <!-- Parte Izquierda -->
        <div style="position: absolute; top: 50px; left: 238px; color: #000000; font-weight: bold">
            {{ $details['code'] }}
        </div>
        <div style="position: absolute; top: 100px; left: 50px; color: #000000; font-weight: bold">
            {{ $details['full_name_con'] }}
        </div>
        <div style="position: absolute; top: 123px; left: 75px; color: #000000; font-weight: bold">
            {{ $details['nro_identificacion_con'] }}
        </div>
        <div style="position: absolute; top: 151px; left: 85px; color: #000000; font-weight: bold">
            {{ $plan }}
        </div>
        <div style="position: absolute; top: 175px; left: 183px; color: #000000; font-weight: bold">
            {{ $details['payment_frequency'] }}
        </div>
        <div style="position: absolute; top: 202px; left: 120px; color: #000000; font-weight: bold">
            US$ {{ $coverage }}
        </div>

        <!-- Parte Derecha -->
        <div style="position: absolute; top: 149px; left: 425px; color: #000000; font-weight: bold">
            {{ date('d-m-Y') }}
        </div>
        <div style="position: absolute; top: 174px; left: 425px; color: #000000; font-weight: bold">
            @if($details['payment_frequency'] == 'MENSUAL')
                {{ date('d-m-Y', strtotime('+1 month')) }}  
            @endif
            @if($details['payment_frequency'] == 'TRIMESTRAL')
                {{ date('d-m-Y', strtotime('+3 month')) }}  
            @endif
            @if($details['payment_frequency'] == 'SEMESTRAL')
                {{ date('d-m-Y', strtotime('+6 month')) }}  
            @endif
            @if($details['payment_frequency'] == 'ANUAL')
                {{ date('d-m-Y', strtotime('+1 year')) }}  
            @endif
        </div>

    </div>

    <div style="display: blog; justify-content: center; align-items: center; text-align: center; margin-top: 10px">
        <div style="width: 600px; display: block; margin: auto;">
            <div style="margin-top: 80px; font-size: 0.9em; font-weight: bold; text-align: center;">
                <p>1. Escanea el código QR para ampliar la información de contactos y paso a paso para la activación de los servicios médicos.</p>
                <p>2. Conserve su Tarjeta de Afiliado cerca de sus documentos personales.</p>
                <p>3. La Tarjeta de Afiliado no es un requisito obligatorio para solicitar el servicio, si usted presenta una eventualidad puede identificarse con su Nombre y Número de Cédula.</p>
            </div>
        </div>
    </div>

    {{-- <div style="page-break-after:always;"></div> --}}

    <div style="display: blog; justify-content: center; align-items: center; text-align: center; margin-top: 140px">
        <img src="{{ public_path('storage/firma-pdf.png') }}" style="width: 35%;" alt="">

    </div>

    <div style="display: blog; justify-content: center; align-items: center; text-align: center; margin-top: 25px">
        <img src="{{ public_path('storage/bannerFooter.png') }}" style="width: 100%;" alt="">
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


