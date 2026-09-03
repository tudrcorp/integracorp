<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\StorefrontGoogleAuthController;
use App\Http\Controllers\Storefront\StorefrontPaymentMethodsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('web')
    ->prefix('app')
    ->group(function (): void {
        Volt::route('/', 'volt.app.welcome')->name('storefront.welcome');
        Volt::route('/planes', 'volt.app.home')->name('storefront.home');
        Volt::route('/planes/{plan}', 'volt.app.plan')
            ->whereNumber('plan')
            ->name('storefront.plan');
        Volt::route('/cotizar/{plan}', 'volt.app.quote-people')
            ->whereNumber('plan')
            ->name('storefront.quote.people');
        Volt::route('/cotizar/{plan}/datos', 'volt.app.quote-details')
            ->whereNumber('plan')
            ->name('storefront.quote.details');
        Volt::route('/cotizar/{plan}/confirmar', 'volt.app.quote-confirm')
            ->whereNumber('plan')
            ->name('storefront.quote.confirm');
        Volt::route('/cotizacion/{code}/propuesta', 'volt.app.quote-proposal')
            ->where('code', '[A-Za-z0-9\-]+')
            ->name('storefront.quote.proposal');
        Volt::route('/cotizacion/{code}', 'volt.app.quote-result')
            ->where('code', '[A-Za-z0-9\-]+')
            ->name('storefront.quote.result');
        Route::get('/documentos/metodos-de-pago', StorefrontPaymentMethodsController::class)
            ->name('storefront.documents.payment-methods');
        Volt::route('/entrar', 'volt.app.login')->name('storefront.login');
        Route::get('/entrar/google', [StorefrontGoogleAuthController::class, 'redirect'])
            ->name('storefront.login.google');
        Route::get('/entrar/google/callback', [StorefrontGoogleAuthController::class, 'callback'])
            ->name('storefront.login.google.callback');

        Route::post('/salir', function () {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('storefront.welcome');
        })->name('storefront.logout')->middleware('auth');
    });
