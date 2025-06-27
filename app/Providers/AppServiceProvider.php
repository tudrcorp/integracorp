<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Colors\Color;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentColor::register([
            'azulOscuro'    => Color::hex('#052F60'),
            'azulClaro'     => Color::hex('#305B93'),
            'azul'          => Color::hex('#5488AE'),
            'verdeOpaco'    => Color::hex('#4A8982'),
            'verde'         => Color::hex('#529471'),
            'gris'          => Color::hex('#E8EBEA'),
        ]);
    }
}