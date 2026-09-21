<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Traits que recrean el esquema (`migrate:fresh`) y por tanto destruyen la
     * base contra la que corren.
     *
     * @var list<class-string>
     */
    private const DESTRUCTIVE_TRAITS = [
        RefreshDatabase::class,
        LazilyRefreshDatabase::class,
        DatabaseMigrations::class,
        DatabaseTruncation::class,
    ];

    /**
     * Obliga a los tests que recrean el esquema a correr en sqlite en memoria.
     *
     * Un test con `RefreshDatabase` ejecuta `migrate:fresh` desde `setUpTraits()`,
     * así que esto tiene que resolverse antes: aquí, al crear la aplicación.
     * Declarar la conexión en `phpunit.xml` no alcanza, porque una config cacheada
     * (`bootstrap/cache/config.php`) ignora `env()` y deja la conexión apuntando a
     * la base de desarrollo.
     *
     * Los tests que solo leen conservan la conexión configurada, para no alterar
     * el comportamiento del resto de la suite.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        if (! $this->usesDestructiveDatabaseTrait()) {
            return;
        }

        $this->forceIsolatedTestEnvironment();
        $this->assertTestDatabaseIsIsolated();
    }

    protected function usesDestructiveDatabaseTrait(): bool
    {
        return array_intersect(
            array_values(class_uses_recursive(static::class)),
            self::DESTRUCTIVE_TRAITS,
        ) !== [];
    }

    protected function forceIsolatedTestEnvironment(): void
    {
        config()->set('session.driver', 'array');
        config()->set('cache.default', 'array');
        config()->set('queue.default', 'sync');

        config()->set('database.connections.sqlite', array_merge(
            (array) config('database.connections.sqlite', []),
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ));

        config()->set('database.default', 'sqlite');

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
    }

    protected function assertTestDatabaseIsIsolated(): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if ($driver === 'sqlite' && ($database === ':memory:' || $database === '')) {
            return;
        }

        throw new RuntimeException(
            'Este test recrea el esquema y no puede correr contra la conexión "'.$connection.'" ('.$driver.': '.$database.'). '
            .'Solo se permite sqlite en memoria: ejecutarlo destruiría la base de datos real.'
        );
    }
}
