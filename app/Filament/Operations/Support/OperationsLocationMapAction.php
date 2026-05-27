<?php

declare(strict_types=1);

namespace App\Filament\Operations\Support;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class OperationsLocationMapAction
{
    public static function forSupplier(): Action
    {
        return self::make(
            name: 'searchSupplierLocationOnMap',
            modalHeading: 'Ubicación del proveedor',
            modalDescription: 'Busque establecimientos cercanos o una dirección y guárdela en la ficha del proveedor.',
            contextResolver: fn (Supplier $record): array => self::buildContext(
                recordId: $record->getKey(),
                recordLabel: (string) $record->name,
                initialAddress: $record->ubicacion_principal,
                livewireApplyMethod: 'applySupplierLocationFromMaps',
                addressInputLabel: 'Dirección del proveedor',
                saveButtonLabel: 'Guardar en proveedor',
                routeFromLabel: 'Recorrido desde el proveedor',
                useSubjectAddressButtonLabel: 'Usar dirección del proveedor',
                subjectRoleLabel: 'proveedor',
                mapAriaLabel: 'Mapa de ubicación del proveedor',
                introText: 'Busque la dirección del proveedor (marcador rojo). Haga clic en un establecimiento cercano o elija un destino para ver la ruta y el tiempo de recorrido.',
            ),
        );
    }

    public static function forAffiliate(): Action
    {
        return self::make(
            name: 'searchAffiliateLocationOnMap',
            modalHeading: 'Ubicación del afiliado',
            modalDescription: 'Busque establecimientos cercanos o una dirección y guárdela en la ficha del afiliado.',
            contextResolver: fn (Affiliate $record): array => self::buildContext(
                recordId: $record->getKey(),
                recordLabel: (string) ($record->full_name ?? 'Afiliado'),
                initialAddress: $record->address,
                livewireApplyMethod: 'applyAffiliateLocationFromMaps',
                addressInputLabel: 'Dirección del afiliado',
                saveButtonLabel: 'Guardar en afiliado',
                routeFromLabel: 'Recorrido desde el afiliado',
                useSubjectAddressButtonLabel: 'Usar dirección del afiliado',
                subjectRoleLabel: 'afiliado',
                mapAriaLabel: 'Mapa de ubicación del afiliado',
                introText: 'Busque la dirección del afiliado (marcador rojo). Haga clic en un establecimiento cercano o elija un destino para ver la ruta y el tiempo de recorrido.',
            ),
        );
    }

    public static function forAffiliateCorporate(): Action
    {
        return self::make(
            name: 'searchAffiliateCorporateLocationOnMap',
            modalHeading: 'Ubicación del afiliado corporativo',
            modalDescription: 'Busque establecimientos cercanos o una dirección y guárdela en la ficha del afiliado corporativo.',
            contextResolver: fn (AffiliateCorporate $record): array => self::buildContext(
                recordId: $record->getKey(),
                recordLabel: trim((string) ($record->first_name ?? '').' '.(string) ($record->last_name ?? '')) ?: 'Afiliado corporativo',
                initialAddress: $record->address,
                livewireApplyMethod: 'applyAffiliateCorporateLocationFromMaps',
                addressInputLabel: 'Dirección del afiliado',
                saveButtonLabel: 'Guardar en afiliado',
                routeFromLabel: 'Recorrido desde el afiliado',
                useSubjectAddressButtonLabel: 'Usar dirección del afiliado',
                subjectRoleLabel: 'afiliado',
                mapAriaLabel: 'Mapa de ubicación del afiliado corporativo',
                introText: 'Busque la dirección del afiliado (marcador rojo). Haga clic en un establecimiento cercano o elija un destino para ver la ruta y el tiempo de recorrido.',
            ),
        );
    }

    public static function forAffiliationCorporateOnAffiliateCorporate(): Action
    {
        return self::make(
            name: 'searchAffiliationCorporateLocationOnMap',
            modalHeading: 'Dirección de la empresa',
            modalDescription: 'Busque establecimientos cercanos o una dirección y guárdela en el corporativo contratante.',
            contextResolver: function (AffiliateCorporate $record): array {
                $corporate = $record->affiliationCorporate;
                $corporateId = $corporate?->getKey() ?? $record->getKey();

                return self::buildContext(
                    recordId: 'ac-'.$corporateId,
                    recordLabel: (string) ($corporate?->name_corporate ?? 'Empresa'),
                    initialAddress: $corporate?->address,
                    livewireApplyMethod: 'applyAffiliationCorporateLocationFromMaps',
                    addressInputLabel: 'Dirección de la empresa',
                    saveButtonLabel: 'Guardar en corporativo',
                    routeFromLabel: 'Recorrido desde la empresa',
                    useSubjectAddressButtonLabel: 'Usar dirección de la empresa',
                    subjectRoleLabel: 'empresa',
                    mapAriaLabel: 'Mapa de ubicación del corporativo',
                    introText: 'Busque la dirección de la empresa (marcador rojo). Haga clic en un establecimiento cercano o elija un destino para ver la ruta y el tiempo de recorrido.',
                );
            },
        );
    }

    /**
     * @param  callable(Model): array<string, mixed>  $contextResolver
     */
    private static function make(string $name, string $modalHeading, string $modalDescription, callable $contextResolver): Action
    {
        return Action::make($name)
            ->label('Buscar en mapa')
            ->icon(Heroicon::OutlinedMap)
            ->iconButton()
            ->color('primary')
            ->tooltip('Buscar o actualizar la dirección con Google Maps')
            ->modalHeading($modalHeading)
            ->modalDescription($modalDescription)
            ->modalIcon(Heroicon::OutlinedMapPin)
            ->modalIconColor('primary')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (Model $record): \Illuminate\Contracts\View\View => view(
                'filament.operations.shared.location-maps-modal',
                $contextResolver($record),
            ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildContext(
        int|string $recordId,
        string $recordLabel,
        ?string $initialAddress,
        string $livewireApplyMethod,
        string $addressInputLabel,
        string $saveButtonLabel,
        string $routeFromLabel,
        string $useSubjectAddressButtonLabel,
        string $subjectRoleLabel,
        string $mapAriaLabel,
        string $introText,
    ): array {
        return [
            'recordId' => $recordId,
            'recordLabel' => $recordLabel,
            'initialAddress' => $initialAddress,
            'livewireApplyMethod' => $livewireApplyMethod,
            'addressInputLabel' => $addressInputLabel,
            'saveButtonLabel' => $saveButtonLabel,
            'routeFromLabel' => $routeFromLabel,
            'useSubjectAddressButtonLabel' => $useSubjectAddressButtonLabel,
            'subjectRoleLabel' => $subjectRoleLabel,
            'mapAriaLabel' => $mapAriaLabel,
            'introText' => $introText,
        ];
    }
}
