<?php

declare(strict_types=1);

use App\Enums\ApiPartnerAbility;
use App\Http\Middleware\AuthenticateApiPartner;
use App\Support\ApiPartners\ApiPartnerToken;
use App\Support\Operations\MedicationInventoryStockStatus;

$root = dirname(__DIR__, 2);

describe('token del aliado', function (): void {
    it('genera un token con prefijo público y guarda solo su hash', function (): void {
        $generated = ApiPartnerToken::generate();

        expect($generated['token'])->toStartWith('icp_'.$generated['prefix'].'.')
            ->and(strlen($generated['token']))->toBe(4 + ApiPartnerToken::PREFIX_LENGTH + 1 + ApiPartnerToken::SECRET_LENGTH)
            ->and($generated['hash'])->toBe(hash('sha256', $generated['token']))
            ->and($generated['hash'])->not->toContain($generated['token'])
            ->and(ApiPartnerToken::prefixOf($generated['token']))->toBe($generated['prefix'])
            ->and(ApiPartnerToken::matches($generated['token'], $generated['hash']))->toBeTrue();
    });

    it('rechaza tokens alterados, vacíos o con otra forma sin ir a la base', function (?string $token): void {
        expect(ApiPartnerToken::prefixOf($token))->toBeNull();
    })->with([
        'nulo' => [null],
        'vacío' => [''],
        'otro esquema' => ['sk_abcdefghijkl.'.str_repeat('a', 48)],
        'secreto corto' => ['icp_abcdefghijkl.abc'],
        'prefijo con mayúsculas' => ['icp_ABCDEFGHIJKL.'.str_repeat('a', 48)],
        'inyección' => ["icp_abcdefghijkl.' OR 1=1 --"],
    ]);

    it('no acepta un token distinto aunque comparta prefijo, ni un hash vacío', function (): void {
        $generated = ApiPartnerToken::generate();
        $forged = 'icp_'.$generated['prefix'].'.'.str_repeat('x', ApiPartnerToken::SECRET_LENGTH);

        expect(ApiPartnerToken::matches($forged, $generated['hash']))->toBeFalse()
            ->and(ApiPartnerToken::matches($generated['token'], null))->toBeFalse()
            ->and(ApiPartnerToken::matches($generated['token'], ''))->toBeFalse();
    });

    it('en pantallas internas solo muestra el prefijo', function (): void {
        expect(ApiPartnerToken::displayPrefix('abcdefghijkl'))->toBe('icp_abcdefghijkl.••••')
            ->and(ApiPartnerToken::displayPrefix(null))->toBe('Sin token');
    });
});

describe('estado de inventario informado al aliado', function (): void {
    it('clasifica sin exponer cantidades', function (int $matches, array $rows, string $expected): void {
        expect(MedicationInventoryStockStatus::classify($matches, $rows))->toBe($expected);
    })->with([
        'sin coincidencias' => [0, [], MedicationInventoryStockStatus::MISSING],
        'producto sin almacenes activos' => [1, [], MedicationInventoryStockStatus::MISSING],
        'existencia en cero' => [1, [['existence' => 0, 'minimum' => 5]], MedicationInventoryStockStatus::MISSING],
        'bajo el mínimo' => [1, [['existence' => 2, 'minimum' => 5]], MedicationInventoryStockStatus::MINIMUM],
        'igual al mínimo' => [1, [['existence' => 5, 'minimum' => 5]], MedicationInventoryStockStatus::MINIMUM],
        'sobre el mínimo sumando almacenes' => [2, [['existence' => 4, 'minimum' => 2], ['existence' => 3, 'minimum' => 2]], MedicationInventoryStockStatus::AVAILABLE],
        'existencia negativa no suma' => [1, [['existence' => -3, 'minimum' => 0], ['existence' => 1, 'minimum' => 0]], MedicationInventoryStockStatus::AVAILABLE],
    ]);

    it('solo cuenta productos que contienen la palabra principal del medicamento', function (): void {
        expect(MedicationInventoryStockStatus::productMatchesPrincipalToken('ÁCIDO FÓLICO 5MG', 'ACIDO'))->toBeTrue()
            ->and(MedicationInventoryStockStatus::productMatchesPrincipalToken('DICLOFENAC POTASICO (50MG) 30 COMPRIMIDOS', 'MIGREN'))->toBeFalse()
            ->and(MedicationInventoryStockStatus::productMatchesPrincipalToken('OMEPRAZOL (40MG) 10 CAPSULAS', null))->toBeFalse();
    });

    it('expone solo los tres estados acordados', function (): void {
        expect(MedicationInventoryStockStatus::values())->toBe(['DISPONIBLE', 'EXISTENCIA_MINIMA', 'NO_EXISTE']);
    });
});

