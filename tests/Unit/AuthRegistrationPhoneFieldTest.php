<?php

declare(strict_types=1);

use Livewire\Volt\Volt;

uses(Tests\TestCase::class);

test('agent form create includes international phone field', function () {
    Volt::test('agentformcreate')
        ->assertSee('WhatsApp / Teléfono')
        ->assertSet('country_code', '+58');
});

test('agency master form includes international phone field', function () {
    Volt::test('agencymasterform')
        ->assertSee('WhatsApp / Teléfono')
        ->assertSet('country_code', '+58');
});

test('agent form create formats phone for storage', function () {
    $component = Volt::test('agentformcreate')
        ->set('country_code', '+57')
        ->set('phone', '3001234567');

    expect($component->instance()->phoneForStorage())->toBe('+573001234567');
});
