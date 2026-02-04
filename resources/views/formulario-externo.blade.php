<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Agencia - iOS Minimalist</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            letter-spacing: -0.015em;
        }

        ::-webkit-scrollbar {
            width: 0px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.98);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-glass {
            animation: fadeIn 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        /* Estilo para los selects para que no rompan el minimalismo */
        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%238E8E93'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1rem;
        }
    </style>
</head>

<body class="h-screen w-full overflow-hidden bg-[#050505] relative selection:bg-blue-500/30">

    <!-- Fondo de Pantalla -->
    <div class="absolute inset-0 z-0">
        <img src="{{ asset('image/i2.jpg') }}"
            alt="Abstract Minimalist Background" class="w-full h-full object-cover">
        <!-- Cortina Negra con Gradiente -->
        <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/60 to-black/80 backdrop-blur-[2px]"></div>
    </div>

    @livewire('formulario-externo')

    <!-- Script de Lógica -->
    <script>
        const locationData = {
            'VE': { 'Distrito Capital': ['Caracas'], 'Zulia': ['Maracaibo'], 'Carabobo': ['Valencia'] },
            'MX': { 'CDMX': ['Ciudad de México'], 'Jalisco': ['Guadalajara'] },
            'US': { 'Florida': ['Miami'], 'California': ['Los Angeles'] }
        };

        const countrySelect = document.getElementById('country');
        const stateSelect = document.getElementById('state');
        const citySelect = document.getElementById('city');

        function loadStates() {
            const countryCode = countrySelect.value;
            stateSelect.innerHTML = '<option value="" class="bg-zinc-900">Región</option>';
            citySelect.innerHTML = '<option value="" class="bg-zinc-900">Localidad</option>';
            stateSelect.disabled = true;
            citySelect.disabled = true;

            if (countryCode && locationData[countryCode]) {
                Object.keys(locationData[countryCode]).forEach(state => {
                    const option = document.createElement('option');
                    option.value = state;
                    option.textContent = state;
                    option.className = "bg-zinc-900";
                    stateSelect.appendChild(option);
                });
                stateSelect.disabled = false;
            }
        }

        function loadCities() {
            const countryCode = countrySelect.value;
            const stateName = stateSelect.value;
            citySelect.innerHTML = '<option value="" class="bg-zinc-900">Localidad</option>';
            citySelect.disabled = true;

            if (countryCode && stateName && locationData[countryCode][stateName]) {
                locationData[countryCode][stateName].forEach(city => {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    option.className = "bg-zinc-900";
                    citySelect.appendChild(option);
                });
                citySelect.disabled = false;
            }
        }
    </script>

</body>

</html>