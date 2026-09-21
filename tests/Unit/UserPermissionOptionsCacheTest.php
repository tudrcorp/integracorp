<?php

declare(strict_types=1);

use App\Support\Filament\CommercialNetworkPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Esquema propio en sqlite: estos tests miden consultas, así que no pueden
 * depender de los datos —ni de la base— de nadie.
 */
beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'cache.default' => 'array',
        'session.driver' => 'array',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    $schema = Schema::connection('sqlite');
    $schema->dropIfExists('permissions');
    $schema->create('permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('slug');
        $table->string('module');
        $table->string('name');
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });

    foreach ([
        ['slug' => 'afiliaciones-individuales', 'name' => 'Afiliaciones individuales'],
        ['slug' => 'cotizaciones-individuales', 'name' => 'Cotizaciones individuales'],
        ['slug' => 'agentes-de-corretaje', 'name' => 'Agentes de corretaje'],
    ] as $permiso) {
        DB::table('permissions')->insert($permiso + [
            'module' => 'NEGOCIOS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    UserFormPermissionOptions::flush();
    CommercialNetworkPermissionRegistry::flush();
});

afterEach(function (): void {
    UserFormPermissionOptions::flush();
    CommercialNetworkPermissionRegistry::flush();
});

it('consulta los permisos de un módulo una sola vez por petición', function (): void {
    $consultas = 0;
    DB::listen(function () use (&$consultas): void {
        $consultas++;
    });

    UserFormPermissionOptions::forModule('NEGOCIOS');
    UserFormPermissionOptions::forModule('NEGOCIOS');
    UserFormPermissionOptions::groupedOptionsForModule('NEGOCIOS');
    UserFormPermissionOptions::groupedOptionsForModule('NEGOCIOS');
    UserFormPermissionOptions::groupedPermissionsForModule('NEGOCIOS');
    UserFormPermissionOptions::countForModule('NEGOCIOS');

    /** Abrir la ficha de un usuario lanzaba 24 veces esta misma consulta. */
    expect($consultas)->toBe(1);
});

it('vuelve a consultar después de olvidar lo memoizado', function (): void {
    UserFormPermissionOptions::forModule('NEGOCIOS');

    $consultas = 0;
    DB::listen(function () use (&$consultas): void {
        $consultas++;
    });

    UserFormPermissionOptions::flush();
    UserFormPermissionOptions::forModule('NEGOCIOS');

    expect($consultas)->toBe(1);
});

it('memoiza por módulo, no para todos a la vez', function (): void {
    UserFormPermissionOptions::forModule('NEGOCIOS');

    $consultas = 0;
    DB::listen(function () use (&$consultas): void {
        $consultas++;
    });

    UserFormPermissionOptions::forModule('OPERACIONES');

    expect($consultas)->toBe(1);
});

it('no repite la consulta de los permisos de la red comercial', function (): void {
    CommercialNetworkPermissionRegistry::options();

    $consultas = 0;
    DB::listen(function () use (&$consultas): void {
        $consultas++;
    });

    CommercialNetworkPermissionRegistry::options();
    CommercialNetworkPermissionRegistry::optionDescriptions();
    CommercialNetworkPermissionRegistry::permissionIds();

    /** Antes eran veinte consultas para dos permisos. */
    expect($consultas)->toBe(0);
});
