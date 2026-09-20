<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\Scopes;

use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * ¿Existe la tabla de casos en la conexión que se está consultando?
 *
 * Los scopes que ocultan las trazas de un caso eliminado consultan
 * `telemedicine_cases` por subconsulta. En un esquema parcial —un test que crea
 * en sqlite solo la tabla hija— esa subconsulta reventaría, así que el filtro se
 * omite cuando la tabla no está.
 *
 * La respuesta se memoiza **por conexión**, y nunca en sqlite: una misma
 * aplicación puede alternar entre la base real y varias bases en memoria con
 * esquemas distintos, y un positivo memoizado de una arrastraría a la otra. En
 * MySQL supone una sola comprobación por conexión y proceso; en sqlite, una
 * lectura barata de `sqlite_master`.
 */
final class TelemedicineCaseTablePresence
{
    /**
     * @var array<string, bool>
     */
    private static array $present = [];

    public static function has(ConnectionInterface $connection, string $table): bool
    {
        $key = $connection->getName().'|'.$table;
        $memoizable = $connection->getDriverName() !== 'sqlite';

        if ($memoizable && (self::$present[$key] ?? false)) {
            return true;
        }

        try {
            $exists = $connection->getSchemaBuilder()->hasTable($table);
        } catch (Throwable) {
            return false;
        }

        if ($exists && $memoizable) {
            self::$present[$key] = true;
        }

        return $exists;
    }

    /**
     * Solo para tests: olvida lo memoizado.
     */
    public static function flush(): void
    {
        self::$present = [];
    }
}