describe('bitácora de acceso', function (): void {
    it('acota los filtros guardados en cantidad y largo', function (): void {
        $logged = AuthenticateApiPartner::loggableQuery([
            'estatus' => ['PENDIENTE', 'EN GESTION'],
            'caso' => str_repeat('x', 500),
        ]);

        expect($logged['estatus'])->toBe('PENDIENTE,EN GESTION')
            ->and(mb_strlen($logged['caso']))->toBeLessThanOrEqual(123)
            ->and(AuthenticateApiPartner::loggableQuery([]))->toBeNull();
    });
});

it('el middleware aplica HTTPS, freno de fuerza bruta, permisos, límite por aliado y bitácora, sin restringir por IP', function () use ($root): void {
    $middleware = file_get_contents($root.'/app/Http/Middleware/AuthenticateApiPartner.php');

    expect($middleware)
        ->toContain("headers->set('Accept', 'application/json')")
        ->toContain('ApiPartner::tablesExist()')
        ->toContain('app()->isProduction() && ! $this->isSecure($request)')
        ->toContain('MAX_FAILED_ATTEMPTS_PER_MINUTE = 20')
        ->toContain('ApiPartnerToken::matches($token, $partner->token_hash)')
        ->not->toContain('allowed_ips')
        ->not->toContain('ip_no_permitida')
        ->toContain('$partner->hasAbility($ability)')
        ->toContain('rate_limit_per_minute')
        ->toContain('ClientLocation::ip($request)')
        ->toContain("'Cache-Control', 'no-store, private'")
        ->toContain('logOncePerMinute')
        ->toContain("'WWW-Authenticate', 'Bearer'")
        ->not->toContain('$token\'')
        ->not->toContain("'token' =>");
});

it('las rutas del API están fuera del grupo web y todas pasan por la autenticación del aliado', function () use ($root): void {
    $routes = file_get_contents($root.'/routes/partners-api.php');
    $bootstrap = file_get_contents($root.'/bootstrap/app.php');

    expect($bootstrap)
        ->toContain("require base_path('routes/partners-api.php')")
        ->toContain("'api.partner' => \\App\\Http\\Middleware\\AuthenticateApiPartner::class");

    expect($routes)
        ->toContain("Route::prefix('api/v1/aliados')")
        ->toContain("Route::get('medicamentos-no-cubiertos'")
        ->toContain("->whereNumber('medication')")
        ->toContain("'api.partner:'.ApiPartnerAbility::NonCoveredMedicationsRead->value")
        ->toContain("Route::any('{path}'")
        ->not->toContain("'web'")
        ->not->toContain('Route::post')
        ->and(substr_count($routes, "->middleware('api.partner"))->toBe(4);

    expect(ApiPartnerAbility::NonCoveredMedicationsRead->value)->toBe('medications.non_covered.read');
});

it('el listado expone solo medicamentos no cubiertos y los datos acordados del paciente', function () use ($root): void {
    $feed = file_get_contents($root.'/app/Support/ApiPartners/NonCoveredMedicationFeed.php');
    $coverage = file_get_contents($root.'/app/Support/Telemedicine/TelemedicineMedicationCoverage.php');

    expect($coverage)->toContain('public static function whereNotCovered(Builder $medications): Builder');

    expect($feed)
        ->toContain('TelemedicineMedicationCoverage::whereNotCovered($query)')
        ->toContain("whereNotNull('operation_coordination_service_id')")
        ->toContain("orWhereHas('telemedicineCase')")
        ->toContain('MedicationInventoryStockStatus::forMedications($input)')
        ->toContain("'nombre' =>")
        ->toContain("'cedula' =>")
        ->toContain("'telefono' =>")
        ->toContain("'estado' =>")
        ->toContain("'ciudad' =>")
        ->toContain('MAX_PER_PAGE = 100')
        ->not->toContain('symptoms_diagnosis')
        ->not->toContain('birth_date')
        ->not->toContain('quote_price')
        ->not->toContain("'address'");
});

it('la validación rechaza parámetros desconocidos y limita la página', function () use ($root): void {
    $request = file_get_contents($root.'/app/Http/Requests/ApiPartners/NonCoveredMedicationIndexRequest.php');

    expect($request)
        ->toContain("'parametros_desconocidos' => ['array', 'max:0']")
        ->toContain('Rule::in(NonCoveredMedicationFeed::STATUSES)')
        ->toContain("'max:'.NonCoveredMedicationFeed::MAX_PER_PAGE")
        ->toContain("'date_format:Y-m-d'");
});

