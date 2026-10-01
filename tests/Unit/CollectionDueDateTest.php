<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Support\Collections\CollectionDueDate;
use App\Support\Collections\CollectionDueDateRepair;
use Carbon\CarbonImmutable;

uses(Tests\TestCase::class);

/**
 * Solo lectura: modelos en memoria, sin guardar en la base.
 */
it('lee las fechas en los formatos que hay en la base y rechaza las inválidas', function (?string $value, ?string $expected): void {
    expect(CollectionDueDate::parse($value)?->toDateString())->toBe($expected);
})->with([
    'oficial' => ['15/10/2026', '2026-10-15'],
    'sin ceros' => ['5/1/2027', '2027-01-05'],
    'con guiones' => ['26-01-2027', '2027-01-26'],
    'formato de filtro' => ['2026-10-15', '2026-10-15'],
    'con hora' => ['2026-10-15 00:00:00', '2026-10-15'],
    'día imposible' => ['31/02/2026', null],
    'texto' => ['pronto', null],
    'vacía' => ['', null],
    'nula' => [null, null],
]);

it('la fecha oficial es la de próximo pago aunque la de filtro diga otra cosa', function (): void {
    $collection = new Collection([
        'next_payment_date' => '15/10/2026',
        'filter_next_payment_date' => '2025-10-15',
        'status' => 'POR PAGAR',
    ]);
    $today = CarbonImmutable::parse('2026-10-01');

    expect(CollectionDueDate::of($collection)?->toDateString())->toBe('2026-10-15')
        ->and(CollectionDueDate::daysUntil($collection, $today))->toBe(14)
        ->and(CollectionDueDate::displayStatus($collection, $today))->toBe('POR PAGAR')
        ->and(CollectionDueDate::daysLabel($collection, $today))->toBe('Faltan 14 días');
});

it('deriva la fecha de filtro desde la oficial y normaliza el formato al guardar', function (array $attributes, string $official, string $filter): void {
    $collection = new Collection($attributes);

    CollectionDueDate::syncColumns($collection);

    expect($collection->next_payment_date)->toBe($official)
        ->and($collection->filter_next_payment_date)->toBe($filter);
})->with([
    'desincronizada' => [['next_payment_date' => '15/10/2026', 'filter_next_payment_date' => '2025-10-15'], '15/10/2026', '2026-10-15'],
    'con guiones' => [['next_payment_date' => '26-01-2027', 'filter_next_payment_date' => '2027-01-26'], '26/01/2027', '2027-01-26'],
    'sin filtro' => [['next_payment_date' => '09/12/2026', 'filter_next_payment_date' => null], '09/12/2026', '2026-12-09'],
    'solo filtro' => [['next_payment_date' => null, 'filter_next_payment_date' => '2026-12-09'], '09/12/2026', '2026-12-09'],
]);

it('no inventa una fecha si la oficial es ilegible', function (): void {
    $collection = new Collection(['next_payment_date' => 'pronto', 'filter_next_payment_date' => '2026-12-09']);

    CollectionDueDate::syncColumns($collection);

    expect($collection->next_payment_date)->toBe('pronto')
        ->and($collection->filter_next_payment_date)->toBe('2026-12-09');
});

it('el modelo sincroniza las dos fechas cada vez que se guarda una cuota', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/Collection.php');

    expect($model)
        ->toContain('static::saving(function (Collection $collection): void {')
        ->toContain('CollectionDueDate::syncColumns($collection);');
});

it('solo una cuota por pagar puede estar vencida', function (string $status, bool $overdue): void {
    $collection = new Collection(['next_payment_date' => '20/09/2026', 'status' => $status]);

    expect(CollectionDueDate::isOverdue($collection, CarbonImmutable::parse('2026-10-01')))->toBe($overdue);
})->with([
    ['POR PAGAR', true],
    ['PAGADO', false],
    ['ANULADO', false],
]);

it('la reparación clasifica cada cuota antes de tocarla', function (array $attributes, ?string $action): void {
    expect(CollectionDueDateRepair::classify(new Collection($attributes))['action'] ?? null)->toBe($action);
})->with([
    'ya sincronizada' => [['next_payment_date' => '15/10/2026', 'filter_next_payment_date' => '2026-10-15', 'expiration_date' => '15/10/2026'], null],
    'expiración cuadra con la oficial' => [['next_payment_date' => '15/10/2026', 'filter_next_payment_date' => '2025-10-15', 'expiration_date' => '20/10/2026'], CollectionDueDateRepair::ACTION_SYNC],
    'solo formato' => [['next_payment_date' => '26-01-2027', 'filter_next_payment_date' => '2027-01-26', 'expiration_date' => '26-01-2027'], CollectionDueDateRepair::ACTION_FORMAT],
    'sin filtro' => [['next_payment_date' => '09/12/2026', 'filter_next_payment_date' => null, 'expiration_date' => null], CollectionDueDateRepair::ACTION_SYNC],
    'expiración cuadra con el filtro' => [['next_payment_date' => '15/10/2026', 'filter_next_payment_date' => '2025-10-15', 'expiration_date' => '15/10/2025'], CollectionDueDateRepair::ACTION_REVIEW],
    'sin evidencia' => [['next_payment_date' => '15/10/2026', 'filter_next_payment_date' => '2025-10-15', 'expiration_date' => null], CollectionDueDateRepair::ACTION_REVIEW],
    'oficial ilegible' => [['next_payment_date' => 'pronto', 'filter_next_payment_date' => '2025-10-15'], CollectionDueDateRepair::ACTION_REVIEW],
    'sin ninguna fecha' => [['next_payment_date' => null, 'filter_next_payment_date' => null], null],
]);

it('la edición en la tabla de Gestión de Cobranza valida el formato de la fecha', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Collections/Tables/CollectionsTable.php');

    expect($table)
        ->toContain("->rules(['required', 'date_format:d/m/Y'])")
        ->toContain('CollectionDueDate::displayStatus($record, $today)');
});
