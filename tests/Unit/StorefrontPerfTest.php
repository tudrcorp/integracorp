<?php

declare(strict_types=1);

it('la ficha del plan pinta primero el shell y carga beneficios despues', function (): void {
    $base = dirname(__DIR__, 2);
    $view = file_get_contents($base.'/app/Support/Storefront/StorefrontPlanView.php');
    $plan = file_get_contents($base.'/resources/views/livewire/volt/app/plan.blade.php');
    $home = file_get_contents($base.'/resources/views/livewire/volt/app/home.blade.php');
    $sw = file_get_contents($base.'/public/app/sw.js');
    $catalog = file_get_contents($base.'/app/Support/Storefront/StorefrontCatalog.php');

    expect($view)
        ->toContain('function shell')
        ->toContain("'rows' => []")
        ->and($plan)->toContain('StorefrontPlanView::shell')
        ->and($plan)->toContain('loadDetails')
        ->and($plan)->toContain('requestIdleCallback')
        ->and($plan)->toContain('cover-picture')
        ->and($home)->toContain('cover-picture')
        ->and($home)->toContain('__sfPrefetchPlan')
        ->and($home)->not->toContain('loading="lazy"')
        ->and($catalog)->toContain('Cache::remember')
        ->and($sw)->toContain('storefront-static-v3')
        ->and($sw)->toContain('/image/storefront/plan-inicial.webp');
});
