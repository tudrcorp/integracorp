<?php

declare(strict_types=1);

use App\Http\Controllers\AgencyController;
use App\Models\Agency;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Estas pruebas solo corren en sqlite :memory: (phpunit.xml).
 * Nunca deben ejecutarse contra MySQL para evitar borrar datos reales.
 */
function skipUnlessAgencyIdentityUsesInMemorySqlite(): void
{
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('Prueba aislada de agencies: requiere sqlite en memoria.');
    }

    if (DB::connection()->getDatabaseName() !== ':memory:') {
        test()->markTestSkipped('Prueba aislada de agencies: requiere base :memory:.');
    }
}

beforeEach(function (): void {
    skipUnlessAgencyIdentityUsesInMemorySqlite();

    Schema::dropIfExists('agencies');

    Schema::create('agencies', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->string('code')->nullable();
        $table->string('name_corporative')->nullable();
        $table->timestamps();
    });

    DB::table('agencies')->insert([
        ['id' => 89, 'code' => 'TDG-189', 'name_corporative' => 'Ten Assist', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 90, 'code' => 'TDG-190', 'name_corporative' => 'Justo a Tiempo', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 91, 'code' => 'TDG-191', 'name_corporative' => 'INHOUSE', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

it('calcula el sufijo TDG a partir del id de agencia', function (): void {
    skipUnlessAgencyIdentityUsesInMemorySqlite();

    expect(AgencyController::codeSuffixForAgencyId(1))->toBe(101)
        ->and(AgencyController::codeSuffixForAgencyId(92))->toBe(192);
});

it('genera TDG-101 cuando no existen agencias', function (): void {
    skipUnlessAgencyIdentityUsesInMemorySqlite();

    DB::table('agencies')->delete();

    expect(AgencyController::generate_code_agency())->toBe('TDG-101');
});

it('genera el codigo TDG-192 cuando el ultimo id es 91', function (): void {
    skipUnlessAgencyIdentityUsesInMemorySqlite();

    expect(AgencyController::generate_code_agency())->toBe('TDG-192');
});

it('reserva id 92 y codigo TDG-192 aunque auto increment este desfasado', function (): void {
    skipUnlessAgencyIdentityUsesInMemorySqlite();

    $identity = DB::transaction(fn (): array => AgencyController::reserveNextAgencyIdentity());

    expect($identity)->toBe([
        'id' => 92,
        'code' => 'TDG-192',
    ]);

    $agency = new Agency;
    $agency->id = $identity['id'];
    $agency->code = $identity['code'];
    $agency->name_corporative = 'GuGUs';
    $agency->save();

    expect(Agency::query()->find(92)?->code)->toBe('TDG-192')
        ->and(Agency::query()->max('id'))->toBe(92);
});

it('continua la secuencia desde el codigo TDG aunque exista un id desfasado', function (): void {
    skipUnlessAgencyIdentityUsesInMemorySqlite();

    DB::table('agencies')->insert([
        'id' => 99_108,
        'code' => 'TDG-192',
        'name_corporative' => 'GuGUs',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(AgencyController::nextAgencyId())->toBe(93)
        ->and(AgencyController::generate_code_agency())->toBe('TDG-193');
});