it('la administración de aliados es solo de SUPERADMIN y no permite borrar', function () use ($root): void {
    $resource = file_get_contents($root.'/app/Filament/Operations/Resources/ApiPartners/ApiPartnerResource.php');
    $registry = file_get_contents($root.'/app/Support/Filament/DepartmentNavigationPermissionRegistry.php');
    $actions = file_get_contents($root.'/app/Filament/Operations/Resources/ApiPartners/Actions/ApiPartnerTokenActions.php');
    $model = file_get_contents($root.'/app/Models/ApiPartner.php');

    expect($registry)->toContain('\App\Filament\Operations\Resources\ApiPartners\ApiPartnerResource::class');

    expect($resource)
        ->toContain('AuthorizesDepartmentNavigation')
        ->toContain('ApiPartner::tablesExist() && static::departmentCanAccess()')
        ->toContain('public static function canDelete($record): bool');

    expect(file_get_contents($root.'/app/Filament/Operations/Resources/ApiPartners/Schemas/ApiPartnerForm.php'))
        ->not->toContain('allowed_ips')
        ->not->toContain('IPs permitidas');

    expect($actions)
        ->toContain("->authorize(fn (): bool => ApiPartnerResource::canAccess())")
        ->toContain('->requiresConfirmation()')
        ->toContain('AUDIT_API_PARTNER_TOKEN_REVOKED')
        ->toContain('No se volverá a mostrar');

    expect($model)
        ->toContain("protected \$hidden = [\n        'token_hash',")
        ->not->toContain("'allowed_ips',\n")
        ->not->toContain("'token_hash',\n        'abilities'");
});

it('la migración es aditiva e idempotente', function () use ($root): void {
    $migration = file_get_contents($root.'/database/migrations/2026_11_20_150000_create_api_partners_tables.php');

    expect($migration)
        ->toContain("if (! Schema::hasTable('api_partners'))")
        ->toContain("if (! Schema::hasTable('api_partner_access_logs'))")
        ->toContain("\$table->string('token_prefix', 32)->nullable()->unique()")
        ->toContain("\$table->string('token_hash', 64)->nullable()")
        ->toContain("\$table->index(['api_partner_id', 'created_at'])");
});

it('el nombre del proveedor vinculado se muestra en una línea y con RIF', function (): void {
    expect(\App\Support\ApiPartners\ApiPartnerSupplierOptions::format("FARMACIA X\n\n(RELACIONADA)", 'J-123'))->toBe('FARMACIA X (RELACIONADA) · J-123')
        ->and(\App\Support\ApiPartners\ApiPartnerSupplierOptions::format('FARMADOC', null))->toBe('FARMADOC')
        ->and(\App\Support\ApiPartners\ApiPartnerSupplierOptions::search('f'))->toBe([]);
});

it('el aliado se puede vincular a un proveedor jurídico sin que eso cambie su acceso', function () use ($root): void {
    $migration = file_get_contents($root.'/database/migrations/2026_11_20_170000_add_supplier_id_to_api_partners_table.php');
    $model = file_get_contents($root.'/app/Models/ApiPartner.php');
    $form = file_get_contents($root.'/app/Filament/Operations/Resources/ApiPartners/Schemas/ApiPartnerForm.php');
    $options = file_get_contents($root.'/app/Support/ApiPartners/ApiPartnerSupplierOptions.php');
    $middleware = file_get_contents($root.'/app/Http/Middleware/AuthenticateApiPartner.php');

    expect($migration)
        ->toContain("Schema::hasColumn('api_partners', 'supplier_id')")
        ->toContain("\$table->unsignedBigInteger('supplier_id')->nullable()")
        ->toContain("\$table->index('supplier_id')");

    expect($model)
        ->toContain("'supplier_id',")
        ->toContain('return $this->belongsTo(Supplier::class)->withTrashed();')
        ->toContain('public static function supplierColumnExists(): bool');

    expect($form)
        ->toContain("Select::make('supplier_id')")
        ->toContain('->getSearchResultsUsing(fn (string $search): array => ApiPartnerSupplierOptions::search($search))')
        ->toContain("Rule::exists('suppliers', 'id')->whereNull('deleted_at')")
        ->toContain('->visible(fn (): bool => ApiPartner::supplierColumnExists())');

    expect($options)
        ->toContain('->limit(self::SEARCH_LIMIT)')
        ->not->toContain('->get()');

    expect($middleware)->not->toContain('supplier');
});

