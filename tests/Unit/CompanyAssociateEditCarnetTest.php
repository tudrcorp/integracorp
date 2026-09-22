<?php

declare(strict_types=1);

use App\Jobs\RegenerateCompanyAssociateCarnetAfterEditJob;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

it('permite editar la ficha del asociado salvo que esté anulado', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/CompanyAssociateResource.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Pages/ViewCompanyAssociate.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Schemas/CompanyAssociateForm.php');

    expect($resource)->not->toBeFalse()
        ->toContain('EditCompanyAssociate::route')
        ->toContain('CompanyAssociateForm::configure')
        ->toContain('return $record instanceof CompanyAssociate && ! $record->isAnnulled();');

    expect($view)->not->toBeFalse()
        ->toContain("->label('Editar información')")
        ->toContain('! $record->isAnnulled()');

    expect($form)->not->toBeFalse()
        ->toContain("TextInput::make('full_name')")
        ->toContain("TextInput::make('identity_card')")
        ->toContain("DatePicker::make('flight_date')")
        ->toContain("TextInput::make('phone')")
        ->toContain("TextInput::make('contact_email')")
        ->toContain('exceptAssociateId');
});

it('regenera y reenvía el carnet en cola después de guardar la edición', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Pages/EditCompanyAssociate.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/RegenerateCompanyAssociateCarnetAfterEditJob.php');

    expect($page)->not->toBeFalse()
        ->toContain('RegenerateCompanyAssociateCarnetAfterEditJob::dispatch')
        ->toContain('mutateFormDataBeforeSave')
        ->toContain('CompanyAssociateRegistrar::calculateAge')
        ->toContain('wasChanged()')
        ->toContain("getUrl('view'");

    expect($job)->not->toBeFalse()
        ->toContain('CompanyAssociateCarnetGenerator::generate')
        ->toContain('CompanyAssociateDocumentsDeliverer::deliver')
        ->toContain('includeAnalystRecipients: false')
        ->toContain('isAnnulled()')
        ->toContain("onQueue((string) config('affiliate-card.documents_queue', 'documents'))");

    expect((new ReflectionClass(RegenerateCompanyAssociateCarnetAfterEditJob::class))
        ->implementsInterface(ShouldQueueAfterCommit::class))->toBeTrue();
});
