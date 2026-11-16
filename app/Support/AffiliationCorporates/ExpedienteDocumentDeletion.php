<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateDocument;
use App\Models\AffiliationCorporateObservation;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Elimina un documento del expediente digital de una afiliación corporativa sin
 * perder el rastro:
 *
 * - el archivo no se destruye: se mueve al disco privado (`local`), fuera del
 *   acceso público, en `expediente-eliminado/corporativo/{afiliación}/`;
 * - queda una observación en la bitácora de la afiliación (qué, quién, cuándo y por qué);
 * - queda el registro en SecurityAudit con la foto completa del documento.
 *
 * Todo en una transacción: si algo falla no se borra nada. El archivo público se
 * quita solo después del commit, y únicamente si ya existe la copia privada.
 */
final class ExpedienteDocumentDeletion
{
    public const ARCHIVE_DIRECTORY = 'expediente-eliminado/corporativo';

    public const MIN_REASON_LENGTH = 5;

    /**
     * @return array{document: string, archived_path: ?string}
     */
    public static function delete(AffiliationCorporate $affiliation, int $documentId, string $reason, ?User $actor): array
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('Escriba el motivo de la eliminación (mínimo '.self::MIN_REASON_LENGTH.' caracteres).');
        }

        $publicPathToRemove = null;

        $result = DB::transaction(function () use ($affiliation, $documentId, $reason, $actor, &$publicPathToRemove): array {
            $document = AffiliationCorporateDocument::query()->lockForUpdate()->find($documentId);

            if (! $document instanceof AffiliationCorporateDocument) {
                throw new InvalidArgumentException('El documento ya no existe: quizá otra persona lo eliminó. Recargue la página.');
            }

            if ((int) $document->affiliation_corporate_id !== (int) $affiliation->getKey()) {
                throw new InvalidArgumentException('El documento no pertenece a esta afiliación.');
            }

            $name = self::displayName($document);
            $archivedPath = self::archive($affiliation, $document);

            AffiliationCorporateObservation::query()->create([
                'affiliation_corporate_id' => $affiliation->getKey(),
                'description' => 'Documento eliminado del expediente digital: «'.$name.'» ('.self::sizeLabel($document->file_size).', cargado el '.($document->created_at?->format('d/m/Y H:i') ?? '—').'). '
                    .'Motivo: '.$reason.'. '
                    .($archivedPath !== null ? 'El archivo quedó resguardado en el archivo privado del sistema.' : 'El archivo ya no estaba en el servidor; solo se quitó el registro.'),
                'created_by' => $actor?->getKey() !== null ? (string) $actor->getKey() : 'INTEGRACORP',
            ]);

            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_EXPEDIENTE_DOCUMENT_DELETED', 'business.affiliation-corporates.expediente', [
                'affiliation_corporate_id' => $affiliation->getKey(),
                'affiliation_code' => $affiliation->code,
                'reason' => $reason,
                'archived_path' => $archivedPath,
                'document' => $document->only(['id', 'file_path', 'original_name', 'mime_type', 'file_size', 'uploaded_by', 'created_at']),
            ], $actor);

            $publicPathToRemove = $archivedPath !== null ? $document->file_path : null;
            $document->delete();

            return ['document' => $name, 'archived_path' => $archivedPath];
        });

        if (filled($publicPathToRemove)) {
            DB::afterCommit(static function () use ($publicPathToRemove): void {
                try {
                    Storage::disk('public')->delete((string) $publicPathToRemove);
                } catch (Throwable $exception) {
                    Log::warning('ExpedienteDocumentDeletion: no se pudo quitar el archivo público.', ['path' => $publicPathToRemove, 'error' => $exception->getMessage()]);
                }
            });
        }

        return $result;
    }

    /**
     * Copia el archivo al disco privado. Devuelve la ruta privada, o null si el archivo
     * ya no estaba en el disco público. Si la copia falla, aborta toda la eliminación.
     */
    private static function archive(AffiliationCorporate $affiliation, AffiliationCorporateDocument $document): ?string
    {
        $path = (string) $document->file_path;

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $target = self::ARCHIVE_DIRECTORY.'/'.$affiliation->getKey().'/'.$document->getKey().'-'.now()->format('YmdHis').'-'.basename($path);

        $stream = Storage::disk('public')->readStream($path);

        if ($stream === null || ! Storage::disk('local')->writeStream($target, $stream)) {
            throw new InvalidArgumentException('No se pudo resguardar el archivo antes de eliminarlo. No se eliminó nada.');
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $target;
    }

    public static function displayName(AffiliationCorporateDocument $document): string
    {
        $name = filled($document->original_name) ? (string) $document->original_name : basename((string) $document->file_path);

        return $name !== '' ? $name : 'documento #'.$document->getKey();
    }

    private static function sizeLabel(mixed $bytes): string
    {
        $bytes = is_numeric($bytes) ? (int) $bytes : 0;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.').' MB';
        }

        return number_format(max(0, $bytes) / 1024, 2, ',', '.').' KB';
    }
}
