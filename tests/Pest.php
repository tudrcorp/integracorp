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
