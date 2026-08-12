<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RrhhColaborador;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

final class PresentationHubGate
{
    public const SESSION_KEY = 'presentation_hub_access';

    public const IDLE_TIMEOUT_MINUTES = 10;

    /**
     * @return list<array{id: string, title: string, subtitle: string, path: string}>
     */
    public static function presentations(): array
    {
        return SystemsKnowledgeCatalog::presentationPaths();
    }

    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     subtitle: string,
     *     eyebrow: string,
     *     icon: string,
     *     items: list<array{id: string, title: string, subtitle: string, url: string|null, status: string, requires_auth: bool}>
     * }>
     */
    public static function sections(): array
    {
        return SystemsKnowledgeCatalog::sections();
    }

    public static function idleTimeoutSeconds(): int
    {
        return self::IDLE_TIMEOUT_MINUTES * 60;
    }

    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Normaliza teléfono venezolano al formato almacenado (+584121931865).
     */
    public static function normalizePhoneInput(string $value): ?string
    {
        $digits = self::digitsOnly($value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '58') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return '+58'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+58'.substr($digits, 1);
        }

        return null;
    }

    public static function findByCedula(string $value): ?RrhhColaborador
    {
        $digits = self::digitsOnly($value);

        if (strlen($digits) < 6) {
            return null;
        }

        return RrhhColaborador::query()
            ->whereNotNull('cedula')
            ->where('cedula', '!=', '')
            ->get(['id', 'fullName', 'cedula', 'telefono', 'telefonoCorporativo', 'status'])
            ->first(fn (RrhhColaborador $colaborador): bool => self::digitsOnly((string) $colaborador->cedula) === $digits);
    }

    public static function findByPhone(string $value): ?RrhhColaborador
    {
        $normalized = self::normalizePhoneInput($value);

        if ($normalized === null) {
            return null;
        }

        $digits = self::digitsOnly($normalized);

        return RrhhColaborador::query()
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('telefono')->where('telefono', '!=', '');
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('telefonoCorporativo')->where('telefonoCorporativo', '!=', '');
                });
            })
            ->get(['id', 'fullName', 'cedula', 'telefono', 'telefonoCorporativo', 'status'])
            ->first(function (RrhhColaborador $colaborador) use ($digits): bool {
                return self::digitsOnly((string) $colaborador->telefono) === $digits
                    || self::digitsOnly((string) $colaborador->telefonoCorporativo) === $digits;
            });
    }

    public static function authenticate(string $method, string $value): ?RrhhColaborador
    {
        $colaborador = match ($method) {
            'cedula' => self::findByCedula($value),
            'telefono' => self::findByPhone($value),
            default => null,
        };

        if ($colaborador === null) {
            return null;
        }

        if (filled($colaborador->status) && strtolower((string) $colaborador->status) !== 'activo') {
            return null;
        }

        return $colaborador;
    }

    public static function grant(RrhhColaborador $colaborador): void
    {
        $now = now()->toIso8601String();

        Session::put(self::SESSION_KEY, [
            'colaborador_id' => $colaborador->id,
            'full_name' => $colaborador->fullName,
            'authenticated_at' => $now,
            'last_activity_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function isIdleExpired(array $payload): bool
    {
        $lastActivity = $payload['last_activity_at'] ?? $payload['authenticated_at'] ?? null;

        if (! is_string($lastActivity) || $lastActivity === '') {
            return true;
        }

        try {
            return Carbon::parse($lastActivity)->lte(now()->subSeconds(self::idleTimeoutSeconds()));
        } catch (\Throwable) {
            return true;
        }
    }

    public static function check(): bool
    {
        $payload = Session::get(self::SESSION_KEY);

        if (! is_array($payload) || ! isset($payload['colaborador_id'])) {
            return false;
        }

        if (self::isIdleExpired($payload)) {
            self::revoke();

            return false;
        }

        return true;
    }

    public static function touch(): void
    {
        $payload = Session::get(self::SESSION_KEY);

        if (! is_array($payload) || ! isset($payload['colaborador_id'])) {
            return;
        }

        if (self::isIdleExpired($payload)) {
            self::revoke();

            return;
        }

        $payload['last_activity_at'] = now()->toIso8601String();
        Session::put(self::SESSION_KEY, $payload);
    }

    /**
     * @return array{colaborador_id?: int, full_name?: string|null, authenticated_at?: string, last_activity_at?: string}|null
     */
    public static function access(): ?array
    {
        if (! self::check()) {
            return null;
        }

        $payload = Session::get(self::SESSION_KEY);

        return is_array($payload) ? $payload : null;
    }

    public static function revoke(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function isAllowedPath(string $path): bool
    {
        return SystemsKnowledgeCatalog::isProtectedReadyPath($path);
    }
}
