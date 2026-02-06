<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

<title>IIntegraCorp - Sistema Integral de Gestión para Empresas</title>

<!-- Descripción SEO (entre 150-160 caracteres) -->
<meta name="description"
    content="Integracorp combina salud y tecnología para ofrecer soluciones médicas digitales, telemedicina y plataformas avanzadas para agentes, agencias y pacientes.">

<!-- Palabras clave (opcional, pero útil) -->
<meta name="keywords"
    content="salud, tecnología, telemedicina, plataforma médica, digitalización salud, agencias médicas, Venezuela, Integracorp, telesalud, innovación médica">

<!-- Canonical URL -->
<link rel="canonical" href="https://integracorp.tudrgroup.com" />

<!-- Open Graph (para compartir en redes sociales) -->
<meta property="og:title" content="Integracorp | Salud y Tecnología">
<meta property="og:description"
    content="Soluciones integrales que fusionan salud y tecnología para transformar el sector médico.">
<meta property="og:image" content="https://integracorp.tudrgroup.com/image/logoTDG.png">
<meta property="og:url" content="https://integracorp.tudrgroup.com">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Integracorp">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@integracorp">
<meta name="twitter:title" content="Integracorp | Salud y Tecnología">
<meta name="twitter:description" content="Plataforma líder en integración de servicios médicos y tecnología digital.">
<meta name="twitter:image" content="https://integracorp.tudrgroup.com/image/logoTDG.png">

<!-- Favicon -->
<link rel="icon" href="{{ asset('image/imagotipo.png') }}" type="image/x-icon">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('image/imagotipo.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('image/imagotipo.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('image/imagotipo.png') }}">
<link rel="manifest" href="/site.webmanifest">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
