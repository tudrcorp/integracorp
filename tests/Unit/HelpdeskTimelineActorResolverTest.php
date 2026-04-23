<?php

declare(strict_types=1);

use App\Support\HelpdeskTimelineActorResolver;

it('genera iniciales a partir del nombre mostrado', function (): void {
    expect(HelpdeskTimelineActorResolver::initialsFromDisplayName('Ana María Pérez'))->toBe('AP')
        ->and(HelpdeskTimelineActorResolver::initialsFromDisplayName('Pedro'))->toBe('PE')
        ->and(HelpdeskTimelineActorResolver::initialsFromDisplayName(''))->toBe('?');
});
