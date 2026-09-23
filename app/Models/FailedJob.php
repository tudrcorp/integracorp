<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Trabajo fallido de la cola (`failed_jobs`), solo para leerlo y gestionarlo
 * desde Negocios → Colas y errores. La tabla la escribe Laravel: aquí no se
 * crean filas, solo se consultan, se reintentan o se eliminan.
 *
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property string $failed_at
 */
class FailedJob extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    public function getConnectionName(): ?string
    {
        return config('queue.failed.database') ?: parent::getConnectionName();
    }

    public function getTable(): string
    {
        return (string) config('queue.failed.table', 'failed_jobs');
    }

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Clase del trabajo según el payload, sin deserializar nada.
     */
    public static function jobClassFromPayload(string $payload): string
    {
        return preg_match('/"displayName"\s*:\s*"([^"]+)"/', $payload, $match) === 1
            ? stripslashes($match[1])
            : 'Desconocido';
    }
}
