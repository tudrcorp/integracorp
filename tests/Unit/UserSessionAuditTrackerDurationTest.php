<?php

declare(strict_types=1);

use App\Support\UserSessionAuditTracker;

it('normaliza segundos flotantes al formatear duración de sesión', function (): void {
    $method = new ReflectionMethod(UserSessionAuditTracker::class, 'durationLabel');
    $method->setAccessible(true);

    $formatted = $method->invoke(null, 12.75);

    expect($formatted)->toBe('00:00:12');
});
