<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Enums\IdentityDocumentType;
use App\Models\Affiliation;
use App\Support\Affiliations\AffiliationDocumentPrefix;
use App\Support\Affiliations\AffiliationDocumentTypeColumns;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Select «Tipo de documento» (V, E, J, G) que va junto al número de cédula o RIF en los
 * formularios de afiliación de Negocios, Agentes, Master y General.
 *
 * Al crear sugiere V (persona) o J (empresa). Al editar una afiliación vieja sin tipo,
 * propone el que deduce {@see AffiliationDocumentPrefix}; el analista lo confirma al guardar.
 * Si las columnas aún no existen, no se muestra ni se guarda.
 */
final class DocumentTypeSelect
{
    public static function holder(): Select
    {
        return self::make('document_type_ti', IdentityDocumentType::V, fn (Model $record): IdentityDocumentType => AffiliationDocumentPrefix::forPerson(
            null,
            $record instanceof Affiliation ? $record->nro_identificacion_ti : null,
        ));
    }

    public static function payer(): Select
    {
        return self::make('document_type_payer', IdentityDocumentType::V, fn (Model $record): IdentityDocumentType => $record instanceof Affiliation
            ? AffiliationDocumentPrefix::forPayer(null, $record->nro_identificacion_payer, $record->full_name_payer)
            : IdentityDocumentType::V);
    }

    public static function companyRif(): Select
    {
        return self::make('document_type_rif', IdentityDocumentType::J, fn (Model $record): IdentityDocumentType => AffiliationDocumentPrefix::forCompany(null));
    }

    /**
     * @param  \Closure(Model): IdentityDocumentType  $suggestForRecord
     */
    private static function make(string $name, IdentityDocumentType $default, \Closure $suggestForRecord): Select
    {
        return Select::make($name)
            ->label('Tipo de documento')
            ->options(IdentityDocumentType::options())
            ->default($default->value)
            ->selectablePlaceholder(false)
            ->native(false)
            ->prefixIcon('heroicon-s-identification')
            ->helperText('V venezolano, E extranjero, J jurídico, G gubernamental.')
            ->rules([Rule::enum(IdentityDocumentType::class)])
            ->required(fn (): bool => AffiliationDocumentTypeColumns::available())
            ->visible(fn (): bool => AffiliationDocumentTypeColumns::available())
            ->dehydrated(fn (): bool => AffiliationDocumentTypeColumns::available())
            ->afterStateHydrated(function (Select $component, mixed $state, ?Model $record) use ($suggestForRecord): void {
                if (filled($state) && IdentityDocumentType::tryFrom((string) $state) !== null) {
                    return;
                }

                if ($record instanceof Model && $record->exists) {
                    $component->state($suggestForRecord($record)->value);
                }
            });
    }
}
