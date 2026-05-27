@include('filament.operations.shared.location-maps-modal', [
    'recordId' => $supplierId ?? 0,
    'recordLabel' => filled($supplierName ?? null) ? trim((string) $supplierName) : 'Proveedor',
    'initialAddress' => $initialAddress ?? null,
    'livewireApplyMethod' => 'applySupplierLocationFromMaps',
    'addressInputLabel' => 'Dirección del proveedor',
    'saveButtonLabel' => 'Guardar en proveedor',
    'routeFromLabel' => 'Recorrido desde el proveedor',
    'useSubjectAddressButtonLabel' => 'Usar dirección del proveedor',
    'subjectRoleLabel' => 'proveedor',
    'mapAriaLabel' => 'Mapa de ubicación del proveedor',
    'introText' => 'Busque la dirección del proveedor (marcador rojo). Haga clic en un establecimiento cercano o elija un destino para ver la ruta y el tiempo de recorrido.',
])
