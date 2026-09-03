<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth de Google para la PWA, sin Socialite: el agente entra con la
 * misma cuenta de IntegraCorp si el correo coincide.
 */
final class StorefrontGoogleAuth
{
    public const SESSION_STATE = 'storefront_google_state';

    public const SESSION_VERIFIER = 'storefront_google_verifier';

    public static function isConfigured(): bool
    {
        return filled(self::clientId()) && filled(self::clientSecret());
    }

    public static function clientId(): string
    {
        return trim((string) config('services.google.client_id', ''));
    }

    public static function clientSecret(): string
    {
        return trim((string) config('services.google.client_secret', ''));
    }

    public static function redirectUri(): string
    {
        $configured = trim((string) config('services.google.redirect', ''));

        if ($configured !== '') {
            return $configured;
        }

        return route('storefront.login.google.callback');
    }

    public static function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function authorizationUrl(string $state, string $codeVerifier): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => self::clientId(),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
            'code_challenge' => self::codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{email: string, name: string}
     */
    public static function userFromAuthorizationCode(string $code, string $codeVerifier): array
    {
        $tokenResponse = Http::asForm()
            ->timeout(12)
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => self::clientId(),
                'client_secret' => self::clientSecret(),
                'redirect_uri' => self::redirectUri(),
                'grant_type' => 'authorization_code',
                'code_verifier' => $codeVerifier,
            ]);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('Google no pudo validar el acceso.');
        }

        $accessToken = (string) $tokenResponse->json('access_token', '');

        if ($accessToken === '') {
            throw new RuntimeException('Google no devolvió un token de acceso.');
        }

        $profileResponse = Http::withToken($accessToken)
            ->timeout(12)
            ->acceptJson()
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (! $profileResponse->successful()) {
            throw new RuntimeException('Google no devolvió el perfil de la cuenta.');
        }

        $email = mb_strtolower(trim((string) $profileResponse->json('email', '')));
        $name = trim((string) $profileResponse->json('name', ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('La cuenta de Google no trae un correo válido.');
        }

        if (! (bool) $profileResponse->json('email_verified', false)) {
            throw new RuntimeException('El correo de Google todavía no está verificado.');
        }

        return [
            'email' => $email,
            'name' => $name !== '' ? $name : $email,
        ];
    }
}
