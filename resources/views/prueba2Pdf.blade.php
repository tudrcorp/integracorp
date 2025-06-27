<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarjeta de Afiliado</title>


    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles

    <style>
        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center; /* Centra horizontalmente */
            align-items: center; /* Centra verticalmente */
            min-height: 100vh; /* Altura mínima de la ventana */
            background-color: #f4f4f9; /* Fondo claro */
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

        /* Divs hijos */
        .child {
            width: 350px; /* Ancho fijo de cada div */
            height: 200px; /* Altura de ejemplo */
            display: flex;
            justify-content: center; /* Centra el contenido horizontalmente */
            align-items: center; /* Centra el contenido verticalmente */
            text-align: center; /* Alineación del texto */
            font-size: 18px;
            color: #ffffff; /* Texto blanco */
        }

        /* Estilo específico para cada div */
        .left {
            background-color: #00539C; /* Azul oscuro */
        }

        .right {
            background-color: #333333; /* Gris oscuro */
        }

    </style>

</head>
<body>

    <div class="container">
        <div class="child left">Div Izquierdo</div>
        <div class="child right">Div Derecho</div>
    </div>
    <div>
        <h1 class="text-sm">Tarjeta de Afiliado</h1>
    </div>


</body>

</html>

