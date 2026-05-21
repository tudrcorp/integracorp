<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RrhhColaborador;
use Illuminate\Support\Collection;

final class HelpdeskTeamMembersPayload
{
    /**
     * @param  list<int|string>  $colaboradorIds
     * @return list<array{id: int, name: string, telefono_corporativo: string|null}>
     */
    public static function fromColaboradorIds(array $colaboradorIds): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (mixed $value): int => (int) $value,
            array_filter($colaboradorIds, static fn (mixed $value): bool => filled($value))
        )));

        if ($ids === []) {
            return [];
        }

        /** @var Collection<int, RrhhColaborador> $colaboradores */
        $colaboradores = RrhhColaborador::query()
            ->whereIn('id', $ids)
            ->orderBy('fullName')
            ->get(['id', 'fullName', 'telefonoCorporativo']);

        return $colaboradores
            ->map(static fn (RrhhColaborador $colaborador): array => [
                'id' => (int) $colaborador->id,
                'name' => (string) $colaborador->fullName,
                'telefono_corporativo' => filled($colaborador->telefonoCorporativo)
                    ? (string) $colaborador->telefonoCorporativo
                    : null,
            ])
            ->values()
            ->all();
    }
}
