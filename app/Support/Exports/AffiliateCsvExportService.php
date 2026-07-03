<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Affiliate;
use App\Support\CsvExportStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AffiliateCsvExportService
{
    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'ACTIVO' => 'Activo',
            'INACTIVO' => 'Inactivo',
            'EXCLUIDO' => 'Excluido',
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
            'Nombre titular',
            'Nombre afiliado',
            'Identificación',
            'Relación',
            'Plan',
            'Estatus',
            'Teléfono',
            'Correo',
            'Fecha de nacimiento',
            'Edad',
            'Sexo',
            'Dirección',
            'País',
            'Estado',
            'Ciudad',
            'Cobertura (USD)',
            'Fecha de registro',
        ];
    }

    /**
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_ids?: list<int|string>|null, affiliate_ids?: list<int|string>|null}  $filters
     */
    public function streamCsv(array $filters, string $panel = 'business'): StreamedResponse
    {
        $filename = 'afiliados_individuales_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($filters, $panel): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            self::query($filters, $panel)
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (Affiliate $record) use ($handle): void {
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
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_ids?: list<int|string>|null, affiliate_ids?: list<int|string>|null}  $filters
     */
    public static function query(array $filters, string $panel = 'business'): Builder
    {
        $query = Affiliate::query()->with([
            'plan:id,description',
            'coverage:id,price',
            'country:id,name',
            'state:id,definition',
            'city:id,definition',
            'affiliation:id,code,full_name_ti,ownerAccountManagers',
        ]);

        if ($panel === 'business' && Auth::user()?->is_accountManagers) {
            $query->whereHas(
                'affiliation',
                fn (Builder $affiliationQuery): Builder => $affiliationQuery->where('ownerAccountManagers', Auth::id()),
            );
        }

        $affiliateIds = array_values(array_filter(
            array_map('intval', (array) ($filters['affiliate_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        if ($affiliateIds !== []) {
            $query->whereIn('id', $affiliateIds);
        } else {
            $affiliationIds = array_values(array_filter(
                array_map('intval', (array) ($filters['affiliation_ids'] ?? [])),
                fn (int $id): bool => $id > 0,
            ));

            if ($affiliationIds !== []) {
                $query->whereIn('affiliation_id', $affiliationIds);
            }
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
    private static function row(Affiliate $record): array
    {
        return [
            (string) $record->id,
            (string) ($record->affiliation?->code ?? ''),
            (string) ($record->affiliation?->full_name_ti ?? ''),
            (string) ($record->full_name ?? ''),
            (string) ($record->nro_identificacion ?? ''),
            (string) ($record->relationship ?? ''),
            (string) ($record->plan?->description ?? ''),
            (string) ($record->status ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->birth_date ?? ''),
            (string) ($record->age ?? ''),
            (string) ($record->sex ?? ''),
            (string) ($record->address ?? ''),
            (string) ($record->country?->name ?? ''),
            (string) ($record->state?->definition ?? ''),
            (string) ($record->city?->definition ?? ''),
            (string) ($record->coverage?->price ?? ''),
            (string) ($record->created_at ?? ''),
        ];
    }
}
