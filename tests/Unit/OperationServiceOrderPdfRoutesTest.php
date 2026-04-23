<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

it('registra las rutas PDF de ordenes de servicio en operations', function (): void {
    $routesByName = Route::getRoutes()->getRoutesByName();

    expect($routesByName)->toHaveKeys([
        'operations.operation-service-orders.pdf.preview',
        'operations.operation-service-orders.pdf',
    ]);
});
