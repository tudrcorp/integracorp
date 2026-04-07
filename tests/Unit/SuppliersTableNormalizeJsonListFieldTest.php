<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Tables\SuppliersTable;

it('normaliza type_service guardado como string JSON en lista de etiquetas', function () {
    expect(SuppliersTable::normalizeJsonListField('["Hospital","Clínica"]'))->toBe(['Clínica', 'Hospital']);
    expect(SuppliersTable::normalizeJsonListField(['A', 'B']))->toBe(['A', 'B']);
    expect(SuppliersTable::normalizeJsonListField('solo texto'))->toBe(['solo texto']);
    expect(SuppliersTable::normalizeJsonListField(null))->toBeNull();
    expect(SuppliersTable::normalizeJsonListField('[]'))->toBeNull();
});
