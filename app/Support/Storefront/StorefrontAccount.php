<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alta, login y perfil de la PWA sobre la tabla users.
 */
final class StorefrontAccount
{
    public const SESSION_FORCE_PROFILE = 'storefront_force_profile';

    public static function normalizePhone(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw) ?: '';
    }

    public static function normalizeIdentification(string $raw): string
    {
        $trimmed = strtoupper(trim($raw));
        $trimmed = preg_replace('/^([VEJPG])[-\.\s]*/', '$1', $trimmed) ?? $trimmed;
        $digits = preg_replace('/\D+/', '', $trimmed) ?: '';

        if ($digits === '') {
            return '';
        }

        if (preg_match('/^([VEJPG])/', $trimmed, $match) === 1) {
            return $match[1].$digits;
        }

        return $digits;
    }

    public static function normalizeEmail(string $raw): string
    {
        return mb_strtolower(trim($raw));
    }

    public static function looksLikeEmail(string $raw): bool
    {
        return str_contains($raw, '@');
    }

    public static function findByLoginIdentifier(string $identifier): ?User
    {
        $raw = trim($identifier);

        if ($raw === '') {
            return null;
        }

        if (self::looksLikeEmail($raw)) {
            $email = self::normalizeEmail($raw);

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            return $user instanceof User ? $user : null;
        }

        $phone = self::normalizePhone($raw);
        $id = self::normalizeIdentification($raw);
        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        if ($phone === '' && $id === '' && $digits === '') {
            return null;
        }

        $candidates = User::query()
            ->where(function ($query) use ($phone, $id, $digits): void {
                if ($phone !== '') {
                    $query->orWhere('phone', $phone);
                }

                if ($id !== '') {
                    $query->orWhere('nro_identification', $id)
                        ->orWhere('identity_card', $id);
                }

                if ($digits !== '' && $digits !== $id && $digits !== $phone) {
                    $query->orWhere('nro_identification', $digits)
                        ->orWhere('identity_card', $digits)
                        ->orWhere('phone', $digits);
                }
            })
            ->limit(8)
            ->get();

        foreach ($candidates as $candidate) {
            if (! $candidate instanceof User) {
                continue;
            }

            $candidatePhone = self::normalizePhone((string) $candidate->phone);
            $candidateId = self::normalizeIdentification((string) ($candidate->nro_identification ?: $candidate->identity_card));

            if ($phone !== '' && $candidatePhone !== '' && ($candidatePhone === $phone || str_ends_with($candidatePhone, $phone) || str_ends_with($phone, $candidatePhone))) {
                return $candidate;
            }

            if ($id !== '' && $candidateId !== '' && $candidateId === $id) {
                return $candidate;
            }

            if ($digits !== '' && ($candidateId === $digits || $candidatePhone === $digits)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanAccess(User $user): void
    {
        if (! StorefrontAuth::canAccessPwa($user)) {
            throw ValidationException::withMessages([
                'identifier' => ['Tu cuenta no está activa para usar la app.'],
            ]);
        }
    }

    /**
     * @param  array{
     *     name: string,
     *     nro_identification: string,
     *     password: string
     * }  $data
     *
     * @throws ValidationException
     */
    public static function register(array $data): User
    {
        $name = trim($data['name']);
        $identification = self::normalizeIdentification($data['nro_identification']);
        $password = (string) $data['password'];

        if ($name === '' || $identification === '' || $password === '') {
            throw ValidationException::withMessages([
                'name' => ['Completa nombre, cédula y clave.'],
            ]);
        }

        self::assertIdentifiersAvailable(null, null, $identification);

        return DB::transaction(function () use ($name, $identification, $password): User {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $name,
                'email' => null,
                'phone' => null,
                'nro_identification' => $identification,
                'identity_card' => $identification,
                'password' => $password,
                'status' => 'ACTIVO',
                'email_verified_at' => null,
            ]);

            return $user->fresh() ?? $user;
        });
    }

    /**
     * @throws ValidationException
     */
    public static function createFromGoogle(string $email, string $name): User
    {
        $email = self::normalizeEmail($email);
        $name = trim($name);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['La cuenta de Google no trae un correo válido.'],
            ]);
        }

        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existing instanceof User) {
            throw ValidationException::withMessages([
                'email' => ['Ya tienes cuenta. Entra con tu correo, teléfono o cédula y tu clave.'],
            ]);
        }

        return DB::transaction(function () use ($email, $name): User {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'password' => Hash::make(Str::password(32)),
                'status' => 'ACTIVO',
                'email_verified_at' => now(),
                'phone' => null,
                'nro_identification' => null,
                'identity_card' => null,
            ]);

            return $user->fresh() ?? $user;
        });
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     nro_identification?: string|null,
     *     password?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public static function updateProfile(User $user, array $data): User
    {
        $name = array_key_exists('name', $data) ? trim((string) $data['name']) : (string) $user->name;
        $emailRaw = array_key_exists('email', $data) ? trim((string) $data['email']) : (string) ($user->email ?? '');
        $phoneRaw = array_key_exists('phone', $data) ? trim((string) $data['phone']) : (string) ($user->phone ?? '');
        $idRaw = array_key_exists('nro_identification', $data)
            ? trim((string) $data['nro_identification'])
            : (string) ($user->nro_identification ?: $user->identity_card ?: '');

        $email = $emailRaw !== '' ? self::normalizeEmail($emailRaw) : '';
        $phone = $phoneRaw !== '' ? self::normalizePhone($phoneRaw) : '';
        $identification = $idRaw !== '' ? self::normalizeIdentification($idRaw) : '';

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['El nombre es obligatorio.'],
            ]);
        }

        if ($email === '' && $phone === '') {
            throw ValidationException::withMessages([
                'email' => ['Debes tener al menos un correo o un teléfono.'],
            ]);
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['El correo no es válido.'],
            ]);
        }

        if ($identification === '') {
            throw ValidationException::withMessages([
                'nro_identification' => ['La cédula es obligatoria.'],
            ]);
        }

        self::assertIdentifiersAvailable(
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
            $identification,
            (int) $user->id,
        );

        $payload = [
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'nro_identification' => $identification,
            'identity_card' => $identification,
        ];

        $password = trim((string) ($data['password'] ?? ''));

        if ($password !== '') {
            $payload['password'] = $password;
        }

        $user->fill($payload);
        $user->save();

        return $user->fresh() ?? $user;
    }

    /**
     * @return list<string>
     */
    public static function missingProfileFields(?User $user): array
    {
        if (! $user instanceof User) {
            return ['name', 'nro_identification', 'contact'];
        }

        $missing = [];

        if (trim((string) $user->name) === '') {
            $missing[] = 'name';
        }

        $id = trim((string) ($user->nro_identification ?: $user->identity_card ?: ''));

        if ($id === '') {
            $missing[] = 'nro_identification';
        }

        $email = trim((string) ($user->email ?? ''));
        $phone = trim((string) ($user->phone ?? ''));

        if ($email === '' && $phone === '') {
            $missing[] = 'contact';
        }

        return $missing;
    }

    public static function profileIsComplete(?User $user): bool
    {
        return self::missingProfileFields($user) === [];
    }

    /**
     * @throws ValidationException
     */
    private static function assertIdentifiersAvailable(
        ?string $email,
        ?string $phone,
        string $identification,
        ?int $ignoreUserId = null,
    ): void {
        if ($email !== null && $email !== '') {
            $query = User::query()->whereRaw('LOWER(email) = ?', [$email]);

            if ($ignoreUserId !== null) {
                $query->where('id', '!=', $ignoreUserId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['Ese correo ya está registrado.'],
                ]);
            }
        }

        if ($phone !== null && $phone !== '') {
            $query = User::query()->where('phone', $phone);

            if ($ignoreUserId !== null) {
                $query->where('id', '!=', $ignoreUserId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'phone' => ['Ese teléfono ya está registrado.'],
                ]);
            }
        }

        $idQuery = User::query()->where(function ($query) use ($identification): void {
            $query->where('nro_identification', $identification)
                ->orWhere('identity_card', $identification);
        });

        if ($ignoreUserId !== null) {
            $idQuery->where('id', '!=', $ignoreUserId);
        }

        if ($idQuery->exists()) {
            throw ValidationException::withMessages([
                'nro_identification' => ['Esa cédula ya está registrada.'],
            ]);
        }
    }
}
