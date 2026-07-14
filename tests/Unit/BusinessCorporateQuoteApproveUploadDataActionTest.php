<?php

declare(strict_types=1);

it('expone en negocios la accion de aprobar y cargar data de poblacion como en agentes', function (): void {
    $businessTable = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php'
    );

    expect($businessTable)
        ->toContain("Action::make('aproved')")
        ->toContain("->label('Aprobar / Cargar Data')")
        ->toContain("'APROBACIÓN DIRECTA PARA PRE-AFILIACIÓN'")
        ->toContain("FileUpload::make('data_doc')")
        ->toContain("'status' => 'APROBADA-DATA-ENVIADA'")
        ->toContain('SendNotificacionUploadDataCorporate::dispatch')
        ->toContain('NotificationController::sendUploadDataCorporate')
        ->toContain('AUDIT_BUSINESS_CORPORATE_QUOTE_APPROVED_DATA_UPLOADED')
        ->toContain('observation_dress_tailor != null');
});

it('mantiene la carga dress-taylor y la carga general como acciones separadas', function (): void {
    $businessTable = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php'
    );

    expect($businessTable)
        ->toContain("Action::make('upload_data_dress_tailor')")
        ->toContain("Action::make('aproved')")
        ->toContain('observation_dress_tailor == null')
        ->toContain('observation_dress_tailor != null');
});
