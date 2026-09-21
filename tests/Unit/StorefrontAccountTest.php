<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontAccount;

it('normaliza telefono y cedula para login y registro', function (): void {
    expect(StorefrontAccount::normalizePhone('+58 412-701.8390'))->toBe('584127018390')
        ->and(StorefrontAccount::normalizeIdentification('v-12.345.678'))->toBe('V12345678')
        ->and(StorefrontAccount::normalizeIdentification('12345678'))->toBe('12345678')
        ->and(StorefrontAccount::normalizeEmail(' Ana@TDG.COM '))->toBe('ana@tdg.com')
        ->and(StorefrontAccount::looksLikeEmail('ana@tdg.com'))->toBeTrue()
        ->and(StorefrontAccount::looksLikeEmail('04121234567'))->toBeFalse();
});

it('detecta perfil incompleto sin cedula o sin contacto', function (): void {
    $user = new \App\Models\User([
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'phone' => null,
        'nro_identification' => null,
        'identity_card' => null,
    ]);

    expect(StorefrontAccount::profileIsComplete($user))->toBeFalse()
        ->and(StorefrontAccount::missingProfileFields($user))->toContain('nro_identification');

    $user->nro_identification = 'V123';
    $user->email = null;
    $user->phone = null;

    expect(StorefrontAccount::missingProfileFields($user))->toContain('contact');

    $user->phone = '04121234567';

    expect(StorefrontAccount::profileIsComplete($user))->toBeTrue();
});
