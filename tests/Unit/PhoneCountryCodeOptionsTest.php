<?php

declare(strict_types=1);

use App\Support\PhoneCountryCodeOptions;

test('phone country code options includes venezuela and common latin america codes', function () {
    $options = PhoneCountryCodeOptions::common();

    expect($options)->toHaveKey('+58')
        ->and($options)->toHaveKey('+57')
        ->and($options)->toHaveKey('+1')
        ->and(PhoneCountryCodeOptions::isVenezuela('+58'))->toBeTrue()
        ->and(PhoneCountryCodeOptions::isAllowed('+58'))->toBeTrue()
        ->and(PhoneCountryCodeOptions::isAllowed('+999'))->toBeFalse();
});
