<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('registra rutas de animación tokenizada y cupón con auth', function (): void {
    $webRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($webRoutes)
        ->toContain("Route::get('/feliz-cumpleanos/{token}'")
        ->toContain('birthday.coupon.auth')
        ->toContain("Route::get('/cupon-cumpleanos/{token}'")
        ->toContain('BirthdayAnimationAccessController')
        ->toContain('BirthdayCouponRedemptionController');
});

it('expone payload de cupones en la vista de animación', function (): void {
    $blade = file_get_contents(resource_path('views/public/birthday-animation.blade.php'));

    expect($blade)->toContain('BIRTHDAY_COUPON_PAYLOAD')
        ->and($blade)->toContain('couponPayloadJson');
});

it('fusiona mensajes de cupón desde el payload en cumple-anim', function (): void {
    $cumpleAnim = file_get_contents(public_path('animacion-cumpleanos/js/cumple-anim.js'));

    expect($cumpleAnim)->toContain('buildCouponsFromPayload')
        ->and($cumpleAnim)->toContain('BIRTHDAY_COUPON_PAYLOAD')
        ->and($cumpleAnim)->toContain('isCouponBlockedStatus')
        ->and($cumpleAnim)->toContain('reconcileCouponStatusFromServer')
        ->and($cumpleAnim)->toContain('clearStoredCouponStatus')
        ->and($cumpleAnim)->toContain('couponClipInsideEnvelope')
        ->and($cumpleAnim)->toContain('mobileEnvelopeMaskActive')
        ->and($cumpleAnim)->toContain('birthday_coupon_status_')
        ->and($cumpleAnim)->toContain('scriptBirthdayGreetingText')
        ->and($cumpleAnim)->toContain('celebrantDisplayName')
        ->and($cumpleAnim)->toContain('Te preparamos cuatro regalos')
        ->and($cumpleAnim)->toContain('MobileCouponCarousel3D')
        ->and($cumpleAnim)->toContain('CouponClickOnceProvider')
        ->and($cumpleAnim)->not->toContain('clickedRef.current.add(key)');

    expect(file_exists(public_path('animacion-cumpleanos/assets/cupon-farmadoc.jpg')))->toBeTrue();
});

it('el correo muestra la tarjeta y el clic abre la animación del cumpleañero', function (): void {
    config(['app.url' => 'https://integracorp.tudrgroup.com']);

    $animationUrl = 'https://integracorp.tudrgroup.com/feliz-cumpleanos/abc123';

    $html = view('mails.cumpleanos-email', [
        'file' => 'otra-imagen.jpg',
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'animationUrl' => $animationUrl,
    ])->render();

    expect($html)
        ->toContain('href="'.$animationUrl.'"')
        ->toContain('https://integracorp.tudrgroup.com/animacion-cumpleanos/assets/tarjeta-cumpleanos.jpg')
        ->toContain('¡Haz clic aquí para ver tus regalos!')
        ->not->toContain('otra-imagen.jpg')
        ->not->toContain('Abrir animación y cupones');

    expect(file_exists(public_path('animacion-cumpleanos/assets/tarjeta-cumpleanos.jpg')))->toBeTrue();
});

it('el correo de una notificación normal muestra su propia imagen', function (): void {
    config(['app.url' => 'https://integracorp.tudrgroup.com']);

    $html = view('mails.cumpleanos-email', [
        'file' => 'birthday-notifications/clasica.jpg',
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'animationUrl' => null,
    ])->render();

    expect($html)
        ->toContain('https://integracorp.tudrgroup.com/storage/birthday-notifications/clasica.jpg')
        ->not->toContain('tarjeta-cumpleanos.jpg')
        ->not->toContain('feliz-cumpleanos');
});

it('conserva la url al final si el mensaje de whatsapp no cabe en el caption', function (): void {
    $url = 'https://integracorp.tudrgroup.com/feliz-cumpleanos/abc123';
    $text = str_repeat('Que en la vida siempre encuentres razones para sonreír. ', 40)."\n".$url;

    $caption = \App\Support\RunReportMessageFormatter::truncateForWhatsAppCaption($text, 180);

    expect($caption)->toEndWith($url)
        ->and(mb_strlen($caption))->toBeLessThanOrEqual(180);
});

it('whatsapp resuelve la tarjeta oficial fuera de storage', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($controller)
        ->toContain('BirthdayCelebrationCard::isOfficialCard')
        ->toContain('BirthdayCelebrationCard::publicUrl()');
});

it('redirige el index estático legacy hacia la ruta sin token (404)', function (): void {
    $this->get('/animacion-cumpleanos/index.html')
        ->assertRedirect('/feliz-cumpleanos');

    $this->get('/feliz-cumpleanos')->assertNotFound();
});
