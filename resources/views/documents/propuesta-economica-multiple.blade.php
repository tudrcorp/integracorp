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

    </style>
</head>
<body>
    @livewire('portada-cotizacion-individual', ['name' => $details_generals['name']])

    @livewire('propuesta-economica.propuesta-economica-page-2')

    @if($data_inicial != null)

        @livewire(
        'planes-cotizacion-individual',
        [
            'data' => $data_inicial,
            'name' => $details_generals['name'],
            'name_user' => $name_user

        ]
    )

    @endif

    @if($data_ideal != null)

        @livewire(
            'planes-cotizacion-individual-ideal',

            [
                'data' => $data_ideal,
                'name' => $details_generals['name'],
                'name_user' => $name_user,
                'number_control' => Str::contains($details_generals['code'], 'COT-IND-') ?
                    str_replace('COT-IND-', '', $details_generals['code']) :
                    str_replace('COT-CORP-', '', $details_generals['code'])

            ]
        )

    @endif

    @if($data_especial != null)

            @livewire(
            'planes-cotizacion-individual-especial',

            [
                'data' => $data_especial,
                'name' => $details_generals['name'],
                'name_user' => $name_user,
                'number_control' => Str::contains($details_generals['code'], 'COT-IND-') ?
                    str_replace('COT-IND-', '', $details_generals['code']) :
                    str_replace('COT-CORP-', '', $details_generals['code'])

            ]
        )
        @livewire('propuesta-economica.propuesta-economica-plan-especial')

    @endif



    @livewire('propuesta-economica.propuesta-economica-page-4')
</body>
</html>

