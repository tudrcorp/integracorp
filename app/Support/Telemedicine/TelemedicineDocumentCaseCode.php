<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;

/**
 * Número de caso que se imprime en los documentos de telemedicina (informes,
 * récipe y órdenes).
 *
 * Se resuelve en la plantilla a partir de `telemedicine_case_id`, que ya viaja
 * en todos los payloads (creación de la consulta, edición, regeneración,
 * registrador AMD): así ningún origen puede olvidarlo. Un payload puede traer
 * `case_code` ya resuelto y entonces no se consulta la base.
 */
final class TelemedicineDocumentCaseCode
{
    public const LABEL = 'Nro. de Caso';

    /** Tope de la memoria por proceso: un worker de cola vive mucho tiempo. */
    private const MEMO_LIMIT = 500;

    /** @var array<int, string|null> */
    private static array $codes = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromData(array $data): ?string
    {
        $code = is_scalar($data['case_code'] ?? null) ? trim((string) $data['case_code']) : '';

        if ($code !== '') {
            return $code;
        }

        return self::forCaseId($data['telemedicine_case_id'] ?? null);
    }

    /**
     * El caso se busca sin scopes globales: un caso eliminado lógicamente
     * conserva su número en cualquier documento que se regenere.
     */
    public static function forCaseId(mixed $caseId): ?string
    {
        $id = is_numeric($caseId) ? (int) $caseId : 0;

        if ($id <= 0) {
            return null;
        }

        if (! array_key_exists($id, self::$codes)) {
            if (count(self::$codes) >= self::MEMO_LIMIT) {
                self::$codes = [];
            }

            $code = trim((string) TelemedicineCase::query()->withoutGlobalScopes()->whereKey($id)->value('code'));

            self::$codes[$id] = $code === '' ? null : $code;
        }

        return self::$codes[$id];
    }

    public static function flush(): void
    {
        self::$codes = [];
    }
}
