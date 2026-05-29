<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\RrhhColaborador;
use App\Support\HelpdeskTimelineActorResolver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ProjectManagementCollaboratorAvatar
{
    public static function initials(string $fullName): string
    {
        return HelpdeskTimelineActorResolver::initialsFromDisplayName($fullName);
    }

    public static function url(RrhhColaborador $collaborator): ?string
    {
        if (! filled($collaborator->avatar)) {
            return null;
        }

        $path = ltrim((string) $collaborator->avatar, '/');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return url('storage/'.$path);
    }

    /**
     * @return array{id: int, name: string, initials: string, avatar_url: string|null}
     */
    public static function profile(RrhhColaborador $collaborator): array
    {
        $name = (string) $collaborator->fullName;

        return [
            'id' => (int) $collaborator->id,
            'name' => $name,
            'initials' => self::initials($name),
            'avatar_url' => self::url($collaborator),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function namesLine(array $members, int $overflowCount): string
    {
        if ($members === []) {
            return 'Sin integrantes asignados';
        }

        $names = collect($members)->pluck('name')->filter()->values();

        if ($overflowCount > 0) {
            $preview = $names->take(2)->implode(', ');

            return trim($preview.' y '.$overflowCount.' más');
        }

        return Str::limit($names->implode(', '), 48);
    }
}
