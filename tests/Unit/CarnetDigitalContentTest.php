<?php

declare(strict_types=1);

use App\Support\Affiliations\Certificates\CarnetDigitalContent;
use App\Support\Affiliations\Certificates\CarnetDigitalIcons;

uses(Tests\TestCase::class);

it('el carnet individual separa Telemedicina y AMD de la coordinación de servicios derivados', function (): void {
    $carnet = CarnetDigitalContent::for(CarnetDigitalContent::INDIVIDUAL);

    expect($carnet['variant'])->toBe(CarnetDigitalContent::INDIVIDUAL)
        ->and($carnet['title'])->toBe('Carnet digital · Tu Dr en Casa')
        ->and($carnet['photo'])->toBe('image/certificado-afiliacion/carnet-digital-portada.jpg')
        ->and(array_column($carnet['actions'], 'label'))->toBe(['Telemedicina y Atención Médica Domiciliaria (AMD)', 'Coordinación de servicios derivados'])
        ->and($carnet['actions'][0]['href'])->toStartWith('https://wa.me/584142009229?text=')
        ->and($carnet['actions'][1]['href'])->toStartWith('https://wa.me/584242132112?text=')
        ->and($carnet['steps'][0]['text'])->toContain('Telemedicina y AMD');
});

it('el carnet corporativo es el plan a la medida con un único canal, MediChat', function (): void {
    $carnet = CarnetDigitalContent::for(CarnetDigitalContent::CORPORATE);

    expect($carnet['variant'])->toBe(CarnetDigitalContent::CORPORATE)
        ->and($carnet['title'])->toBe('Carnet digital · Plan a la medida · Tu Dr en Casa')
        ->and($carnet['photo'])->toBe('image/certificado-afiliacion/carnet-digital-portada-a-la-medida.jpg')
        ->and($carnet['subtitle'])->toBe('Tu plan a la medida: un médico para ti, en casa o en la oficina.')
        ->and($carnet['actions'])->toHaveCount(1)
        ->and($carnet['actions'][0]['label'])->toBe('MediChat')
        ->and($carnet['actions'][0]['icons'])->toBe(['phone', 'message-circle'])
        ->and(urldecode($carnet['actions'][0]['href']))->toBe('https://wa.me/584242132112?text=Hola, soy afiliado de Tu Dr en Casa y necesito orientación médica.')
        ->and($carnet['steps'][0]['text'])->toBe('Escríbenos o llámanos por MediChat al +58 424-2132112.');
});

it('una variante desconocida cae en el carnet individual', function (): void {
    expect(CarnetDigitalContent::for('otra')['variant'])->toBe(CarnetDigitalContent::INDIVIDUAL);
});

it('los teléfonos de contacto marcan en formato internacional', function (): void {
    $phones = collect(CarnetDigitalContent::for(CarnetDigitalContent::INDIVIDUAL)['contact_groups'])->flatMap(fn (array $group): array => $group['phones']);

    expect($phones->firstWhere('label', '0414 901 0352')['href'])->toBe('tel:+584149010352')
        ->and($phones->pluck('href')->every(fn (string $href): bool => (bool) preg_match('/^tel:\+58\d{10}$/', $href)))->toBeTrue();
});

it('los dos carnets comparten la misma página y sus imágenes existen', function (string $variant): void {
    $carnet = CarnetDigitalContent::for($variant);

    expect(is_file(public_path($carnet['photo'])))->toBeTrue()
        ->and(is_file(public_path('image/certificado-afiliacion/carnet-digital-logo.png')))->toBeTrue()
        ->and(is_file(public_path('image/certificado-afiliacion/logo-farmadoc.png')))->toBeTrue();
})->with([CarnetDigitalContent::INDIVIDUAL, CarnetDigitalContent::CORPORATE]);

it('cada ícono que usa la página está incrustado y no se pide al CDN', function (): void {
    $view = file_get_contents(resource_path('views/affiliation-certificate-verification.blade.php'));
    preg_match_all("/Icon::svg\\('([a-z-]+)'/", $view, $literal);

    $used = collect($literal[1])
        ->merge(['search', 'shield-check', 'triangle-alert'])
        ->merge(collect([CarnetDigitalContent::INDIVIDUAL, CarnetDigitalContent::CORPORATE])->flatMap(function (string $variant): array {
            $carnet = CarnetDigitalContent::for($variant);

            return [...array_merge(...array_column($carnet['actions'], 'icons')), ...array_column($carnet['steps'], 'icon'), ...array_column($carnet['contact_groups'], 'icon')];
        }))
        ->unique();

    expect($used->diff(CarnetDigitalIcons::names())->all())->toBe([])
        ->and($view)->not->toContain('unpkg.com')
        ->and((string) CarnetDigitalIcons::svg('phone', 22, '#00A859'))->toContain('width="22"')
        ->toContain('stroke="#00A859"')
        ->toContain('aria-hidden="true"');
});

it('un ícono inexistente falla en vez de dejar un hueco en la página', function (): void {
    CarnetDigitalIcons::svg('no-existe');
})->throws(InvalidArgumentException::class);
