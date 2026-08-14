<?php

declare(strict_types=1);

it('declara la relación plan vía age range en el modelo Fee', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Models/Fee.php');

    expect($source)
        ->toContain('function plan(): HasOneThrough')
        ->toContain('Plan::class')
        ->toContain('AgeRange::class')
        ->toContain("'age_range_id'")
        ->toContain("'plan_id'")
        ->toContain('function coverageRecord(): BelongsTo')
        ->toContain("'neta'")
        ->toContain("'neta' => 'decimal:2'")
        ->not->toContain('function coverage(): BelongsTo');
});

it('usa coverageRecord en el formulario de tarifas para evitar choque con la columna coverage', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Fees/Schemas/FeeForm.php');

    expect($source)
        ->toContain("->relationship('coverageRecord', 'price')")
        ->not->toContain("->relationship('coverage', 'price')");
});

it('permite cargar neta de forma manual en el formulario de tarifas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Fees/Schemas/FeeForm.php');

    expect($source)
        ->toContain("TextInput::make('neta')")
        ->toContain("->label('Neta US$')")
        ->toContain('Carga manual');
});

it('muestra neta en la ficha de tarifa', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Fees/Schemas/FeeInfolist.php');

    expect($source)
        ->toContain("TextEntry::make('neta')")
        ->toContain("->label('Neta')");
});

it('muestra el plan en la tabla de tarifas del panel business', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Fees/Tables/FeesTable.php');

    expect($source)
        ->toContain("TextColumn::make('ageRange.plan.description')")
        ->toContain("->label('Plan')")
        ->toContain("Filter::make('plan_and_coverage')")
        ->toContain("Select::make('coverage_id')")
        ->toContain("SelectFilter::make('status')")
        ->toContain("SelectFilter::make('age_range_id')")
        ->toContain("TextColumn::make('price')")
        ->toContain("TextColumn::make('neta')")
        ->toContain("->label('Neta')")
        ->toContain("->money('USD')")
        ->toContain('ViewAction::make()')
        ->toContain('EditAction::make()')
        ->toContain("'ageRange.plan'")
        ->toContain('Creado desde')
        ->toContain('Creado hasta')
        ->not->toContain('TextInputColumn')
        ->not->toContain('Venta desde')
        ->not->toContain('Venta hasta');
});

it('filtra coberturas según el plan seleccionado', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Fees/Tables/FeesTable.php');

    expect($source)
        ->toContain("Filter::make('plan_and_coverage')")
        ->toContain("Select::make('plan_id')")
        ->toContain("Select::make('coverage_id')")
        ->toContain("->where('plan_id', \$planId)")
        ->toContain("->where('coverage_id', \$coverageId)")
        ->toContain('Seleccione un plan primero')
        ->toContain('->live()')
        ->toContain("\$set('coverage_id', null)");
});

it('abre un modal al clic en tarifa con monto, motivo obligatorio y auditoría', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Fees/Tables/FeesTable.php');

    expect($source)
        ->toContain("Action::make('editFeePrice')")
        ->toContain("TextInput::make('price')")
        ->toContain("Textarea::make('reason')")
        ->toContain('minLength(10)')
        ->toContain('FeePriceUpdater::update')
        ->toContain('Clic para editar el monto')
        ->toContain('trazas de seguridad');
});

it('actualiza el monto de tarifa con motivo y lo registra en SecurityAudit', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/FeePriceUpdater.php');

    expect($source)
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_BUSINESS_FEE_PRICE_UPDATED')
        ->toContain('business.fees.price.update')
        ->toContain('price_from')
        ->toContain('price_to')
        ->toContain('reason')
        ->toContain('Debe indicar el motivo del cambio de tarifa.')
        ->toContain('El nuevo monto debe ser distinto al actual.');
});
