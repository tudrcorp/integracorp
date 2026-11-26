<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Analistas del mismo departamento. La lista se arma al abrir el control, no en cada refresco.
 */
final class CrmInboxColleagues
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public static function forArea(?string $area, ?int $exceptUserId = null): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $department = CrmInboxAreas::departmentFor($area);
        $colleagues = [];

        $rows = DB::table('users')
            ->where('status', 'ACTIVO')
            ->where('email', 'like', '%@tudrencasa.com')
            ->orderBy('name')
            ->get(['id', 'name', 'departament']);

        foreach ($rows as $row) {
            $id = (int) $row->id;

            if ($exceptUserId !== null && $id === $exceptUserId) {
                continue;
            }

            if (! in_array($department, self::departmentsOf($row->departament), true)) {
                continue;
            }

            $name = trim((string) $row->name);
            $colleagues[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : 'Analista',
            ];

            if (count($colleagues) >= 40) {
                break;
            }
        }

        return $colleagues;
    }

    public static function nameInArea(?string $area, int $userId): ?string
    {
        foreach (self::forArea($area) as $colleague) {
            if ($colleague['id'] === $userId) {
                return $colleague['name'];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function departmentsOf(mixed $raw): array
    {
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $departments = [];

        foreach ($decoded as $item) {
            if (is_string($item) && trim($item) !== '') {
                $departments[] = strtoupper(trim($item));
            }
        }

        return $departments;
    }
}
