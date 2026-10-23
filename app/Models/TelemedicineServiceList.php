<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineServiceList extends Model
{
    public const CONSULTA_GENERAL_ID = 17;

    /**
     * Servicio estándar: se ofrece a todo paciente aunque el plan no lo incluya, y solo
     * registra el tipo de consulta (no consume cupo ni dispara flujos en Operaciones).
     */
    public const CONSULTA_PRESENCIAL_NAME = 'CONSULTA PRESENCIAL';

    /**
     * @var array{id: int, name: string}|false|null
     */
    private static array|false|null $standardType1Option = null;

    /**
     * Servicios de nivel 1 que aparecen siempre, con o sin plan.
     *
     * @return array<int, string>
     */
    public static function standardType1Options(): array
    {
        if (self::$standardType1Option === null) {
            $row = static::query()
                ->where('name', self::CONSULTA_PRESENCIAL_NAME)
                ->where('level', 1)
                ->orderBy('id')
                ->first(['id', 'name']);

            self::$standardType1Option = $row === null ? false : ['id' => (int) $row->id, 'name' => (string) $row->name];
        }

        return self::$standardType1Option === false
            ? []
            : [self::$standardType1Option['id'] => self::$standardType1Option['name']];
    }

    public static function isStandardType1(?int $serviceListId): bool
    {
        return $serviceListId !== null && array_key_exists($serviceListId, self::standardType1Options());
    }

    public static function flushStandardType1Options(): void
    {
        self::$standardType1Option = null;
    }

    protected $table = 'telemedicine_service_lists';

    protected $fillable = ['name', 'description'];
}
