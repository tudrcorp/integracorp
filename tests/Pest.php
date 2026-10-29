<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function insertPublicAiAgentTestAgency(array $attributes): void
{
    if (! Illuminate\Support\Facades\Schema::hasTable('agencies')) {
        Illuminate\Support\Facades\Schema::create('agencies', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('name_corporative')->nullable();
            $table->string('code')->nullable();
            $table->string('rif')->nullable();
        });
    }

    $row = [
        'id' => $attributes['id'],
        'name_corporative' => $attributes['name_corporative'],
    ];

    if (isset($attributes['code']) && Illuminate\Support\Facades\Schema::hasColumn('agencies', 'code')) {
        $row['code'] = $attributes['code'];
    }

    if (Illuminate\Support\Facades\Schema::hasColumn('agencies', 'rif')) {
        $row['rif'] = $attributes['rif'] ?? null;
    }

    if (Illuminate\Support\Facades\Schema::hasColumn('agencies', 'email')) {
        $row['email'] = $attributes['email'] ?? 'chat-agency-'.$attributes['id'].'@test.invalid';
    }

    if (Illuminate\Support\Facades\Schema::hasColumn('agencies', 'phone')) {
        $row['phone'] = $attributes['phone'] ?? '04140000000';
    }

    if (Illuminate\Support\Facades\Schema::hasColumn('agencies', 'agency_type_id')) {
        $row['agency_type_id'] = $attributes['agency_type_id']
            ?? Illuminate\Support\Facades\DB::table('agencies')->whereNotNull('agency_type_id')->value('agency_type_id')
            ?? 1;
    }

    if (Illuminate\Support\Facades\Schema::hasColumn('agencies', 'status') && ! isset($row['status'])) {
        $row['status'] = $attributes['status']
            ?? Illuminate\Support\Facades\DB::table('agencies')->whereNotNull('status')->value('status')
            ?? 'ACTIVO';
    }

    Illuminate\Support\Facades\DB::table('agencies')->where('id', $attributes['id'])->delete();
    Illuminate\Support\Facades\DB::table('agencies')->insert($row);
}

function ensureSqliteInMemoryDatabaseOrSkip(): void
{
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        test()->markTestSkipped('Este test solo puede ejecutarse con sqlite en memoria para no alterar la base de datos real.');
    }
}

/**
 * Esquema mínimo en sqlite `:memory:` para los tests de la Propuesta Económica.
 *
 * Los deja deterministas y, sobre todo, fuera de la base de desarrollo: se
 * ejecutan igual con la config cacheada (que apunta a MySQL) que sin ella.
 */
