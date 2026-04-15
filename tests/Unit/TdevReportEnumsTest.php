<?php

declare(strict_types=1);

use App\Enums\FormaPago;
use App\Enums\StatusComision;
use App\Enums\StatusPago;
use App\Enums\StatusVaucher;

it('define los valores almacenados y etiquetas de StatusVaucher', function (): void {
    expect(StatusVaucher::cases())->toHaveCount(3)
        ->and(StatusVaucher::Activo->value)->toBe('ACTIVO')
        ->and(StatusVaucher::Activo->label())->toBe('Activo')
        ->and(StatusVaucher::options())->toHaveKeys(['ACTIVO', 'ANULADO', 'EXPIRADO']);
});

it('incluye En gestión en StatusComision', function (): void {
    expect(StatusComision::cases())->toHaveCount(5)
        ->and(StatusComision::EnGestion->value)->toBe('EN GESTION')
        ->and(StatusComision::EnGestion->label())->toBe('En gestión');
});

it('resuelve etiqueta y color desde texto heredado', function (): void {
    expect(StatusPago::labelFromMixed('pendiente'))->toBe('Pendiente')
        ->and(StatusPago::filamentColorFromMixed('PAGADO'))->toBe('success');
});

it('acepta etiquetas legibles en BD como «Activo» al castear desde almacenamiento', function (): void {
    expect(StatusVaucher::fromStored('Activo'))->toBe(StatusVaucher::Activo)
        ->and(StatusVaucher::fromStored('ACTIVO'))->toBe(StatusVaucher::Activo)
        ->and(StatusVaucher::fromStored('vigente'))->toBe(StatusVaucher::Activo);
});

it('define FormaPago con valores almacenados y etiquetas TDEV', function (): void {
    expect(FormaPago::cases())->toHaveCount(3)
        ->and(FormaPago::Credito->value)->toBe('CREDITO')
        ->and(FormaPago::CreditoPagado->value)->toBe('CREDITO PAGADO')
        ->and(FormaPago::TarjetaCredito->value)->toBe('TARJETA DE CREDITO')
        ->and(FormaPago::options())->toHaveKeys(['CREDITO', 'CREDITO PAGADO', 'TARJETA DE CREDITO']);
});

it('resuelve FormaPago desde texto mixto y colores Filament', function (): void {
    expect(FormaPago::fromStored('credito'))->toBe(FormaPago::Credito)
        ->and(FormaPago::labelFromMixed('CREDITO PAGADO'))->toBe('Credito Pagado')
        ->and(FormaPago::filamentColorFromMixed('TARJETA DE CREDITO'))->toBe('info')
        ->and(FormaPago::fromStored('tarjeta de crédito'))->toBe(FormaPago::TarjetaCredito);
});
