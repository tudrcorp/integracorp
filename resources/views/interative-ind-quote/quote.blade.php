<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>

<body class="min-h-screen">

    @php
    use App\Models\IndividualQuote;
    $individual_quote = IndividualQuote::where('id', $individual_quote_id)->first();
    @endphp

    <!-- Primera página: Imagen de fondo -->
    <div style="background-image: url('{{ public_path('storage/footer-ideal.png') }}'); background-size: 100%; background-repeat: no-repeat; background-position: center;">

        <div style="position: absolute; top: 0px; left: 0px; margin-top: 15px; padding: 20px; margin-left: 20px">
            <p class="sin-margen" style="margin-bottom: 5px; font-size: 18px;">
                <span style="font-weight: bold; color: #052F60; font-size: 25px; font-style: italic;">Propuesta
                </span>
                <span style="font-weight: bold; color: #7ab2db; font-size: 25px; font-style: italic;">Económica
                </span>
            </p>

            <p class="sin-margen" style="font-size: 16px;">
                <span style="font-weight: bold; color: #000000;">
                    Datos del afiliado titular:
                </span>
                <span style="">
                    Sr(a): {{ $individual_quote->full_name }}
                </span>
            </p>
            <p class="sin-margen" style="font-size: 16px;">
                <span style="font-weight: bold; color: #000000;">
                    Nombre del Agente:
                </span>
                <span style="margin-left: 38px">
                    Pablo Contreras
                </span>
            </p>
            <p class="sin-margen" style="font-size: 16px; margin-top: 2px">
                <span style="font-weight: bold; color: #000000;">
                    Fecha de emisión:
                </span>
                <span style="margin-left: 50px">
                    21/05/2025
                    <br>
                    <span style="font-size: 12px; font-style: italic; font-weight: bold">
                        Propuesta válida por 15 días a partir de la fecha de emisión.
                    </span>
                </span>
            </p>
        </div>
        <div style="position: absolute; top: 140px; right: 10px; margin-top: 20px; padding: 20px; margin-right: 20px">
            <img src="{{ public_path('storage/beneficios-plan-ideal.png') }}" style="width: 700px; height: auto;" alt="">
        </div>

        <div style="position: absolute; top: 500px; right: 10px; margin-top: 20px; padding: 20px; margin-right: 20px; width: 700px;">
            <table style="width: 100%; font-type: Helvetica, sans-serif;">
                <tr style="background-color: #082f62; font-size: 10px;">

                    <th style="font-weight: bold; color: white;">RANGO DE EDAD</th>
                    <th style="font-weight: bold; color: white;">POBLACIÓN</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 1K</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 2K</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 3K</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 5K</th>
                    <th style="font-weight: bold; color: white;">TARIFA ANUAL<br>US$ 10K</th>

                </tr>
            </table>
            
            
        </div>



        <div style="position: absolute; top: 0px; right: 0px; margin-top: 20px; padding: 20px; margin-right: 20px">
            <div>
                <img class="logo-bottom-left" src="{{ public_path('storage/logo2-pdf.png') }}" style="width: 150px; height: 70px;" alt="">
            </div>
        </div>
        <!-- Primera página: Imagen de fondo -->


    </div>

    @fluxScripts
</body>
</html>
