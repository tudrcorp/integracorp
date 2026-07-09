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

            body {
                margin: 0;
                padding: 0;
                min-height: 100vh;
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
        </style>
    </head>
    <body>
        <div class="cover">
            <img
                class="cover-template-image"
                src="{{ public_path('storage/certificados/tarjeta-afiliado-individual.png') }}"
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

            <div class="cover-field" style="top: 370px; left: 267px;">
                {{ $data['code'] }}
            </div>
            <div class="cover-field" style="top: 406px; left: 118px;">
                {{ $data['name_first_part'] }}
            </div>
            <div class="cover-field" style="top: 420px; left: 118px;">
                {{ $data['name_second_part'] }}
            </div>
            <div class="cover-field" style="top: 440px; left: 88px;">
                {{ $data['ci'] }}
            </div>
            <div class="cover-field" style="top: 468px; left: 98px;">
                {{ $data['plan_tarjeta_etiqueta'] }}
            </div>
            <div class="cover-field" style="top: 494px; left: 203px;">
                {{ $data['frecuencia'] }}
            </div>
            <div class="cover-field" style="top: 520px; left: 138px;">
                {{ $data['cobertura_display'] }}
            </div>
            <div class="cover-field" style="top: 464px; left: 455px;">
                {{ $data['desde'] }}
            </div>
            <div class="cover-field" style="top: 485px; left: 455px;">
                {{ $data['hasta'] }}
            </div>
        </div>
    </body>
</html>
