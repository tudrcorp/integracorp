<?php

declare(strict_types=1);

it('no repite en el paso de selección el resumen de ítems que ya muestran los pasos siguientes', function (): void {
    $form = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php');

    expect($form)
        ->not->toContain("Section::make('Resumen de selección')")
        ->not->toContain("Placeholder::make('manage_service_items_preview')")
        ->toContain("CheckboxList::make('managed_service_item_keys')")
        ->toContain("Section::make('Ítems incluidos en la orden')")
        ->toContain("Section::make('Ítems incluidos en la cotización')")
        ->toContain('CoordinationServiceItemsManager::manageServiceSelectedItemsTable(');
});
