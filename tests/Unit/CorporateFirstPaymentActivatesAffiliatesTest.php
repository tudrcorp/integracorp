<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    /**
     * Conexión sqlite propia del test: el `.env` gana sobre `phpunit.xml`, así que
     * no se puede depender de la conexión por defecto. Nunca toca la base real.
     */
    config()->set('database.connections.corporate_activation_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'corporate_activation_testing');
    DB::purge('corporate_activation_testing');
    DB::setDefaultConnection('corporate_activation_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    $schema = Schema::connection('corporate_activation_testing');

    $schema->dropIfExists('affiliation_corporates');
    $schema->dropIfExists('affiliate_corporates');

    $schema->create('affiliation_corporates', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->string('status')->nullable();
        $table->string('activated_at')->nullable();
        $table->timestamps();
    });

    $schema->create('affiliate_corporates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->string('first_name')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    DB::table('affiliation_corporates')->insert([
        'id' => 1,
        'code' => 'TDEC-COR-TEST',
        'status' => 'PRE-APROBADA',
        'activated_at' => null,
    ]);

    DB::table('affiliate_corporates')->insert([
        ['affiliation_corporate_id' => 1, 'first_name' => 'Pre aprobado', 'status' => 'PRE-APROBADA'],
        ['affiliation_corporate_id' => 1, 'first_name' => 'Otro pre aprobado', 'status' => 'PRE-APROBADA'],
        ['affiliation_corporate_id' => 1, 'first_name' => 'Dado de baja', 'status' => 'INACTIVO'],
        ['affiliation_corporate_id' => 1, 'first_name' => 'Excluido', 'status' => 'EXCLUIDO'],
        ['affiliation_corporate_id' => 1, 'first_name' => 'Ya activo', 'status' => 'ACTIVO'],
    ]);
});

afterEach(function (): void {
    DB::purge('corporate_activation_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

it('activa a los afiliados vigentes al aprobar el primer pago', function (): void {
    $owner = AffiliationCorporate::query()->findOrFail(1);

    $activated = $owner->corporateAffiliates()
        ->whereNotIn('status', ['INACTIVO', 'EXCLUIDO'])
        ->update(['status' => 'ACTIVO']);

    $porEstado = DB::table('affiliate_corporates')
        ->selectRaw('status, COUNT(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

    expect($activated)->toBe(3)
        ->and($porEstado['ACTIVO'])->toBe(3)
        ->and($porEstado['INACTIVO'])->toBe(1)
        ->and($porEstado['EXCLUIDO'])->toBe(1)
        ->and($porEstado)->not->toHaveKey('PRE-APROBADA');
});

it('no reincorpora a quienes ya estaban dados de baja', function (): void {
    $owner = AffiliationCorporate::query()->findOrFail(1);

    $owner->corporateAffiliates()
        ->whereNotIn('status', ['INACTIVO', 'EXCLUIDO'])
        ->update(['status' => 'ACTIVO']);

    $baja = DB::table('affiliate_corporates')
        ->whereIn('first_name', ['Dado de baja', 'Excluido'])
        ->pluck('status', 'first_name');

    expect($baja['Dado de baja'])->toBe('INACTIVO')
        ->and($baja['Excluido'])->toBe('EXCLUIDO');
});

it('el controlador corporativo activa los afiliados como el individual', function (): void {
    $root = dirname(__DIR__, 2);
    $corporativo = file_get_contents($root.'/app/Http/Controllers/PaidMembershipCorporateController.php');
    $individual = file_get_contents($root.'/app/Http/Controllers/PaidMembershipController.php');

    expect($individual)->toContain("'status' => 'ACTIVO',");

    expect($corporativo)
        ->toContain('->corporateAffiliates()')
        ->toContain('->whereNotIn(\'status\', self::UNRECOVERABLE_AFFILIATE_STATUSES)')
        ->toContain("->update(['status' => 'ACTIVO'])")
        ->toContain("SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_AFFILIATES_ACTIVATED'")
        ->toContain("private const UNRECOVERABLE_AFFILIATE_STATUSES = ['INACTIVO', 'EXCLUIDO'];");
});

it('solo activa en el primer pago, no en los cobros siguientes', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipCorporateController.php');

    $primerPago = strpos($source, "if (! isset(\$data['collections'])) {");
    $activacion = strpos($source, '->corporateAffiliates()');
    $guardActivacion = strpos($source, 'if ($record->affiliation_corporate->activated_at == null) {');

    expect($primerPago)->not->toBeFalse()
        ->and($guardActivacion)->not->toBeFalse()
        ->and($activacion)->not->toBeFalse()
        ->and($primerPago)->toBeLessThan($guardActivacion)
        ->and($guardActivacion)->toBeLessThan($activacion);
});
