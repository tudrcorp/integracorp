<?php

declare(strict_types=1);

use App\Models\CompanyAssociate;
use App\Models\CompanyResponsible;
use App\Support\Companies\CompanyAssociateDocumentsManualSender;

uses(Tests\TestCase::class);

it('permite envio manual cuando el asociado o responsable tienen contacto', function (): void {
    $associateWithoutContact = new CompanyAssociate;
    $associateWithoutContact->setRelation('responsible', new CompanyResponsible);

    $associateWithEmail = new CompanyAssociate(['email' => 'asociado@example.com']);
    $associateWithEmail->setRelation('responsible', new CompanyResponsible);

    $associateWithResponsiblePhone = new CompanyAssociate;
    $associateWithResponsiblePhone->setRelation('responsible', new CompanyResponsible([
        'phone' => '04121234567',
    ]));

    expect(CompanyAssociateDocumentsManualSender::canDeliver($associateWithoutContact))->toBeFalse()
        ->and(CompanyAssociateDocumentsManualSender::canDeliver($associateWithEmail))->toBeTrue()
        ->and(CompanyAssociateDocumentsManualSender::canDeliver($associateWithResponsiblePhone))->toBeTrue();
});

it('expone bulk action y servicio de envio manual en la tabla de asociados', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php');
    $actions = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Actions/CompanyAssociatesTableActions.php');
    $sender = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateDocumentsManualSender.php');
    $deliverer = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateDocumentsDeliverer.php');

    expect($table)
        ->toContain('BulkActionGroup::make')
        ->toContain('CompanyAssociatesTableActions::sendDocumentsBulkAction');

    expect($actions)
        ->toContain('sendDocumentsBulkAction')
        ->toContain('requiresConfirmation()')
        ->toContain('modalDescription(')
        ->toContain('modalSubmitActionLabel(\'Enviar documentos\')')
        ->toContain('modalCancelActionLabel(\'Cancelar\')')
        ->toContain('company-associate-documents-send-start')
        ->toContain('data-associate-documents-submit')
        ->toContain('resetAssociateDocumentsBulkSendProgress')
        ->toContain('send-documents-bulk-modal');

    expect($sender)
        ->toContain('CompanyAssociateCarnetGenerator::generate')
        ->toContain('CompanyAssociateDocumentsDeliverer::deliver')
        ->toContain('includeAnalystRecipients: false')
        ->toContain('sendWhatsAppImmediately: true');

    expect($deliverer)->toContain('includeAnalystRecipients');
});

it('la pagina de listado expone envio progresivo por asociado', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Pages/ListCompanyAssociates.php');
    $modal = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/company-associates/send-documents-bulk-modal.blade.php');

    expect($page)
        ->toContain('associateDocumentsBulkSendProgress')
        ->toContain('initAssociateDocumentsBulkSend')
        ->toContain('sendAssociateDocument')
        ->toContain('finishAssociateDocumentsBulkSendFromProgress')
        ->toContain('recordAssociateDocumentsBulkSendResult')
        ->toContain('resetAssociateDocumentsBulkSendProgress')
        ->toContain('deselectAllTableRecords');

    expect($modal)
        ->toContain('Progreso del envío')
        ->toContain('@entangle(\'associateDocumentsBulkSendProgress\')')
        ->toContain('initAssociateDocumentsBulkSend')
        ->toContain('finishAssociateDocumentsBulkSendFromProgress')
        ->toContain('progress.status === \'running\'');
});
