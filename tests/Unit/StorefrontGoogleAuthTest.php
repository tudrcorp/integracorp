<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontGoogleAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

it('sin credenciales el login con google no está activo', function (): void {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
    ]);

    expect(StorefrontGoogleAuth::isConfigured())->toBeFalse();
});

it('con credenciales arma la url de google con estado pkce y correo', function (): void {
    config([
        'services.google.client_id' => 'abc.apps.googleusercontent.com',
        'services.google.client_secret' => 'secret',
        'services.google.redirect' => 'https://www.integracorp.test/app/entrar/google/callback',
    ]);

    expect(StorefrontGoogleAuth::isConfigured())->toBeTrue();

    $verifier = 'pkce-verifier-token';
    $url = StorefrontGoogleAuth::authorizationUrl('csrf-token', $verifier);

    expect($url)
        ->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->and($url)->toContain('client_id=abc.apps.googleusercontent.com')
        ->and($url)->toContain('state=csrf-token')
        ->and($url)->toContain('scope=openid')
        ->and($url)->toContain('code_challenge_method=S256')
        ->and($url)->toContain('code_challenge='.StorefrontGoogleAuth::codeChallenge($verifier))
        ->and($url)->toContain('redirect_uri='.rawurlencode('https://www.integracorp.test/app/entrar/google/callback'));
});

it('si no hay redirect en config usa la ruta nombrada de la pwa', function (): void {
    config(['services.google.redirect' => '']);

    expect(StorefrontGoogleAuth::redirectUri())->toEndWith('/app/entrar/google/callback');
});

it('resuelve el perfil de google a partir del codigo de autorizacion', function (): void {
    config([
        'services.google.client_id' => 'abc.apps.googleusercontent.com',
        'services.google.client_secret' => 'secret',
        'services.google.redirect' => 'https://www.integracorp.test/app/entrar/google/callback',
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok-123'], 200),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'email' => 'Ana@TuDrEnCasa.com',
            'email_verified' => true,
            'name' => 'Ana Pérez',
        ], 200),
    ]);

    $profile = StorefrontGoogleAuth::userFromAuthorizationCode('auth-code', 'pkce-verifier');

    expect($profile)->toBe([
        'email' => 'ana@tudrencasa.com',
        'name' => 'Ana Pérez',
    ]);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['code_verifier'] === 'pkce-verifier'
            && $request['code'] === 'auth-code';
    });
});

it('sin credenciales el boton de google avisa en la bienvenida', function (): void {
    config([
        'services.google.client_id' => '',
        'services.google.client_secret' => '',
    ]);

    $this->get('/app/entrar/google')
        ->assertRedirect(route('storefront.welcome'))
        ->assertSessionHas('storefront_notice');
});

it('con credenciales redirige al consentimiento de google', function (): void {
    config([
        'services.google.client_id' => 'abc.apps.googleusercontent.com',
        'services.google.client_secret' => 'secret',
        'services.google.redirect' => 'https://www.integracorp.test/app/entrar/google/callback',
    ]);

    $response = $this->get('/app/entrar/google');

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))
        ->toStartWith('https://accounts.google.com/o/oauth2/v2/auth')
        ->and($response->headers->get('Location'))->toContain('code_challenge_method=S256');
});

it('el callback exige estado y pkce de la sesion', function (): void {
    $this->get('/app/entrar/google/callback?state=forjado&code=abc')
        ->assertRedirect(route('storefront.welcome'))
        ->assertSessionHas('storefront_notice');
});

it('rechaza un perfil de google sin correo verificado', function (): void {
    config([
        'services.google.client_id' => 'abc.apps.googleusercontent.com',
        'services.google.client_secret' => 'secret',
        'services.google.redirect' => 'https://www.integracorp.test/app/entrar/google/callback',
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok-123'], 200),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'email' => 'ana@tudrencasa.com',
            'email_verified' => false,
            'name' => 'Ana',
        ], 200),
    ]);

    StorefrontGoogleAuth::userFromAuthorizationCode('auth-code', 'pkce-verifier');
})->throws(RuntimeException::class, 'El correo de Google todavía no está verificado.');
