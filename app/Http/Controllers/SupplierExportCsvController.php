<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SupplierExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'supplier_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    /**
     * Exporta los proveedores seleccionados a CSV.
     * Requiere ?token=xxx (token generado por la acción de la tabla con los IDs en cache).
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $ids = Cache::pull(self::CACHE_PREFIX.$token);

        if (! is_array($ids) || empty($ids)) {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $headers = [
            'Estado',
            'Ciudad',
            'Zona de Cobertura',
            'Clasificacion del Proveedor',
            'Tipo de Clinica',
            'Horario de Atencion',
            'Estatus del Convenio',
            'Estatus del Sistema',
            'Nombre del Proveedor',
            'RIF',
            'Razon Social',
            'Teléfono Celular',
            'Teléfono Local',
            'Correo Principal',
            'Afiliación Proveedor',
            'Ubicación Principal',
            'Convenio de Pago',
            'Tiempo de Credito',
            'Creado por',
        ];

        $filename = 'proveedores_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            Supplier::query()
                ->with(['supplierContactPrincipals', 'state', 'city', 'SupplierClasificacion'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (Supplier $record) use ($handle): void {
                    $row = $this->buildRow($record);
                    fputcsv($handle, $row);
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function buildRow(Supplier $record): array
    {
        return [
            $record->state?->definition ?? '',
            $record->city?->definition ?? '',
            $this->formatStateServices($record->state_services),
            $record->SupplierClasificacion?->description ?? '',
            (string) $record->tipo_clinica,
            (string) $record->horario,
            (string) $record->status_convenio,
            (string) $record->status_sistema,
            (string) $record->name,
            (string) $record->rif,
            (string) $record->razon_social,
            (string) $record->personal_phone,
            (string) $record->local_phone,
            $this->getPrincipalContactEmails($record),
            (string) $record->afiliacion_proveedor,
            (string) $record->ubicacion_principal,
            (string) $record->convenio_pago,
            (string) $record->tiempo_credito,
            (string) $record->created_by,
        ];
    }

    private function formatStateServices(mixed $value): string
    {
        return is_array($value) ? json_encode($value) : (string) $value;
    }

    private function getPrincipalContactEmails(Supplier $record): string
    {
        try {
            $contacts = $record->relationLoaded('supplierContactPrincipals')
                ? $record->supplierContactPrincipals
                : $record->supplierContactPrincipals()->get();

            $emails = $contacts
                ->pluck('email')
                ->map(fn ($email): string => is_string($email) ? trim($email) : '')
                ->filter(fn (string $email): bool => $email !== '')
                ->unique()
                ->values();

            return $emails->implode('; ');
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Genera un token y guarda los IDs en cache. Útil para la acción de la tabla.
     *
     * @param  array<int|string>  $ids
     */
    public static function storeIdsAndGetToken(array $ids): string
    {
        $ids = array_values(array_map('intval', $ids));
        $token = bin2hex(random_bytes(16));
        Cache::put(self::CACHE_PREFIX.$token, $ids, self::TOKEN_TTL_SECONDS);

        return $token;
    }
}
