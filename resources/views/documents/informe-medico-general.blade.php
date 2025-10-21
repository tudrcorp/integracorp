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


    </style>


</head>

<body>
    <!-- Primera página: Imagen de fondo -->
    <div class="cover" style="background-image: url('{{ public_path('storage/telemedicina/fondoInforme.png') }}');">


        <div style="position: absolute; top: 32px; left: 568px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="color: #7ab2db; font-size: 22px; font-style: sans-serif; font-family: 'Helvetica', Century, sans-serif;">Informe Médico</span>
            </p>
        </div>

        @php
        // ** SIMULACIÓN DE DATOS **
        // Asume que esta variable viene de tu componente o controlador
        $movitoConsulta = "El paciente presenta un historial médico complejo.SDVDSNVKDSNVDKSSDJVBKJVVKEGVJEWGJESGFJDSHGFSJDHFGSDJHFDSGJDHSFGDSJHFGESJHFSDGFJHSFGDSJHFGDSJHFGDSJFHDGSFJHDSGFDJSHFGSDJHFGSDJHFGDSJHFDSGJSHFGSJHGFSD";

        // Si la variable viene de la base de datos, debería estar disponible aquí.
        // Ejemplo: $antecedentesTexto = $this->record->antecedentes_medicos;

        @endphp


        <div style="position: absolute; top: 200px; left: 60px; width: 600px; max-width: 100%; margin: 10px 0;">

            <table style="
            width: 650px;

            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 10pt;
            ">
                {{-- Fila 1: Etiqueta Antecedentes --}}
                <tr style="border: 1px solid #000000;">
                    <td style="
            padding: 2px 2px;
            font-weight: bold;
            text-transform: uppercase;
            width: 100%;
            border: 1px solid #000000;
            ">
                        Motivo de la Consulta
                    </td>
                </tr>

                {{-- Fila 2: Campo de Texto (Contenido Dinámico) --}}
                <tr>
                    <td style="
                        padding: 2px 2px;
                        font-weight: bold;
                        text-transform: uppercase;
                        width: 100%;
                        border: 1px solid #000000;
                        /* pre-wrap respeta saltos de línea y ajusta el texto automáticamente */
                        white-space: pre-wrap;
                    ">
                        {{ $movitoConsulta }}

                    </td>
                </tr>
            </table>


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