function bootTuDrQuoteSqliteSchema(): void
{
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        /** Sin esto la sesión buscaría la tabla `sessions` de MySQL. */
        'session.driver' => 'array',
    ]);

    Illuminate\Support\Facades\DB::purge('sqlite');
    Illuminate\Support\Facades\DB::reconnect('sqlite');

    $schema = Illuminate\Support\Facades\Schema::connection('sqlite');

    foreach ([
        'users', 'age_ranges', 'coverages', 'fees',
        'individual_quotes', 'detail_individual_quotes',
        'corporate_quotes', 'detail_corporate_quotes',
        'quote_document_layout_settings', 'logs',
    ] as $table) {
        $schema->dropIfExists($table);
    }

    /** `SecurityAudit` escribe aquí en cada acción auditada. */
    $schema->create('logs', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('action')->nullable();
        $table->string('route')->nullable();
        $table->text('response')->nullable();
        $table->string('method')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    $schema->create('users', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('status')->nullable();
        $table->text('departament')->nullable();
        $table->string('remember_token', 100)->nullable();
        $table->timestamps();
    });

    $schema->create('age_ranges', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('range');
    });

    $schema->create('coverages', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->decimal('price', 10, 2);
    });

    $schema->create('fees', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('age_range_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->decimal('price', 10, 2)->default(0);
        $table->string('status')->nullable();
        $table->timestamps();
    });

    foreach (['individual', 'corporate'] as $scope) {
        $schema->create($scope.'_quotes', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('full_name')->nullable();
            $table->string('created_by')->nullable();
            $table->string('plan')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('storefront_user_id')->nullable();
            $table->timestamps();
        });

        $schema->create('detail_'.$scope.'_quotes', function (Illuminate\Database\Schema\Blueprint $table) use ($scope): void {
            $table->id();
            $table->unsignedBigInteger($scope.'_quote_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('age_range_id');
            $table->unsignedBigInteger('coverage_id')->nullable();
            $table->integer('total_persons')->default(1);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('subtotal_anual', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    $schema->create('quote_document_layout_settings', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('scope', 20)->unique();
        $table->unsignedTinyInteger('total_pages')->default(4);
        $table->unsignedTinyInteger('calculations_page')->default(3);
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });

    seedTuDrQuoteCatalog();
}

/**
 * Rangos, coberturas y tarifas reales del Inicial y el Especial.
 */
function seedTuDrQuoteCatalog(): void
{
    $db = Illuminate\Support\Facades\DB::connection('sqlite');

    $db->table('age_ranges')->insert([
        ['id' => 1, 'range' => '0 a 99'],
        ['id' => 2, 'range' => '0 A 30'],
        ['id' => 3, 'range' => '31 a 65'],
    ]);

    $coberturas = [5000, 10000, 20000, 30000, 40000, 50000];

    foreach ($coberturas as $indice => $precio) {
        $db->table('coverages')->insert(['id' => $indice + 1, 'price' => $precio]);
    }

    /** Plan Inicial: tarifa única, sin cobertura. */
    $db->table('fees')->insert([
        'plan_id' => 1,
        'age_range_id' => 1,
        'coverage_id' => null,
        'price' => 160,
        'status' => 'ACTIVO',
    ]);

    $especial = [
        2 => [311, 339, 431, 527, 606, 644],
        3 => [339, 377, 459, 589, 676, 904],
    ];

    foreach ($especial as $rangeId => $tarifas) {
        foreach ($tarifas as $indice => $tarifa) {
            $db->table('fees')->insert([
                'plan_id' => 3,
                'age_range_id' => $rangeId,
                'coverage_id' => $indice + 1,
                'price' => $tarifa,
                'status' => 'ACTIVO',
            ]);
        }
    }
}

/**
 * Usuario interno de pruebas para los flujos del panel de negocios.
 *
 * @param  list<string>  $departments
 */
function makeTuDrQuoteUser(string $name = 'Analista de prueba', array $departments = ['NEGOCIOS', 'SUPERADMIN']): App\Models\User
{
    $id = Illuminate\Support\Facades\DB::table('users')->insertGetId([
        'name' => $name,
        'email' => Illuminate\Support\Str::slug($name).'-'.uniqid().'@tudrencasa.com',
        'password' => bcrypt('secret-de-prueba'),
        'status' => 'ACTIVO',
        'departament' => json_encode($departments),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return App\Models\User::query()->findOrFail($id);
}

/**
 * Esquema mínimo para los avisos de WhatsApp del módulo de cotizaciones.
 */
function bootQuoteWhatsAppSqliteSchema(): void
{
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'cache.default' => 'array',
        'queue.default' => 'sync',
        'session.driver' => 'array',
    ]);

    Illuminate\Support\Facades\DB::purge('sqlite');
    Illuminate\Support\Facades\DB::reconnect('sqlite');

    $schema = Illuminate\Support\Facades\Schema::connection('sqlite');

    foreach (['users', 'notifications', 'logs'] as $table) {
        $schema->dropIfExists($table);
    }

    /** `SecurityAudit` y el tracker de sesión escriben aquí. */
    $schema->create('logs', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('action')->nullable();
        $table->string('route')->nullable();
        $table->text('response')->nullable();
        $table->string('method')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    $schema->create('users', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('status')->nullable();
        $table->text('departament')->nullable();
        $table->string('remember_token', 100)->nullable();
        $table->timestamps();
    });

    /** Bandeja de notificaciones del panel. */
    $schema->create('notifications', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });
}

/**
 * Catálogo de upgrades en la conexión por defecto (que el test ya apuntó a
 * sqlite `:memory:`), con un juego de datos fijo:
 *
 * - 1 ODONTOLOGÍA (activo): cobertura 10 (5.000 US$) 0–35 = 40, 36–64 = 60;
 *   cobertura 11 (10.000 US$) 0–64 = 80.
 * - 2 ÓPTICA (activo): cobertura 20 (sin monto) 0–35 = 20.
 * - 3 VIEJO (inactivo): cobertura 30 0–120 = 10.
 */
function createUpgradeCatalogTestSchema(bool $withFixture = true): void
{
    $schema = Illuminate\Support\Facades\Schema::connection(null);

    $schema->create('upgrades', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('code', 40)->unique();
        $table->string('name', 120);
        $table->text('description')->nullable();
        $table->string('status', 20)->default('ACTIVO');
        $table->unsignedBigInteger('legacy_upgrade_benefit_id')->nullable();
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });

    $schema->create('upgrade_services', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('upgrade_id');
        $table->string('name', 160);
        $table->text('description')->nullable();
        $table->unsignedSmallInteger('sort')->default(0);
        $table->string('status', 20)->default('ACTIVO');
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });

    $schema->create('upgrade_coverages', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('upgrade_id');
        $table->decimal('amount', 12, 2)->nullable();
        $table->unsignedSmallInteger('sort')->default(0);
        $table->string('status', 20)->default('ACTIVO');
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });

    $schema->create('upgrade_coverage_age_ranges', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('upgrade_coverage_id');
        $table->unsignedTinyInteger('age_from');
        $table->unsignedTinyInteger('age_to');
        $table->decimal('price', 10, 2);
        $table->string('status', 20)->default('ACTIVO');
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });

    if (! $withFixture) {
        return;
    }

    $db = Illuminate\Support\Facades\DB::connection(null);

    $db->table('upgrades')->insert([
        ['id' => 1, 'code' => 'UPG-0001', 'name' => 'ODONTOLOGÍA', 'status' => 'ACTIVO'],
        ['id' => 2, 'code' => 'UPG-0002', 'name' => 'ÓPTICA', 'status' => 'ACTIVO'],
        ['id' => 3, 'code' => 'UPG-0003', 'name' => 'VIEJO', 'status' => 'INACTIVO'],
    ]);

    $db->table('upgrade_coverages')->insert([
        ['id' => 10, 'upgrade_id' => 1, 'amount' => 5000, 'sort' => 0, 'status' => 'ACTIVO'],
        ['id' => 11, 'upgrade_id' => 1, 'amount' => 10000, 'sort' => 1, 'status' => 'ACTIVO'],
        ['id' => 20, 'upgrade_id' => 2, 'amount' => null, 'sort' => 0, 'status' => 'ACTIVO'],
        ['id' => 30, 'upgrade_id' => 3, 'amount' => null, 'sort' => 0, 'status' => 'ACTIVO'],
    ]);

    $db->table('upgrade_coverage_age_ranges')->insert([
        ['id' => 100, 'upgrade_coverage_id' => 10, 'age_from' => 0, 'age_to' => 35, 'price' => 40, 'status' => 'ACTIVO'],
        ['id' => 101, 'upgrade_coverage_id' => 10, 'age_from' => 36, 'age_to' => 64, 'price' => 60, 'status' => 'ACTIVO'],
        ['id' => 110, 'upgrade_coverage_id' => 11, 'age_from' => 0, 'age_to' => 64, 'price' => 80, 'status' => 'ACTIVO'],
        ['id' => 200, 'upgrade_coverage_id' => 20, 'age_from' => 0, 'age_to' => 35, 'price' => 20, 'status' => 'ACTIVO'],
        ['id' => 300, 'upgrade_coverage_id' => 30, 'age_from' => 0, 'age_to' => 120, 'price' => 10, 'status' => 'ACTIVO'],
    ]);
}

