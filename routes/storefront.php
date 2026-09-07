<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\StorefrontGoogleAuthController;
use App\Http\Controllers\Storefront\StorefrontPaymentMethodsController;
use App\Http\Controllers\Storefront\StorefrontQuotePdfController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('web')
    ->prefix('app')
    ->group(function (): void {
        Route::middleware('storefront.guest')->group(function (): void {
            Volt::route('/', 'volt.app.welcome')->name('storefront.welcome');
            Volt::route('/entrar', 'volt.app.login')->name('storefront.login');
            Volt::route('/registro', 'volt.app.register')->name('storefront.register');
            Route::get('/entrar/google', [StorefrontGoogleAuthController::class, 'redirect'])
                ->name('storefront.login.google');
            Route::get('/entrar/google/callback', [StorefrontGoogleAuthController::class, 'callback'])
                ->name('storefront.login.google.callback');
        });

        Route::post('/salir', function () {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('storefront.welcome');
        })->name('storefront.logout')->middleware('auth');

        Route::middleware('storefront.auth')->group(function (): void {
            Volt::route('/perfil', 'volt.app.profile')->name('storefront.profile');
            Volt::route('/cotizaciones', 'volt.app.quotes')->name('storefront.quotes');
            Volt::route('/cotizacion/{code}/coberturas', 'volt.app.quote-coverages')
                ->where('code', '[A-Za-z0-9\-]+')
                ->name('storefront.quote.coverages');
            Volt::route('/cotizacion/{code}/frecuencia', 'volt.app.quote-frequency')
                ->where('code', '[A-Za-z0-9\-]+')
                ->name('storefront.quote.frequency');
            Volt::route('/cotizacion/{code}/pagar/{frequency}', 'volt.app.quote-pay')
                ->where('code', '[A-Za-z0-9\-]+')
                ->whereIn('frequency', ['anual', 'semestral', 'trimestral'])
                ->name('storefront.quote.pay');
            Route::get('/cotizacion/{code}/pagar', function (string $code) {
                return redirect()->route('storefront.quote.coverages', ['code' => $code]);
            })->where('code', '[A-Za-z0-9\-]+');
            Volt::route('/cotizacion/{code}/comprobante/listo', 'volt.app.quote-receipt-success')
                ->where('code', '[A-Za-z0-9\-]+')
                ->name('storefront.quote.receipt.success');
            Volt::route('/cotizacion/{code}/comprobante/{frequency}', 'volt.app.quote-receipt')
                ->where('code', '[A-Za-z0-9\-]+')
                ->whereIn('frequency', ['anual', 'semestral', 'trimestral'])
                ->name('storefront.quote.receipt');
            Route::get('/cotizacion/{code}/comprobante', function (string $code) {
                return redirect()->route('storefront.quote.coverages', ['code' => $code]);
            })->where('code', '[A-Za-z0-9\-]+');
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
            Route::get('/cotizacion/{code}/pdf', StorefrontQuotePdfController::class)
                ->where('code', '[A-Za-z0-9\-]+')
                ->name('storefront.quote.pdf');
            Volt::route('/cotizacion/{code}', 'volt.app.quote-result')
                ->where('code', '[A-Za-z0-9\-]+')
                ->name('storefront.quote.result');
            Route::get('/documentos/metodos-de-pago', StorefrontPaymentMethodsController::class)
                ->name('storefront.documents.payment-methods');
            Volt::route('/metodos-de-pago', 'volt.app.payment-methods')
                ->name('storefront.payment-methods');
        });
    });
