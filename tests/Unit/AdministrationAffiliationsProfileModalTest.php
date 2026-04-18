<?php

declare(strict_types=1);

it('abre modal de perfil al hacer click en codigo de afiliaciones de administracion', function (): void {
    $individualTablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php';
    $corporateTablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php';
    $individualWorkspaceWrapperPath = dirname(__DIR__, 2).'/resources/views/filament/administration/affiliations/affiliation-workspace-modal-wrapper.blade.php';
    $individualWorkspaceLivewirePath = dirname(__DIR__, 2).'/resources/views/livewire/administration/affiliation-workspace-modal.blade.php';
    $individualLivewireClassPath = dirname(__DIR__, 2).'/app/Livewire/Administration/AffiliationWorkspaceModal.php';
    $corporateViewPath = dirname(__DIR__, 2).'/resources/views/filament/administration/affiliation-corporates/affiliation-corporate-quick-profile.blade.php';

    $individualTable = file_get_contents($individualTablePath);
    $corporateTable = file_get_contents($corporateTablePath);
    $individualWorkspaceWrapper = file_get_contents($individualWorkspaceWrapperPath);
    $individualWorkspaceLivewire = file_get_contents($individualWorkspaceLivewirePath);
    $individualLivewireClass = file_get_contents($individualLivewireClassPath);

    expect(file_exists($individualWorkspaceWrapperPath))->toBeTrue()
        ->and(file_exists($individualWorkspaceLivewirePath))->toBeTrue()
        ->and(file_exists($individualLivewireClassPath))->toBeTrue()
        ->and(file_exists($corporateViewPath))->toBeTrue();

    expect($individualTable)
        ->toContain("TextColumn::make('code')")
        ->toContain("Action::make('view_affiliation_profile')")
        ->toContain('modalHeading(\'Afiliación Individual · Workspace\')')
        ->toContain('modalWidth(\'7xl\')')
        ->toContain('filament.administration.affiliations.affiliation-workspace-modal-wrapper');

    expect($individualWorkspaceWrapper)
        ->toContain('@livewire(\'administration.affiliation-workspace-modal\'');

    expect($individualWorkspaceLivewire)
        ->toContain('Datos del afiliado')
        ->toContain('Cargar comprobante')
        ->toContain('Pagos y aprobación')
        ->toContain('Filtros rápidos')
        ->toContain('Misma funcionalidad del formulario original');

    expect($individualLivewireClass)
        ->toContain('class AffiliationWorkspaceModal extends Component')
        ->toContain('public function savePayment(): void')
        ->toContain('public function approvePayment(): void')
        ->toContain('public function updatedPaymentFormPaymentMethod(string $value): void')
        ->toContain('public string $paymentsStatusFilter = \'all\';');

    expect($corporateTable)
        ->toContain("TextColumn::make('code')")
        ->toContain("Action::make('view_affiliation_corporate_profile')")
        ->toContain('modalHeading(\'Afiliación Corporativa\')')
        ->toContain('modalWidth(\'5xl\')')
        ->toContain('filament.administration.affiliation-corporates.affiliation-corporate-quick-profile');
});