/**
 * Columnas de una tabla de asignación de upgrades (corporativa o individual).
 */
function upgradeAssignmentTestColumns(Illuminate\Database\Schema\Blueprint $table): void
{
    $table->unsignedBigInteger('upgrade_id')->nullable();
    $table->unsignedBigInteger('upgrade_coverage_id')->nullable();
    $table->unsignedBigInteger('upgrade_coverage_age_range_id')->nullable();
    $table->string('name', 120);
    $table->decimal('coverage_amount', 12, 2)->nullable();
    $table->unsignedTinyInteger('age_at_assignment')->nullable();
    $table->decimal('amount', 10, 2);
    $table->string('billing_mode', 20)->nullable();
    $table->unsignedBigInteger('charged_collection_id')->nullable();
    $table->string('ils_voucher_code', 80)->nullable();
    $table->date('ils_date_init')->nullable();
    $table->date('ils_date_end')->nullable();
    $table->string('ils_document_path')->nullable();
    $table->string('status', 20)->default('ACTIVO');
    $table->unsignedBigInteger('replaces_id')->nullable();
    $table->string('created_by')->nullable();
    $table->string('updated_by')->nullable();
    $table->timestamp('deactivated_at')->nullable();
    $table->string('deactivated_by')->nullable();
    $table->string('deactivation_reason', 60)->nullable();
    $table->timestamps();
}

/**
 * Vouchers ILS válidos para `add()` de los gestores de upgrades: un comprobante
 * por afiliado en el disco `public` (el test debe llamar antes a
 * `Storage::fake('public')`) y un número que no se repite.
 *
 * @param  list<int>  $affiliateIds
 * @return array<int, array{code: string, date_init: string, date_end: string, document_path: string}>
 */
function upgradeVouchersFor(array $affiliateIds): array
{
    $vouchers = [];

    foreach ($affiliateIds as $affiliateId) {
        $code = 'ILS-'.strtoupper(substr(md5(uniqid((string) $affiliateId, true)), 0, 10));
        $path = App\Support\Upgrades\UpgradeIlsVoucher::DOCUMENT_DIRECTORY.'/'.$code.'.pdf';
        Illuminate\Support\Facades\Storage::disk(App\Support\Upgrades\UpgradeIlsVoucher::DOCUMENT_DISK)->put($path, '%PDF-1.4 prueba');

        $vouchers[(int) $affiliateId] = [
            'code' => $code,
            'date_init' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'document_path' => $path,
        ];
    }

    return $vouchers;
}
