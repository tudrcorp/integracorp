<?php

declare(strict_types=1);

use App\Support\Affiliations\AffiliationIndividualQuoteField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    if (! Schema::hasTable('individual_quotes')) {
        Schema::create('individual_quotes', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('full_name')->nullable();
            $table->string('created_by');
            $table->timestamps();
        });
    }

    DB::beginTransaction();
});

afterEach(fn () => DB::rollBack());

function insertIndividualQuoteForFieldTest(?string $fullName, string $code): int
{
    return (int) DB::table('individual_quotes')->insertGetId([
        'code' => $code,
        'full_name' => $fullName,
        'created_by' => 'TEST',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return array{0: string, 1: string}
 */
function individualQuoteFieldSources(): array
{
    $root = dirname(__DIR__, 2);

    return [
        file_get_contents($root.'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php'),
        file_get_contents($root.'/app/Filament/Resources/Affiliations/Schemas/AffiliationForm.php'),
    ];
}

function individualQuoteSelectBlock(string $source, string $until): string
{
    $start = strpos($source, "Select::make('individual_quote_id')");

    expect($start)->not->toBeFalse();

    return substr($source, $start, strpos($source, $until, $start) - $start);
}

it('etiqueta la cotización con nombre y código', function (): void {
    $quoteId = insertIndividualQuoteForFieldTest('DAISI DELGADO', 'COT-IND-TEST-01');

    expect(AffiliationIndividualQuoteField::optionLabel($quoteId))->toBe('DAISI DELGADO · COT-IND-TEST-01')
        ->and(AffiliationIndividualQuoteField::optionLabel((string) $quoteId))->toBe('DAISI DELGADO · COT-IND-TEST-01');
});

it('usa «Sin nombre» si la cotización no trae nombre', function (): void {
    $quoteId = insertIndividualQuoteForFieldTest(null, 'COT-IND-TEST-02');

    expect(AffiliationIndividualQuoteField::optionLabel($quoteId))->toBe('Sin nombre · COT-IND-TEST-02');
});

it('no etiqueta una cotización inexistente para que el alta la siga rechazando', function (): void {
    $missingId = (int) DB::table('individual_quotes')->max('id') + 900_000;

    expect(AffiliationIndividualQuoteField::optionLabel($missingId))->toBeNull();
});

it('no etiqueta valores vacíos o inválidos', function (mixed $value): void {
    expect(AffiliationIndividualQuoteField::optionLabel($value))->toBeNull();
})->with([
    'nulo' => [null],
    'vacío' => [''],
    'cero' => [0],
    'negativo' => ['-5'],
    'texto' => ['abc'],
]);

it('oculta «Nombre del cliente» al editar en Negocios sin cargar todas las cotizaciones', function (): void {
    [$business] = individualQuoteFieldSources();
    $field = individualQuoteSelectBlock($business, "TextInput::make('plan_generator_client')");

    expect($field)
        ->toContain("->hiddenOn('edit')")
        ->toContain('AffiliationIndividualQuoteField::optionLabel($value)')
        ->toContain('->disabled()')
        ->not->toContain('IndividualQuote::all()');
});

it('oculta «Nombre del cliente» al editar en Admin', function (): void {
    [, $admin] = individualQuoteFieldSources();
    $field = individualQuoteSelectBlock($admin, "Select::make('plan_id')");

    expect($field)
        ->toContain("->hiddenOn('edit')")
        ->toContain('->required()');
});
