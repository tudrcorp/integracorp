
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] flex flex-col p-6 lg:p-8 items-center justify-center h-screen">

    @livewire('link-debito-inmediato')

    @fluxScripts
    @persist('toast')
    <flux:toast />
    @endpersist
    
</body>

</html>