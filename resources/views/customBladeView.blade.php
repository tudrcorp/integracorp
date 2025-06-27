<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Vista Personalizada</title>
    <style>
        /* A4 size in pixels at 96dpi: 794px x 1123px approx */
        body, html {
            margin: 0;
            padding: 0;
            width: 794px;
            height: 1123px;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
        }
        .page {
            width: 794px;
            height: 1123px;
            padding: 20px;
            box-sizing: border-box;
            border: 1px solid #000;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        header img {
            width: 100px;
            height: auto;
        }
        h1.title {
            color: blue;
            font-weight: bold;
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 30px;
        }
        .centered-div {
            width: 255px;
            height: 155px;
            margin: 0 auto;
            display: flex;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        .left-div, .right-div {
            flex: 1;
            padding: 10px;
            box-sizing: border-box;
        }
        .left-div header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .left-div header img {
            width: 40px;
            height: 40px;
        }
        .left-div header .code {
            font-weight: bold;
            font-size: 1rem;
        }
        .left-div ul, .right-div ul {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 0.9rem;
        }
        .right-div {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .right-div ul {
            text-align: left;
            flex: 1;
        }
        .right-div img.qr {
            width: 60px;
            height: 60px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="page">
        <header>
            <img src="{{ asset('images/logo1.png') }}" alt="Logo 1" />
            <img src="{{ asset('images/logo2.png') }}" alt="Logo 2" />
        </header>

        <h1 class="title">Título Resaltante en Azul</h1>

        <div class="centered-div">
            <div class="left-div">
                <header>
                    <img src="{{ asset('images/logo1.png') }}" alt="Logo Izquierda" />
                    <div class="code">Código1234</div>
                </header>
                <ul>
                    <li>Nombre: Juan</li>
                    <li>Apellido: Pérez</li>
                    <li>Cédula: 12345678</li>
                    <li>Edad: 30</li>
                </ul>
            </div>
            <div class="right-div">
                <ul>
                    <li>Nombre: Juan</li>
                    <li>Apellido: Pérez</li>
                    <li>Cédula: 12345678</li>
                    <li>Edad: 30</li>
                </ul>
                <img class="qr" src="{{ asset('storage/qr.png') }}" alt="Código QR" />
            </div>
        </div>
    </div>
</body>
</html>
