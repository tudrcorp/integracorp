<?php

declare(strict_types=1);

it('mantiene visible el boton de comprobante de pago en afiliaciones individuales general', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/General/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain("Action::make('upload')")
        ->toContain("->label('Comprobante de Pago')")
        ->not->toContain("if (\$record->payment_frequency == 'ANUAL' && \$record->paid_memberships()->count() == 1)")
        ->not->toContain("if (\$record->payment_frequency == 'SEMESTRAL' && \$record->paid_memberships()->count() == 2)")
        ->not->toContain("if (\$record->payment_frequency == 'TRIMESTRAL' && \$record->paid_memberships()->count() == 4)");
});

it('mantiene visible el boton de comprobante de pago en afiliaciones corporativas general', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/General/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain("Action::make('upload')")
        ->toContain("->label('Comprobante de Pago')")
        ->not->toContain("if (\$record->payment_frequency == 'ANUAL' && \$record->paid_membership_corporates()->count() == 1)")
        ->not->toContain("if (\$record->payment_frequency == 'SEMESTRAL' && \$record->paid_membership_corporates()->count() == 2)")
        ->not->toContain("if (\$record->payment_frequency == 'TRIMESTRAL' && \$record->paid_membership_corporates()->count() == 4)");
});

it('mantiene visible el boton de comprobante de pago en afiliaciones individuales master', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Master/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain("Action::make('upload')")
        ->toContain("->label('Comprobante de Pago')")
        ->not->toContain("if (\$record->payment_frequency == 'ANUAL' && \$record->paid_memberships()->count() == 1)")
        ->not->toContain("if (\$record->payment_frequency == 'SEMESTRAL' && \$record->paid_memberships()->count() == 2)")
        ->not->toContain("if (\$record->payment_frequency == 'TRIMESTRAL' && \$record->paid_memberships()->count() == 4)");
});

it('mantiene visible el boton de comprobante de pago en afiliaciones corporativas master', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Master/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain("Action::make('upload')")
        ->toContain("->label('Comprobante de Pago')")
        ->not->toContain("if (\$record->payment_frequency == 'ANUAL' && \$record->paid_membership_corporates()->count() == 1)")
        ->not->toContain("if (\$record->payment_frequency == 'SEMESTRAL' && \$record->paid_membership_corporates()->count() == 2)")
        ->not->toContain("if (\$record->payment_frequency == 'TRIMESTRAL' && \$record->paid_membership_corporates()->count() == 4)");
});

it('mantiene visible el boton de comprobante de pago en afiliaciones individuales agentes', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain("Action::make('upload')")
        ->toContain('Comprobante de Pago')
        ->not->toContain("if (\$record->payment_frequency == 'ANUAL' && \$record->paid_memberships()->count() == 1)")
        ->not->toContain("if (\$record->payment_frequency == 'SEMESTRAL' && \$record->paid_memberships()->count() == 2)")
        ->not->toContain("if (\$record->payment_frequency == 'TRIMESTRAL' && \$record->paid_memberships()->count() == 4)");
});

it('mantiene visible el boton de comprobante de pago en afiliaciones corporativas agentes', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Agents/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain("Action::make('upload')")
        ->toContain("->label('Comprobante de Pago')")
        ->not->toContain("if (\$record->payment_frequency == 'ANUAL' && \$record->paid_membership_corporates()->count() == 1)")
        ->not->toContain("if (\$record->payment_frequency == 'SEMESTRAL' && \$record->paid_membership_corporates()->count() == 2)")
        ->not->toContain("if (\$record->payment_frequency == 'TRIMESTRAL' && \$record->paid_membership_corporates()->count() == 4)");
});
