<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\DoctorNurse;
use App\Models\Supplier;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

final class GlobalSearchSupplierResultTitle
{
    public static function html(Model $record, string $providerKind): Htmlable
    {
        $displayName = self::displayName($record);
        $kindLabel = $providerKind === 'juridico' ? 'Jurídico' : 'Natural';
        $kindClass = $providerKind === 'juridico'
            ? 'fi-global-search-supplier-badge--kind-juridico'
            : 'fi-global-search-supplier-badge--kind-natural';

        return new HtmlString(
            '<span class="fi-global-search-supplier-title flex min-w-0 flex-wrap items-start gap-x-2 gap-y-1.5">'
            .'<span class="min-w-0 flex-1 font-semibold leading-snug text-gray-950 dark:text-white">'.e($displayName).'</span>'
            .'<span class="fi-global-search-supplier-badge fi-global-search-supplier-badge--kind '.$kindClass.'">'.e($kindLabel).'</span>'
            .'</span>'
        );
    }

    private static function displayName(Model $record): string
    {
        if ($record instanceof Supplier || $record instanceof DoctorNurse) {
            if (filled($record->name)) {
                return trim((string) $record->name);
            }

            if (filled($record->razon_social)) {
                return trim((string) $record->razon_social);
            }

            if (filled($record->rif)) {
                return trim((string) $record->rif);
            }
        }

        return 'Proveedor sin nombre';
    }
}
