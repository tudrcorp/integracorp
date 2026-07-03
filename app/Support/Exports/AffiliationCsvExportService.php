<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Affiliation;
use App\Support\CsvExportStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AffiliationCsvExportService
{
    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'ACTIVA' => 'Activa',
            'INACTIVA' => 'Inactiva',
            'EXCLUIDA' => 'Excluida',
            'PRE-AFILIADA' => 'Pre-afiliada',
        ];
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'ID',
            'Código afiliación',
            'Estatus',
            'Código agencia',
            'Código agente',
            'Nombre agente',
            'Plan',
            'Cobertura (USD)',
            'Frecuencia de pago',
            'Nombre titular',
            'CI titular',
            'Sexo titular',
            'Fecha nacimiento titular',
            'Teléfono titular',
            'Correo titular',
            'Ciudad titular',
            'Estado titular',
            'Fee anual',
            'Monto total',
            'Fecha activación',
            'Fecha vigencia',
            'Creado por',
            'Fecha de registro',
        ];
    }

    /**
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_ids?: list<int|string>|null}  $filters
     */
    public function streamCsv(array $filters, string $panel = 'business'): StreamedResponse
    {
        $filename = 'afiliaciones_individuales_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($filters, $panel): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            self::query($filters, $panel)
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (Affiliation $record) use ($handle): void {
                    fputcsv($handle, self::row($record));
                });

            fclose($handle);
        }, 200, self::downloadHeaders($filename));
    }

    /**
     * @return array<string, string>
     */
    public static function downloadHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /**
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_ids?: list<int|string>|null}  $filters
     */
    public static function query(array $filters, string $panel = 'business'): Builder
    {
        $query = Affiliation::query()->with([
            'agent:id,name,code_agent',
            'plan:id,description',
            'coverage:id,price',
            'city:id,definition',
            'state:id,definition',
        ]);

        if ($panel === 'business' && Auth::user()?->is_accountManagers) {
            $query->where('ownerAccountManagers', Auth::id());
        }

        $affiliationIds = array_values(array_filter(
            array_map('intval', (array) ($filters['affiliation_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        if ($affiliationIds !== []) {
            $query->whereIn('id', $affiliationIds);
        }

        if (filled($filters['plan_id'] ?? null)) {
            $query->where('plan_id', (int) $filters['plan_id']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private static function row(Affiliation $record): array
    {
        return [
            (string) $record->id,
            (string) ($record->code ?? ''),
            (string) ($record->status ?? ''),
            (string) ($record->code_agency ?? ''),
            (string) ($record->agent?->code_agent ?? $record->code_agent ?? ''),
            (string) ($record->agent?->name ?? $record->full_name_agent ?? ''),
            (string) ($record->plan?->description ?? ''),
            (string) ($record->coverage?->price ?? ''),
            (string) ($record->payment_frequency ?? ''),
            (string) ($record->full_name_ti ?? ''),
            (string) ($record->nro_identificacion_ti ?? ''),
            (string) ($record->sex_ti ?? ''),
            (string) ($record->birth_date_ti ?? ''),
            (string) ($record->phone_ti ?? ''),
            (string) ($record->email_ti ?? ''),
            (string) ($record->city?->definition ?? ''),
            (string) ($record->state?->definition ?? ''),
            (string) ($record->fee_anual ?? ''),
            (string) ($record->total_amount ?? ''),
            (string) ($record->activated_at ?? ''),
            (string) ($record->effective_date ?? ''),
            (string) ($record->created_by ?? ''),
            (string) ($record->created_at ?? ''),
        ];
    }
}
