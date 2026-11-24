<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Jobs\DeleteServiceOrderPatientDocumentJob;
use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Copia pública temporal del PDF de la orden para que UltraMsg la descargue.
 *
 * UltraMsg solo acepta el documento por URL. El PDF guardado de la orden tiene un nombre
 * predecible, así que se publica una copia con un nombre aleatorio de 48 caracteres que se
 * borra a los {@see self::PUBLIC_FILE_MINUTES} minutos (o enseguida si el envío falla). La
 * tarea programada borra además cualquier copia de más de un día que haya quedado.
 */
final class ServiceOrderPatientDocument
{
    public const PUBLIC_DIRECTORY = 'ordenes-paciente';

    public const PUBLIC_FILE_MINUTES = 120;

    private const NAME_PATTERN = '/^[A-Za-z0-9]{48}\.pdf$/';

    /**
     * @return array{relative_path: string, url: string}
     */
    public static function publish(OperationServiceOrder $order): array
    {
        $contents = OperationServiceOrderPdfService::document($order)['contents'];

        if (strlen($contents) < 100) {
            throw new RuntimeException('El PDF de la orden '.$order->order_number.' salió vacío.');
        }

        $relativePath = self::PUBLIC_DIRECTORY.'/'.Str::random(48).'.pdf';
        $absolutePath = self::absolutePath($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo crear la carpeta temporal de órdenes para WhatsApp.');
        }

        if (file_put_contents($absolutePath, $contents) === false) {
            throw new RuntimeException('No se pudo guardar el PDF temporal de la orden '.$order->order_number.'.');
        }

        return [
            'relative_path' => $relativePath,
            'url' => rtrim((string) config('parameters.PUBLIC_URL'), '/').'/'.$relativePath,
        ];
    }

    public static function scheduleDeletion(string $relativePath): void
    {
        DeleteServiceOrderPatientDocumentJob::dispatch($relativePath)->delay(now()->addMinutes(self::PUBLIC_FILE_MINUTES));
    }

    /**
     * Borra la copia. Solo acepta rutas de esta carpeta con el nombre que ella genera.
     */
    public static function delete(string $relativePath): void
    {
        if (! self::isOwnPath($relativePath)) {
            return;
        }

        $absolutePath = self::absolutePath($relativePath);

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /**
     * Copias de más de un día (un borrado en cola que se perdió).
     */
    public static function purgeOld(): int
    {
        $directory = self::absolutePath(self::PUBLIC_DIRECTORY);

        if (! is_dir($directory)) {
            return 0;
        }

        $deleted = 0;

        foreach (glob($directory.'/*.pdf') ?: [] as $file) {
            if (preg_match(self::NAME_PATTERN, basename($file)) === 1 && filemtime($file) < time() - 86400 && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public static function isOwnPath(string $relativePath): bool
    {
        return str_starts_with($relativePath, self::PUBLIC_DIRECTORY.'/')
            && preg_match(self::NAME_PATTERN, substr($relativePath, strlen(self::PUBLIC_DIRECTORY) + 1)) === 1;
    }

    private static function absolutePath(string $relativePath): string
    {
        return public_path('storage/'.$relativePath);
    }
}
