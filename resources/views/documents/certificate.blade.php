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
            width: 100%; /* Ancho total */
            border-collapse: separate; /* Necesario para bordes redondeados */
            border-spacing: 0; /* Elimina el espacio entre celdas */
            margin: 0; /* Centra la tabla */
            max-width: 800px; /* Ancho máximo */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Sombra suave */
            font-size: 10px;
        }


        /* Separación entre filas */


        /* Efecto hover en las filas */
        tbody tr:hover {
            background-color: #d9edff; /* Cambia el color al pasar el cursor */
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

        .titulos_table_uno{
            color: #575757;
            font-size: 12px;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            font-style: sans-serif;
            font-family: 'Helvetica', Century, sans-serif;

        }

        .contenido_table_uno{
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

        <!-- TITULO 1 -->
        <div style="position: absolute; top: 60px; left: 60px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <!-- Titulo Uno-->
            <p style="font-size: 30px;">
                <span style="
                        font-weight: bold;
                        color: #26b2ca;
                        font-size: 16px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    CERTIFICADO DE AFILIACIÓN
                </span>
            </p>

            <!-- Tabla Informacionn Principal-->
            <div style="width: 600px; max-width: 600px; margin: -20px auto;">
                <table class="table_info_ti">
                    <tbody class="tb_table_info_ti">
                        <tr class="tr_table_info_ti">
                            <td class="titulos_table_uno">Contratante:</td>
                            <td class="contenido_table_uno">{{ $pagador['name'] }}</td>
                            <td class="titulos_table_uno" style="font-weight: bold">Agente:</td>
                            <td class="contenido_table_uno">{{ $pagador['agente_agencia'] }}</td>
                        </tr>
                        <tr class="tr_table_info_ti">
                            <td class="titulos_table_uno" style="font-weight: bold">Código de Afiliación:</td>
                            <td class="contenido_table_uno">{{ $pagador['code'] }}</td>
                            <td class="titulos_table_uno" style="font-weight: bold">Tarifa Anual:</td>
                            <td class="contenido_table_uno">US$ {{ number_format($pagador['tarifa_anual'], 2, ',', '.') }}</td>
                        </tr>
                        <tr class="tr_table_info_ti">
                            <td class="titulos_table_uno" style="font-weight: bold">Plan:</td>
                            <td class="contenido_table_uno">{{ $pagador['plan'] }}</td>
                            <td class="titulos_table_uno" style="font-weight: bold">Frecuencia de Pago:</td>
                            <td class="contenido_table_uno">{{ $pagador['frecuencia_pago'] }}</td>
                        </tr>
                        <tr class="tr_table_info_ti">
                            <td class="titulos_table_uno" style="font-weight: bold">Fecha de Afiliación:</td>
                            <td class="contenido_table_uno">{{ $pagador['fecha_afiliacion'] }}</td>
                            <td class="titulos_table_uno" style="font-weight: bold">Tarifa Periodo:</td>
                            <td class="contenido_table_uno">US$ {{ number_format($pagador['tarifa_periodo'], 2, ',', '.') }}</td>
                        </tr>
                        <tr class="tr_table_info_ti">
                            <td class="titulos_table_uno" style="font-weight: bold">Vigencia:</td>
                            <td class="contenido_table_uno">
                                <p class="contenido_table_uno">Desde: {{ $pagador['fecha_vigencia'] }}</p>
                                <p class="contenido_table_uno">Hasta: {{ $pagador['fecha_vigencia_final'] }}</p>


                            </td>
                            <td class="titulos_table_uno">Periodo Facturado:</td>
                            <td class="contenido_table_uno">
                                <p class="contenido_table_uno">Desde: {{ $pagador['fecha_vigencia'] }}</p>
                                @php
                                    use Carbon\Carbon;
                                    use Illuminate\Support\Facades\Log;

                                        if($pagador['fecha_vigencia'] != '') {

                                            $fecha = $pagador['fecha_vigencia'];

                                            Log::info('fecha_vigencia: ' . $pagador['fecha_vigencia']);
                                            Log::info('fecha_vigencia_final: ' . $pagador['fecha_vigencia_final']);

                                            if($pagador['frecuencia_pago'] == 'MENSUAL'){
                                                Log::info('frecuencia_pago: ' . $pagador['frecuencia_pago']);
                                                $fechaVigenciaHasta = Carbon::createFromFormat('d/m/Y', $pagador['fecha_vigencia'])->addMonths(1)->format('d/m/Y');
                                            }
                                            if($pagador['frecuencia_pago'] == 'TRIMESTRAL'){
                                                Log::info('frecuencia_pago: ' . $pagador['frecuencia_pago']);
                                                $fechaVigenciaHasta = Carbon::createFromFormat('d/m/Y', $pagador['fecha_vigencia'])->addMonths(3)->format('d/m/Y');
                                            }
                                            if($pagador['frecuencia_pago'] == 'SEMESTRAL'){
                                                Log::info('frecuencia_pago: ' . $pagador['frecuencia_pago']);
                                                $fechaVigenciaHasta = Carbon::createFromFormat('d/m/Y', $pagador['fecha_vigencia'])->addMonths(6)->format('d/m/Y');
                                            }
                                            if($pagador['frecuencia_pago'] == 'ANUAL'){
                                                Log::info('frecuencia_pago: ' . $pagador['frecuencia_pago']);
                                                $fechaVigenciaHasta = Carbon::createFromFormat('d/m/Y', $pagador['fecha_vigencia'])->addYear()->format('d/m/Y');

                                            }

                                        }else{
                                            $fechaVigenciaHasta = '';
                                        }
                                        Log::info('fecha_vigencia_hasta: ' . $fechaVigenciaHasta);
                                @endphp
                                <p class="contenido_table_uno">Hasta: {{ $fechaVigenciaHasta }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Titulo Dos-->
            <p class="sin-margen" style="font-size: 30px; margin-bottom: 25px;">

                <span style="
                        font-weight: bold;
                        color: #26b2ca;
                        font-size: 16px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                        text-transform: uppercase;
                    ">
                    DATOS DE AFILIADO Y BENEFICIARIOS
                </span>
            </p>

            @php
            // Simulación de datos de ejemplo
            $datosTabla = [
            ['NOMBRE Y APELLIDO', 'DOCUMENTO DE IDENTIDAD', 'FECHA DE NACIMIENTO', 'PARENTESCO'],
            ];

            $colorFondoGris = '#f7f7f7';
            $colorFondoBlanco = '#ffffff';
            $colorBorde = '#cccccc';
            $colorFondoEncabezado = '#e0e0e0';

            @endphp

            <!-- Tabla Afiliados -->
            <div style="width: 100%; max-width: 600px; ">

                <table style="
                            width: 600px;
                            border-collapse: collapse;
                            font-family: Arial, sans-serif;
                            font-size: 12px;
                            margin: -20px auto;
                        ">

                    {{-- Encabezado de la Tabla --}}
                    <thead style="background-color: #b5b5b5;">
                        <tr>
                            @foreach ($datosTabla[0] as $header)
                            <th style="
                                        color: #ffffff;
                                        border: 1px solid {{ $colorBorde }};
                                        padding: 4px; 
                                        text-align: left;
                                        font-weight: bold;
                                        text-transform: uppercase;
                                    ">
                                {{ $header }}
                            </th>
                            @endforeach
                        </tr>
                    </thead>

                    {{-- Cuerpo de la Tabla --}}
                    <tbody>
                        {{-- Iteramos sobre las filas de datos, omitiendo el encabezado (índice 0) --}}
                        @foreach ($afiliates as $index => $celda)
                        @php
                        // Determinamos el color de fondo: Gris para filas pares (empezando en 0), Blanco para impares
                        // Usamos (index + 1) % 2 == 0 para alternar colores
                        $backgroundColor = ($index % 2 == 0) ? $colorFondoBlanco : $colorFondoGris;
                        @endphp

                        <tr style="background-color: {{ $backgroundColor }};">
                            <td style="
                                            border: 1px solid {{ $colorBorde }};
                                            padding: 4px;
                                            text-align: left;
                                            text-transform: uppercase;
                                        ">
                                {{ $celda['full_name'] }}
                            </td>
                            <td style="
                                            border: 1px solid {{ $colorBorde }};
                                            padding: 4px;
                                            text-align: left;
                                        ">
                                {{ $celda['nro_identificacion'] }}
                            </td>
                            <td style="
                                            border: 1px solid {{ $colorBorde }};
                                            padding: 4px;
                                            text-align: left;
                                        ">
                                {{ $celda['birth_date'] }}
                            </td>
                            <td style="
                                            border: 1px solid {{ $colorBorde }};
                                            padding: 4px;
                                            text-align: left;
                                            text-transform: uppercase;
                                        ">
                                {{ $celda['relationship'] }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>



            </div>

            <!-- Titulo Tres-->
            <p class="sin-margen" style="font-size: 30px; margin-bottom: 5px;">
                <span style="
                    font-weight: bold;
                    color: #26b2ca;
                    font-size: 16px;
                    font-style: sans-serif; 
                    font-family: 'Helvetica', Century, sans-serif; 
                    text-transform: uppercase;
                ">
                    BENEFICIOS DEL PLAN SELECCIONADO
                </span>
            </p>

            @php
            // Colores base:
            $colorFondoGris = '#ffffff';
            $colorFondoBlanco = '#ffffff';
            $colorBorde = '#cccccc';
            $colorFondoEncabezado = '#e0e0e0';

            @endphp

            <!-- Tabla Beneficios -->
            <div style="width: 100%; max-width: 600px;">

                <table style="
                            width: 600px;
                            border-collapse: collapse;
                            font-size: 9px;
                            font-style: sans-serif;
                            font-family: 'Helvetica', Century, sans-serif;
                        ">
                    {{-- Cuerpo de la Tabla --}}
                    <tbody>
                        {{-- Iteramos sobre las filas de datos, omitiendo el encabezado (índice 0) --}}
                        @foreach ($beneficios_table as $index => $fila)
                        <tr>
                            {{-- Columna 1: Descripción --}}
                            <td style="
                                    border-bottom: 1px solid {{ $colorBorde }};
                                    padding: 0px;
                                    text-align: left;
                                ">
                                {{ $fila }}
                            </td>

                            {{-- Columna 2: Ícono Unicode (Centrado) --}}
                            <td style="
                                        border-bottom: 1px solid {{ $colorBorde }};
                                        padding: 8px;
                                        text-align: right; 
                                        /* Aplicamos el color y tamaño de fuente para simular el ícono */
                                        font-size: 9px; 
                                        font-weight: bold;
                                    ">
                                @if($fila == "EMERGENCIAS MÉDICAS POR PATOLOGIAS LISTADAS")
                                    <span style="font-size: 14px; font-weight: bold;">US$ {{ number_format($pagador['cobertura'], 2, ',', '.') }}</span>
                                @elseif($fila == "ASISTENCIA MÉDICA POR ACCIDENTES")
                                    <span style="font-size: 14px; font-weight: bold;">US$ {{ number_format($pagador['cobertura'], 2, ',', '.') }}</span>
                                @else
                                    <img src="{{ public_path('storage/certificados/check-beneficios.png') }}" style="width: 12px; height: 12px;" alt="">
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        @if($pagador['plan_id'] == 3)
                            <tr>
                                <td colspan="2" style="font-size: 8px;
                                                    text-align: justify; 
                                                    padding: 2px; 
                                                    font-style: sans-serif;
                                                    font-family: 'Helvetica', Century, sans-serif;
                                                ">
                                    LUEGO DEL ANÁLISIS TÉCNICO Y MÉDICO DE LA SOLICITUD, QUEDA EXCLUIDO DEL BENEFICIO DE EMERGENCIAS MÉDICAS POR PATOLOGÍAS LISTADAS, TODA OCURRENCIA RELACIONADA Y/O A CONSECUENCIA DE LAS PREEXISTENCIAS DECLARADAS O NO. <br> ANTE ALGÚN EVENTO INESPERADO ASOCIADO A LAS PREEXISTENCIAS DECLARADAS Y EN CONOCIMIENTO O NO, SERÁ ESTABILIZADO EN SU DOMICILIO EN EL MOMENTO QUE SEA REQUERIDO.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        </div>



        <div style="position: absolute; top: 930px; left: 60px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <img src="{{ public_path('storage/certificados/firmaHC-Certificados.png') }}" style="width: 180px; height: 70px;" alt="">
        </div>


        <!-- Firma Humberto Sanchez -->
        {{-- <div style="position: absolute; top: 970px; left: 60px; margin-top: 0px; padding: 0px; margin-left: 0px">
            <div style="text-align: center;">
                <p class="sin-margen" style="font-size: 30px; line-height: 12px;">
                    <span style="
                        font-weight: bold;
                        font-size: 12px; 
                        font-style: sans-serif; 
                        font-family: 'Helvetica', Century, sans-serif; 
                    ">
                        HUMBERTO SANCHEZ<br>
                        Director de Negocios
                    </span>
                </p>

            </div> --}}
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

