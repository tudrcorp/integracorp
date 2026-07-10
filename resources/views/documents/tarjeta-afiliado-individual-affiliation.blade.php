<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Carnet de afiliado individual</title>

        <style>
            @page {
                margin: 0;
                size: 93mm 33.25mm landscape;
            }

            body {
                margin: 0;
                padding: 0;
            }

            .cover {
                position: relative;
                width: 351px;
                height: 125px;
                overflow: hidden;
            }

            .cover-template-image {
                position: absolute;
                top: 0;
                left: 0;
                width: 351px;
                height: 125px;
            }
        </style>
    </head>
    <body>
        <div class="cover">
            <img
                class="cover-template-image"
                src="{{ public_path('storage/certificados/tarjeta-afiliado-individual-cropped.png') }}"
                alt=""
            >
        </div>
    </body>
</html>
