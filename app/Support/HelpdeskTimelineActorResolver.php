<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RrhhColaborador;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class HelpdeskTimelineActorResolver
{
    /**
     * @return array{display_name: string, initials: string, avatar_url: string|null}
     */
    public static function resolve(string $actorLabel): array
    {
        $display = trim($actorLabel) !== '' ? trim($actorLabel) : 'Sistema';

        if (in_array($display, ['Sistema', 'Usuario'], true)) {
            return [
                'display_name' => $display,
                'initials' => mb_strtoupper(mb_substr($display, 0, min(2, mb_strlen($display)))) ?: '?',
                'avatar_url' => null,
            ];
        }

        $initials = self::initialsFromDisplayName($display);
        $user = self::findUserByDisplayName($display);
        if ($user === null) {
            return [
                'display_name' => $display,
                'initials' => $initials,
                'avatar_url' => null,
            ];
        }

        $colaborador = RrhhColaborador::query()
            ->where('user_id', $user->getKey())
            ->first(['id', 'avatar']);

        if ($colaborador !== null && filled($colaborador->avatar)) {
            $path = ltrim((string) $colaborador->avatar, '/');
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return [
                    'display_name' => (string) $user->name,
                    'initials' => self::initialsFromDisplayName((string) $user->name),
                    'avatar_url' => $path,
                ];
            }
            if (Storage::disk('public')->exists($path)) {
                return [
                    'display_name' => (string) $user->name,
                    'initials' => self::initialsFromDisplayName((string) $user->name),
                    'avatar_url' => url('storage/'.$path),
                ];
            }
        }

        return [
            'display_name' => (string) $user->name,
            'initials' => self::initialsFromDisplayName((string) $user->name),
            'avatar_url' => null,
        ];
    }

    public static function initialsFromDisplayName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $parts = array_values(array_filter($parts, fn (string $p): bool => $p !== ''));
        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            $w = $parts[0];

            return mb_strtoupper(mb_substr($w, 0, min(2, mb_strlen($w))));
        }

        $first = $parts[0];
        $last = $parts[count($parts) - 1];

        return mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
    }

    private static function findUserByDisplayName(string $display): ?User
    {
        $normalized = preg_replace('/\s+/', ' ', $display) ?? $display;
        $lower = Str::lower($normalized);
        if ($normalized === '') {
            return null;
        }

        if (is_numeric($normalized)) {
            return User::query()->find((int) $normalized, ['id', 'name', 'email']);
        }

        $byEmail = User::query()->where('email', $normalized)->first(['id', 'name', 'email']);
        if ($byEmail !== null) {
            return $byEmail;
        }

        return User::query()->whereRaw('LOWER(name) = ?', [$lower])->first(['id', 'name', 'email']);
    }
}
