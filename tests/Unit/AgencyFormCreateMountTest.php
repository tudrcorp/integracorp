<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Livewire\Volt\Volt;

uses(Tests\TestCase::class);

test('agency form create assigns master type for default owner code', function () {
    Volt::test('agencyformcreate')
        ->assertSet('agency_type_id', 1)
        ->assertSet('owner_code', 'TDG-100')
        ->assertSet('country_code', '+58');
});

test('agency form create assigns general type when owner code comes from encrypted link', function () {
    Volt::test('agencyformcreate', [
        'code' => Crypt::encryptString('AG-TEST-001'),
    ])
        ->assertSet('agency_type_id', 3)
        ->assertSet('owner_code', 'AG-TEST-001');
});

test('agency form create formats venezuelan phone as user types', function () {
    $component = Volt::test('agencyformcreate')
        ->set('country_code', '+58')
        ->set('phone', '4127018390')
        ->assertSet('phone', '0412 701 8390');

    expect($component->instance()->phoneForStorage())->toBe('+584127018390');
});

test('agency form create stores colombian phone with selected country code', function () {
    $component = Volt::test('agencyformcreate')
        ->set('country_code', '+57')
        ->set('phone', '3001234567');

    expect($component->instance()->isPhoneValid())->toBeTrue()
        ->and($component->instance()->phoneForStorage())->toBe('+573001234567');
});

test('agency form create validates complete venezuelan mobile number', function () {
    $component = Volt::test('agencyformcreate')
        ->set('phone', '0412 701 8390');

    expect($component->instance()->isPhoneValid())->toBeTrue();
    expect($component->instance()->phoneForStorage())->toBe('+584127018390');
});

test('agency form create page renders country code selector', function () {
    $code = Crypt::encryptString('AG-TEST-003');

    $this->get('/agency/c/'.$code)
        ->assertSuccessful()
        ->assertSee('WhatsApp / Teléfono')
        ->assertSee('+58')
        ->assertSee('+57');
});
