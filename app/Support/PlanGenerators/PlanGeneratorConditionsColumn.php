<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En MariaDB una columna JSON es LONGTEXT más un CHECK (json_valid()).
 * Cambiar el tipo a LONGTEXT no quita esa regla, y el texto pegado por el
 * analista deja de entrar. El nombre de la regla es `plan_generators.conditions`.
 */
final class PlanGeneratorConditionsColumn
{
    public const JSON_CHECK_CONSTRAINT = 'plan_generators.conditions';

    /**
     * Nombres de CHECK que exigen JSON válido en `conditions`.
     *
     * @return list<string>
     */
    public static function jsonValidityConstraintNames(string $createTableSql): array
    {
        $names = [];

        if (preg_match_all(
            '/CONSTRAINT\s+`([^`]+)`\s+CHECK\s*\(\s*json_valid\s*\(\s*`conditions`\s*\)\s*\)/i',
            $createTableSql,
            $named,
        ) > 0) {
            foreach ($named[1] as $name) {
                $names[] = $name;
            }
        }

        if (preg_match('/json_valid\s*\(\s*`conditions`\s*\)/i', $createTableSql) === 1) {
            $names[] = self::JSON_CHECK_CONSTRAINT;
        }

        return array_values(array_unique($names));
    }

    public static function dropJsonValidityConstraint(): void
    {
        if (! Schema::hasTable('plan_generators') || ! Schema::hasColumn('plan_generators', 'conditions')) {
            return;
        }

        $create = DB::selectOne('SHOW CREATE TABLE `plan_generators`');
        $sql = (string) ($create->{'Create Table'} ?? '');

        foreach (self::jsonValidityConstraintNames($sql) as $name) {
            self::dropConstraint($name);
        }

        DB::statement('ALTER TABLE `plan_generators` MODIFY `conditions` LONGTEXT NULL');
    }

    private static function dropConstraint(string $name): void
    {
        $quoted = '`'.str_replace('`', '``', $name).'`';

        try {
            DB::statement("ALTER TABLE `plan_generators` DROP CONSTRAINT {$quoted}");
        } catch (QueryException $exception) {
            if (self::constraintIsAlreadyGone($exception)) {
                return;
            }

            if ((int) ($exception->errorInfo[1] ?? 0) !== 1064) {
                throw $exception;
            }

            DB::statement("ALTER TABLE `plan_generators` DROP CHECK {$quoted}");
        }
    }

    private static function constraintIsAlreadyGone(QueryException $exception): bool
    {
        $code = (int) ($exception->errorInfo[1] ?? 0);

        if ($code === 1091) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'does not exist')
            || str_contains($message, "doesn't exist")
            || str_contains($message, 'check that it exists')
            || str_contains($message, 'unknown constraint');
    }
}
