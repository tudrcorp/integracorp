<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <style>
        [x-cloak] {
            display: none !important;
        }

    </style>

    @filamentStyles
    @vite('resources/css/app.css')

</head>
<body class="bg-[#FDFDFC] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
    <header class="w-full flex items-center justify-center lg:1/3 max-w-[335px] text-sm mb-6 not-has-[nav]:hidden">
        {{-- Logo --}}
        <a href="{{ route('welcome.public') }}">
            <img src="{{ asset('image/logo_new.png') }}" style="width: 350px;">
        </a>
    </header>
    <div class="flex flex-col items-center justify-center w-full max-w-4xl lg:max-w-6xl lg:flex-row gap-6 lg:gap-8">
        @livewire('create-agency')
    </div>

    @livewire('notifications')

    @filamentScripts
    @vite('resources/js/app.js')

</body>
</html>

