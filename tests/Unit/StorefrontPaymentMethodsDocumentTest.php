<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontPaymentMethodsDocument;
use Tests\TestCase;

uses(TestCase::class);

it('encuentra el pdf de metodos de pago y genera el qr', function (): void {
    expect(StorefrontPaymentMethodsDocument::exists())->toBeTrue()
        ->and(StorefrontPaymentMethodsDocument::publicUrl())->toContain('/app/documentos/metodos-de-pago')
        ->and(StorefrontPaymentMethodsDocument::ensureShareableRelativePath())
        ->toBe(StorefrontPaymentMethodsDocument::STORAGE_DIRECTORY.'/'.StorefrontPaymentMethodsDocument::DOWNLOAD_FILENAME);

    $qr = StorefrontPaymentMethodsDocument::qrDataUri();

    expect($qr)->toStartWith('data:image/png;base64,');
});

it('entrega el pdf de metodos de pago en una url publica estable', function (): void {
    $response = $this->get('/app/documentos/metodos-de-pago');

    $response->assertSuccessful();

    expect((string) $response->headers->get('content-type'))->toContain('pdf');
});

it('permite descargar el pdf con disposition attachment', function (): void {
    $response = $this->get('/app/documentos/metodos-de-pago?download=1');

    $response->assertSuccessful();

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain(StorefrontPaymentMethodsDocument::DOWNLOAD_FILENAME);
});
