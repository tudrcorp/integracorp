<?php

declare(strict_types=1);

use Filament\Support\Colors\Color;

it('genera paletas con tono 50 para badgeColor de tabs', function () {
    foreach (['#ffc107', '#ffcc00', '#28cd41', '#ff3b30'] as $hex) {
        $palette = Color::hex($hex);
        expect($palette)->toHaveKeys([50, 400, 500, 600, 900, 950]);
    }
});
