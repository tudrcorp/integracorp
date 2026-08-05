<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationServiceOrders\Tables\OperationServiceOrdersTable;
use App\Models\OperationServiceOrder;
use Illuminate\Database\Eloquent\Builder;

uses(Tests\TestCase::class);

it('define búsqueda global por paciente, caso, descripción y proveedor', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    expect($src)->toContain('applyTableSearch')
        ->and($src)->toContain('->searchable()')
        ->and($src)->toContain('->searchUsing(')
        ->and($src)->toContain('LOWER(COALESCE(patient')
        ->and($src)->toContain('LOWER(COALESCE(full_name')
        ->and($src)->toContain('LOWER(COALESCE(patient_name')
        ->and($src)->toContain('LOWER(COALESCE(code')
        ->and($src)->toContain('LOWER(COALESCE(description')
        ->and($src)->toContain("'supplier'")
        ->and($src)->toContain('supplier_external');
});

it('applyTableSearch construye un where con LOWER para coincidencia case-insensitive', function (): void {
    $query = OperationServiceOrder::query();

    $result = OperationServiceOrdersTable::applyTableSearch($query, 'Nancy');

    expect($result)->toBeInstanceOf(Builder::class);

    $sql = mb_strtolower($result->toSql());

    expect($sql)->toContain('lower(order_number) like ?')
        ->and($sql)->toContain('lower(coalesce(description')
        ->and($sql)->toContain('exists')
        ->and($result->getBindings())->toContain('%nancy%');
});

it('applyTableSearch no altera la query cuando el término está vacío', function (): void {
    $query = OperationServiceOrder::query();
    $sqlBefore = $query->toSql();

    $result = OperationServiceOrdersTable::applyTableSearch($query, '   ');

    expect($result->toSql())->toBe($sqlBefore);
});
