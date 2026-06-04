<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\DoctorNurse;
use App\Models\Supplier;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

final class GlobalSearchSupplierResultDetails
{
    /**
     * Un solo bloque HTML para evitar solapamientos en la rejilla por defecto de Filament.
     *
     * @return array<string, Htmlable|string>
     */
    public static function forRecord(Model $record): array
    {
        if (! $record instanceof Supplier && ! $record instanceof DoctorNurse) {
            return [];
        }

        return [
            "\u{200B}" => new HtmlString(self::buildBody($record)),
        ];
    }

    private static function buildBody(Supplier|DoctorNurse $record): string
    {
        $phone = filled($record->personal_phone)
            ? (string) $record->personal_phone
            : (filled($record->local_phone) ? (string) $record->local_phone : '—');

        $rows = [];

        if (
            filled($record->razon_social)
            && mb_strtolower(trim((string) $record->razon_social)) !== mb_strtolower(trim((string) ($record->name ?? '')))
        ) {
            $rows[] = self::rowFull(
                'Razón social',
                e((string) $record->razon_social),
            );
        }

        $rows[] = '<div class="fi-global-search-supplier-row fi-global-search-supplier-row--duo">'
            .self::field('RIF', e(filled($record->rif) ? (string) $record->rif : '—'))
            .self::field('Teléfono', e($phone))
            .'</div>';

        $rows[] = self::rowFull(
            'Correo',
            e(filled($record->correo_principal) ? (string) $record->correo_principal : '—'),
            isEmail: true,
        );

        $rows[] = '<div class="fi-global-search-supplier-row fi-global-search-supplier-row--duo">'
            .self::field(
                'Estatus en sistema',
                (string) GlobalSearchSupplierStatusLabel::sistemaHtml($record->status_sistema),
                raw: true,
            )
            .self::field(
                'Estatus de convenio',
                (string) GlobalSearchSupplierStatusLabel::convenioHtml($record->status_convenio),
                raw: true,
            )
            .'</div>';

        return '<div class="fi-global-search-supplier-body">'.implode('', $rows).'</div>';
    }

    private static function rowFull(string $label, string $value, bool $isEmail = false): string
    {
        $valueClass = $isEmail ? ' fi-global-search-supplier-value--email' : '';

        return '<div class="fi-global-search-supplier-row">'
            .self::field($label, $value, full: true, valueClass: $valueClass)
            .'</div>';
    }

    private static function field(
        string $label,
        string $value,
        bool $full = false,
        string $valueClass = '',
        bool $raw = false,
    ): string {
        $fullClass = $full ? ' fi-global-search-supplier-field--full' : '';

        return '<div class="fi-global-search-supplier-field'.$fullClass.'">'
            .'<span class="fi-global-search-supplier-label">'.e($label).'</span>'
            .'<span class="fi-global-search-supplier-value'.$valueClass.'">'.($raw ? $value : $value).'</span>'
            .'</div>';
    }
}
