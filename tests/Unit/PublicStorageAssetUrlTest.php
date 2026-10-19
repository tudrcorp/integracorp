<?php

declare(strict_types=1);

use App\Support\PublicStorageAssetUrl;

uses(Tests\TestCase::class);

it('arma url pública de storage con fallback a app url', function (): void {
    config([
        'parameters.PUBLIC_URL' => '',
        'app.url' => 'https://integracorp.test',
    ]);

    expect(PublicStorageAssetUrl::url('birthday-notifications/tarjeta.png'))
        ->toBe('https://integracorp.test/storage/birthday-notifications/tarjeta.png');
});

it('usa parameters PUBLIC_URL cuando está configurado', function (): void {
    config([
        'parameters.PUBLIC_URL' => 'https://integracorp.tudrgroup.com/storage',
        'app.url' => 'https://integracorp.test',
    ]);

    expect(PublicStorageAssetUrl::url('birthday-notifications/tarjeta.png'))
        ->toBe('https://integracorp.tudrgroup.com/storage/birthday-notifications/tarjeta.png');
});
