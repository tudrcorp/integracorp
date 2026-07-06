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
