<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Affiliations\Tables\AffiliationsTable;
use App\Models\Affiliation;
use Carbon\Carbon;

uses(Tests\TestCase::class);

it('no aplica clases cuando activated_at está vacío', function (): void {
    $record = new Affiliation;
    $record->activated_at = null;

    $classes = invokeRowClasses($record);

    expect($classes)->toBeEmpty();
});

it('no aplica clases cuando activated_at no es hoy', function (): void {
    $record = new Affiliation;
    $record->activated_at = '01/01/2020';

    $classes = invokeRowClasses($record);

    expect($classes)->toBeEmpty();
});

it('aplica clases ios success cuando activated_at es la fecha actual', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-26 12:00:00'));

    $record = new Affiliation;
    $record->activated_at = '26/03/2026';

    $classes = invokeRowClasses($record);

    expect($classes)->not->toBeEmpty()
        ->and($classes[0])->toContain('#34C759');

    Carbon::setTestNow();
});

/**
 * @return array<int, string>
 */
function invokeRowClasses(Affiliation $record): array
{
    $method = new \ReflectionMethod(AffiliationsTable::class, 'rowClassesForAffiliationActivatedToday');
    $method->setAccessible(true);

    /** @var array<int, string> */
    return $method->invoke(null, $record);
}
