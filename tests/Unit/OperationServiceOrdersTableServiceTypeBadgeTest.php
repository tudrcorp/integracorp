<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationServiceOrders\Tables\OperationServiceOrdersTable;

function serviceTypeBadgeColor(?string $serviceType): string
{
    $method = new ReflectionMethod(OperationServiceOrdersTable::class, 'serviceTypeColor');
    $method->setAccessible(true);

    return $method->invoke(null, $serviceType);
}

it('asigna un color distinto a cada tipo de servicio', function (string $serviceType, string $expected): void {
    expect(serviceTypeBadgeColor($serviceType))->toBe($expected);
})->with([
    'medicamentos' => ['MEDICAMENTOS', 'success'],
    'laboratorios' => ['LABORATORIOS', 'info'],
    'imagenología' => ['IMAGENOLOGIA', 'warning'],
    'especialista' => ['ESPECIALISTA', 'primary'],
]);

it('normaliza mayúsculas y espacios antes de resolver el color', function (): void {
    expect(serviceTypeBadgeColor('  medicamentos '))->toBe('success')
        ->and(serviceTypeBadgeColor('Imagenología Compleja'))->toBe('warning');
});

it('cae en gris cuando el tipo de servicio es desconocido o nulo', function (): void {
    expect(serviceTypeBadgeColor(null))->toBe('gray')
        ->and(serviceTypeBadgeColor(''))->toBe('gray')
        ->and(serviceTypeBadgeColor('OTRO SERVICIO'))->toBe('gray');
});

it('la columna de la tabla usa el color dinámico y conserva el ícono', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    expect($src)->toContain('->color(fn (?string $state): string => self::serviceTypeColor($state))')
        ->and($src)->toContain('->icon(fn (?string $state): string => self::serviceTypeIcon($state))');
});
