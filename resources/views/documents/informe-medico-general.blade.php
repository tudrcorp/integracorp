<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarjeta de Afiliado</title>

    <style>
        @page {
            margin: 0px;
            /* background-color: white; */
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
    @php
        $movitoConsulta = "El paciente presenta un historial médico complejo.";
        $movitoConsultaDos = "El paciente presenta un historial médico complejo. Si la variable viene de la base de datos, debería estar disponible aquí. El paciente presenta un historial médico complejo. Si la variable viene de la base de datos, debería estar disponible aquí. El paciente presenta un historial médico complejo. Si la variable viene de la base de datos, debería estar disponible aquí.";
        $impresionDiagnostica = "El paciente presenta un historial médico complejo. Si la variable viene de la base de datos, debería estar disponible aquí. El paciente presenta un historial médico complejo. Si la variable viene de la base de datos, debería estar disponible aquí. El paciente presenta un historial médico complejo. Si la variable viene de la base de datos, debería estar disponible aquí.";

        $datosFila = [
            'Peso(Kg): 56.5',
            'Estatura(Mts): 1.70',
            'IMC: 23.5',
        ];
        $datosPersonales = [
            'NOMBRE Y APELLIDO: Gustavo Alberto Pereira',
            'EDAD: 45 anos',
        ];
        $datosPersonalesDos = [
            'CEDULA: 16007868',
            'TIPO DE SERVICIO: TELEMEDICINA',
        ];

    @endphp

    <!-- Primera página: Imagen de fondo -->
    <div class="cover" style="background-image: url('{{ public_path('storage/telemedicina/informeMedicoTLM.png') }}');">


        <!-- Titulo -->
        {{-- <div style="position: absolute; top: 32px; left: 540px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #26b2ca;
                        font-size: 20px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    Informe Médico
                </span>
            </p>
        </div> --}}

        <!-- Fecha -->
        <div style="position: absolute; top: 32px; left: 265px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #000000; 
                        font-size: 14px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    FECHA: {{ now()->format('d/m/Y') }}
                </span>
            </p>
        </div>

        <!-- Referencia -->
        <div style="position: absolute; top: 80px; left: 430px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <p class="sin-margen" style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #000000; 
                        font-size: 14px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    CLAVE DEL SERVICIO: 76543-098
                </span>
            </p>
        </div>


        <!-- Nombre, Apellido y Edad -->
        <div style="position: absolute; top: 120px; left: 60px; margin-top: 0px; padding: 0px; margin-left: 0px; width: 650px; max-width: 650px; margin: 20px 0;">
            <table style="
                /* El ancho total será la suma de los anchos de las celdas (ajustadas al contenido) /
                / Eliminamos width: 100% y table-layout para permitir el ajuste al contenido /
                border-collapse: collapse;
                font-family: Arial, sans-serif;
                font-size: 10pt;
                ">
                {{-- Única Fila --}}
                <tr style="border: 1px solid #000000;">
                    @foreach ($datosPersonales as $dato)
                    <td style="
                        /* background-color: #ffffff; */
                        padding: 4px; / Padding reducido a 4px /
                        color: #000000;
                        font-size: 12px;
                        font-style: sans-serif;
                        font-family: 'Helvetica', Century, sans-serif;
                        /* border: 1px solid #000000; */
                        text-align: left; / Alineado a la izquierda /
                        vertical-align: top;
                        white-space: nowrap; / Fuerza el contenido a una sola línea y ajusta el ancho de la celda */

                        ">
                         <span style="font-weight: bold; color: #000000; text-transform: uppercase;">{{ $dato }}</span>

                    </td>
                    @endforeach
                </tr>
            </table>
        </div>
        <!-- Cedula y Tipo de Servicio -->
        <div style="position: absolute; top: 150px; left: 60px; margin-top: 0px; padding: 0px; margin-left: 0px; width: 650px; max-width: 650px; margin: 5px 0;">
            <table style="
                /* El ancho total será la suma de los anchos de las celdas (ajustadas al contenido) /
                / Eliminamos width: 100% y table-layout para permitir el ajuste al contenido /
                border-collapse: collapse;
                font-family: Arial, sans-serif;
                font-size: 10pt;
                ">
                {{-- Única Fila --}}
                <tr style="border: 1px solid #000000;">
                    @foreach ($datosPersonalesDos as $dato)
                    <td style="
                        /* background-color: #ffffff; */
                        padding: 4px; / Padding reducido a 4px /
                        color: #000000;
                        font-size: 12px;
                        font-style: sans-serif;
                        font-family: 'Helvetica', Century, sans-serif;
                        /* border: 1px solid #000000; */
                        text-align: left; / Alineado a la izquierda /
                        vertical-align: top;
                        white-space: nowrap; / Fuerza el contenido a una sola línea y ajusta el ancho de la celda */
                        ">
                        <span style="font-weight: bold; color: #000000; text-transform: uppercase;">{{ $dato }}</span>

                    </td>
                    @endforeach
                </tr>
            </table>
        </div>

        {{-- Línea separadora azul (ancho de 650px) --}}
        <div style="position: absolute; top: 180px; left: 60px; margin-top: 0px; padding: 0px; margin-left: 0px; width: 600px; border-top: 2px solid #26b2ca; margin: 15px 0;"></div>

        {{-- Tablas de antecedentes, motivo de consulta --}}
        <div style="position: absolute; top: 200px; left: 60px; width: 600px; max-width: 100%;">

            <!-- Motivo de la Consulta -->
            <div style="width: 600px; max-width: 600px; margin: 20px auto;">
                <table style="
                    width: 600px; /* Ancho fijo de 650px solicitado /
                    border-collapse: collapse;
                    color: #000000;
                    font-size: 12px;
                    font-style: sans-serif;
                    font-family: 'Helvetica', Century, sans-serif;
                    text-align: left; / Alineación base del texto a la izquierda /
                ">
                    {{-- Fila 1: Etiqueta Antecedentes --}}
                    <tr style="border: 1px solid #000000;">
                        <td style="
                            /* background-color: #f0f0f0; */
                            padding: 4px; / Padding reducido a 4px */
                            font-weight: bold;
                            color: #000000;
                            text-transform: uppercase;
                            width: 100%;
                            /* border: 1px solid #000000; */
                            text-align: left;
                        ">
                            <span style="font-weight: bold; color: #000000; text-transform: uppercase;">MOTIVO DE CONSULTA:</span>
                        </td>
                    </tr>

                    {{-- Fila 2: Campo de Texto (Contenido Dinámico) --}}

                    <tr>
                        <td style="
                            /* border: 1px solid #000000; */
                            line-height: 1.4;
                            min-height: 50px;
                            vertical-align: top;
                            text-align: justify; / Justificación del texto /
                            white-space: pre-wrap;
                        ">
                            {{ $movitoConsultaDos }}
                        </td>
                    </tr>

                </table>

            </div>

            <!-- Enfermedad -->
            <div style="width: 600px; max-width: 600px; margin: 20px auto;">
                <table style="
                    width: 600px; /* Ancho fijo de 650px solicitado /
                    border-collapse: collapse;
                    color: #000000;
                    font-size: 12px;
                    font-style: sans-serif;
                    font-family: 'Helvetica', Century, sans-serif;
                    text-align: left; / Alineación base del texto a la izquierda /
                ">
                    {{-- Fila 1: Etiqueta Antecedentes --}}
                    <tr style="border: 1px solid #000000;">
                        <td style="
                            /* background-color: #f0f0f0; */
                            padding: 4px; / Padding reducido a 4px */
                            font-weight: bold;
                            text-transform: uppercase;
                            width: 100%;
                            /* border: 1px solid #000000; */
                            text-align: left;
                        ">
                            <span style="font-weight: bold; color: #000000; text-transform: uppercase;">ENFERMEDAD:</span>

                        </td>
                    </tr>

                    {{-- Fila 2: Campo de Texto (Contenido Dinámico) --}}

                    <tr>
                        <td style="
                            /* border: 1px solid #000000; */
                            line-height: 1.4;
                            min-height: 50px;
                            vertical-align: top;
                            text-align: justify; / Justificación del texto /
                            white-space: pre-wrap;
                        ">
                            {{ $movitoConsulta }}
                        </td>
                    </tr>

                </table>

            </div>

            <!-- Antecedentes -->
            <div style="width: 600px; max-width: 600px; margin: 20px auto;">
                <table style="
                    width: 600px; /* Ancho fijo de 650px solicitado /
                    border-collapse: collapse;
                    color: #000000;
                    font-size: 12px;
                    font-style: sans-serif;
                    font-family: 'Helvetica', Century, sans-serif;
                    text-align: left; / Alineación base del texto a la izquierda /
                ">
                    {{-- Fila 1: Etiqueta Antecedentes --}}
                    <tr style="border: 1px solid #000000;">
                        <td style="
                            /* background-color: #f0f0f0; */
                            padding: 4px; / Padding reducido a 4px */
                            font-weight: bold;
                            text-transform: uppercase;
                            width: 100%;
                            /* border: 1px solid #000000; */
                            text-align: left;
                        ">
                            <span style="font-weight: bold; color: #000000; text-transform: uppercase;">ANTECEDENTES:</span>
                        </td>
                    </tr>

                    {{-- Fila 2: Campo de Texto (Contenido Dinámico) --}}

                    <tr>
                        <td style="
                            /* border: 1px solid #000000; */
                            line-height: 1.4;
                            min-height: 50px;
                            vertical-align: top;
                            text-align: justify; / Justificación del texto /
                            white-space: pre-wrap;
                        ">
                            {{ $movitoConsulta }}
                        </td>
                    </tr>

                </table>

            </div>

            <!-- Medidas Anteropométricas -->
            <div style="width: 600px; max-width: 600px; margin: 20px auto;">
                <div style="
                        padding: 4px;
                        float: left;
                        width: 300px;
                        color: #000000;
                        font-size: 12px;
                        font-style: sans-serif;
                        font-family: 'Helvetica', Century, sans-serif;
                        /* Alineamos el texto a la altura de la tabla (ajustar si es necesario) */
                        padding-top: 9px;
                        box-sizing: border-box;
                    ">
                    <span style="font-weight: bold; color: #000000; text-transform: uppercase;">Medidas Antropométricas:</span>
                </div>
                <div style="float: right; width: 400px;">
                    <table style="
                        width: 400px; /* Ancho fijo de 650px solicitado /
                        border-collapse: collapse;
                        font-family: Arial, sans-serif;
                        font-size: 10pt;
                        table-layout: fixed; / Ayuda a que los anchos de columna sean respetados /
                    ">
                        {{-- Única Fila con Seis Columnas --}}
                        <tr style="border: 1px solid #000000;">
                            @foreach ($datosFila as $indice => $dato)
                            <td style="
                                / El ancho se divide entre las 6 columnas (aprox. 16.66%) /
                                width: 16.66%;
                                background-color: #a1a1a1;

                                padding: 4px; / Padding reducido a 4px /
                                font-weight: bold;
                                color: #ffffff;
                                /* border rounded */
                                border-radius: 8px;
                                text-align: center; / Alineado a la izquierda */
                                vertical-align: top;
                                white-space: nowrap;
    
                                ">
                                {{ $dato }}
                            </td>
                            @endforeach
                        </tr>
                    </table>
                </div>

            </div>

            <!-- Impresion Diagnostica -->
            <div style="width: 600px; max-width: 600px; margin: 40px auto; margin-bottom: 20px;">
                <table style="
                    width: 600px; /* Ancho fijo de 650px solicitado /
                    border-collapse: collapse;
                    color: #000000;
                    font-size: 12px;
                    font-style: sans-serif;
                    font-family: 'Helvetica', Century, sans-serif;
                    text-align: left; / Alineación base del texto a la izquierda /
                ">
                    {{-- Fila 1: Etiqueta Antecedentes --}}
                    <tr style="border: 1px solid #000000;">
                        <td style="
                            /* background-color: #f0f0f0; */
                            padding: 4px; / Padding reducido a 4px */
                            font-weight: bold;
                            text-transform: uppercase;
                            width: 100%;
                            /* border: 1px solid #000000; */
                            text-align: left;
                        ">
                            <span style="font-weight: bold; color: #000000; text-transform: uppercase;">IMPRESION DIAGNOSTICA:</span>
                        </td>
                    </tr>

                    {{-- Fila 2: Campo de Texto (Contenido Dinámico) --}}

                    <tr>
                        <td style="
                            /* border: 1px solid #000000; */
                            line-height: 1.4;
                            min-height: 50px;
                            vertical-align: top;
                            text-align: justify; / Justificación del texto /
                            white-space: pre-wrap;
                        ">
                            {{ $impresionDiagnostica }}

                        </td>
                    </tr>

                </table>

            </div>

            <!-- Plan Terapeutico -->
            <div style="position: absolute; top: 440px; left: 0px; width: 600px; margin: 0 auto;">
    
                <!-- Contenedor principal para definir el ancho total (600px) -->
                <table style="width: 600px;">
                    <tbody style="
                        color: #000000;
                        font-size: 12px;
                        font-style: sans-serif;
                        font-family: 'Helvetica', Century, sans-serif;">

                        <tr>
                            <td colspan="2" style="padding-bottom: 10px; font-weight: bold;">
                                PLAN TERAPÉUTICO:
                            </td>
                        </tr>

                        <tr>
                            <td style="
                                padding: 5px;
                                font-weight: bold;
                                color: #ffffff;
                                background-color: #26b2ca;
                                border-bottom: 1px solid #CCCCCC;
                                border-radius: 8px;
                                ">
                                MEDICAMENTOS
                            </td>
    
                            <td style="
                                padding: 5px;
                                color: #ffffff;
                                font-weight: bold;
                                background-color: #26b2ca;
                                border-bottom: 1px solid #CCCCCC;
                                border-radius: 8px;
                            ">
                                INDICACIONES
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li>Paracetamol 500mg (dosis única)</li>
                                    <li>Loratadina 10mg (alergias)</li>
                                

                                </ul>
                            </td>
                            <td>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li>Paracetamol 500mg (dosis única)</li>
                                    <li>Loratadina 10mg (alergias)</li>
                                    

                                </ul>
                            </td>
                        </tr>

                        
                            <tr>
                                <td colspan="2" style=" padding-top: 30px; padding-bottom: 10px; font-weight: bold;">
                                    PARACLINICOS:
                                </td>
                            </tr>

    
                        </tr>
                        <tr>
                            <td style="
                                padding: 5px;
                                font-weight: bold;
                                color: #ffffff;
                                background-color: #26b2ca;
                                border-bottom: 1px solid #CCCCCC;
                                border-radius: 8px;
                                ">
                                LABORATORIOS
                            </td>

                            <td style="
                                padding: 5px;
                                color: #ffffff;
                                font-weight: bold;
                                background-color: #26b2ca;
                                border-bottom: 1px solid #CCCCCC;
                                border-radius: 8px;
                            ">
                                EXAMENES
                            </td>
                        </tr>
                        <tr>
                            <td style="
                                
                                ">
                                <!-- La lista de medicamentos crecerá con respecto a la cantidad de líneas -->
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li>Paracetamol 500mg (dosis única)</li>
                                    <li>Loratadina 10mg (alergias)</li>
                                    <li>Suero Oral (hidratación)</li>
                                </ul>

                            </td>

                            <td style="
                                
                                ">
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li>Paracetamol 500mg (dosis única)</li>
                                    <li>Loratadina 10mg (alergias)</li>
                                    <li>Suero Oral (hidratación)</li>
                                </ul>

                            </td>
                        </tr>

                    </tbody>
                </table>
    
    
            </div>

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