it('la documentación para aliados es pública, sin datos sensibles y con los valores reales del API', function () use ($root): void {
    $routes = file_get_contents($root.'/routes/partners-api.php');
    $view = file_get_contents($root.'/resources/views/api-partners/documentation.blade.php');
    $model = file_get_contents($root.'/app/Models/ApiPartner.php');
    $viewPage = file_get_contents($root.'/app/Filament/Operations/Resources/ApiPartners/Pages/ViewApiPartner.php');

    expect($routes)
        ->toContain("Route::view('aliados/api/documentacion', 'api-partners.documentation')")
        ->toContain("->name('api.partners.documentation')");

    // La ruta de la documentación no lleva el middleware del API: es pública.
    $start = strpos($routes, "Route::view('aliados/api/documentacion'");
    $documentationRoute = substr($routes, $start, strpos($routes, ';', $start) - $start);
    expect($documentationRoute)->not->toContain('->middleware(');

    expect($view)
        ->toContain('<meta name="robots" content="noindex, nofollow">')
        ->toContain("url('/api/v1/aliados')")
        ->toContain('NonCoveredMedicationFeed::STATUSES')
        ->toContain('NonCoveredMedicationFeed::MAX_PER_PAGE')
        ->toContain('AuthenticateApiPartner::MAX_FAILED_ATTEMPTS_PER_MINUTE')
        ->toContain('MedicationInventoryStockStatus::values()')
        ->toContain('prefers-color-scheme: dark')
        ->not->toContain('@csrf')
        ->not->toContain('token_hash')
        ->not->toContain('ip_no_permitida');

    expect($model)
        ->toContain("public const DOCUMENTATION_PATH = '/aliados/api/documentacion';")
        ->toContain('return url(self::DOCUMENTATION_PATH);');

    expect($viewPage)
        ->toContain("Action::make('documentation')")
        ->toContain('ApiPartner::documentationUrl(), shouldOpenInNewTab: true')
        ->not->toContain("route('api.partners.documentation')");
});

it('acepta el token aunque venga con errores típicos de copiado, sin aflojar la verificación', function (string $wrap): void {
    $generated = ApiPartnerToken::generate();
    $presented = ApiPartnerToken::normalize(sprintf($wrap, $generated['token']));

    expect(ApiPartnerToken::prefixOf($presented))->toBe($generated['prefix'])
        ->and(ApiPartnerToken::matches($presented, $generated['hash']))->toBeTrue();
})->with([
    'espacios y salto de línea' => ["  %s \n"],
    'comillas' => ['"%s"'],
    'entre < >' => ['<%s>'],
    'Bearer repetido' => ['Bearer %s'],
    'Bearer repetido y < >' => ['Bearer <%s>'],
]);

it('explica en la bitácora por qué un token tiene mala forma, sin revelar el secreto', function (): void {
    $token = ApiPartnerToken::generate()['token'];

    expect(ApiPartnerToken::formatProblem(substr($token, 0, 40)))->toBe('largo 40 (se esperaban 65)')
        ->and(ApiPartnerToken::formatProblem(substr($token, 4)))->toContain('no empieza con icp_')
        ->and(ApiPartnerToken::formatProblem(substr($token, 0, 40)))->not->toContain(substr($token, 17, 10))
        ->and(ApiPartnerToken::normalize('   '))->toBeNull();
});

it('distingue en la bitácora token mal copiado, desconocido y que no coincide, pero responde igual al cliente', function () use ($root): void {
    $middleware = file_get_contents($root.'/app/Http/Middleware/AuthenticateApiPartner.php');

    expect($middleware)
        ->toContain('ApiPartnerToken::normalize($request->bearerToken())')
        ->toContain('ApiPartnerAccessLog::OUTCOME_MALFORMED_TOKEN')
        ->toContain('ApiPartnerAccessLog::OUTCOME_UNKNOWN_TOKEN')
        ->toContain('ApiPartnerAccessLog::OUTCOME_TOKEN_MISMATCH')
        ->toContain("'_diagnostico'")
        ->and(substr_count($middleware, "'El token de acceso no es válido.'"))->toBe(1);
});

it('el API informa los códigos de barras de los productos del inventario que coinciden', function () use ($root): void {
    $feed = file_get_contents($root.'/app/Support/ApiPartners/NonCoveredMedicationFeed.php');
    $status = file_get_contents($root.'/app/Support/Operations/MedicationInventoryStockStatus.php');
    $docs = file_get_contents($root.'/resources/views/api-partners/documentation.blade.php');

    expect($feed)
        ->toContain('MedicationInventoryStockStatus::detailsForMedications($input)')
        ->toContain("'codigos_barras' => array_values(\$barcodes)");

    expect($status)
        ->toContain("'existence', 'min_stock', 'barcode']")
        ->toContain('public static function detailsForMedications(array $medications): array');

    expect($docs)->toContain('codigos_barras')->toContain('no el producto exacto a despachar');

    expect(MedicationInventoryStockStatus::uniqueBarcodes([' 7591 585 ', '7591585', 7591585, null, '', ['x'], '0730170649487']))
        ->toBe(['0730170649487', '7591585'])
        ->and(MedicationInventoryStockStatus::uniqueBarcodes([]))->toBe([]);
});
