<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Filament\Operations\Resources\TelemedicinePatients\Pages\RegisterRetailServices;
use App\Models\RetailMainService;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\RetailMainServiceCatalog;
use App\Support\Operations\RetailServiceRegistration;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    RetailMainServiceCatalog::flush();
});

afterEach(function (): void {
    DB::rollBack();
    RetailMainServiceCatalog::flush();
});

it('ofrece los servicios de siempre más equipos médicos y sesión de fisioterapia', function (): void {
    $active = RetailMainServiceCatalog::active();

    expect($active)->toContain('TELEMEDICINA', 'AMD (ASISTENCIA MEDICA DOMICILIARIA)', 'TRASLADO EN AMBULANCIA', 'EQUIPOS MEDICOS', 'SESION DE FISIOTERAPIA');
    expect(RetailMainServiceCatalog::label('EQUIPOS MEDICOS'))->toBe('Equipos médicos');
    expect(RetailMainServiceCatalog::label('SESION DE FISIOTERAPIA'))->toBe('Sesión de fisioterapia');
    expect(RetailServiceRegistration::mainServices())->toBe($active);
    expect(RegisterTpaRetailServicesAction::standaloneSpecificServices())->toBe($active);
});

it('agrega un servicio con su clave sin acentos y no lo repite', function (): void {
    $result = RetailMainServiceCatalog::add('  Oxigenoterapia   en casa ', null);
    $again = RetailMainServiceCatalog::add('OXIGENOTERAPIA EN CASA', null);

    expect($result['ok'])->toBeTrue();
    expect(RetailMainServiceCatalog::active())->toContain('OXIGENOTERAPIA EN CASA');
    expect(RetailMainServiceCatalog::label('OXIGENOTERAPIA EN CASA'))->toBe('Oxigenoterapia en casa');
    expect($again['ok'])->toBeFalse();
    expect(RetailMainService::query()->where('name', 'OXIGENOTERAPIA EN CASA')->count())->toBe(1);
});

it('rechaza nombres vacíos, muy largos o con signos raros', function (): void {
    expect(RetailMainServiceCatalog::add('ab', null)['ok'])->toBeFalse();
    expect(RetailMainServiceCatalog::add(str_repeat('a', 61), null)['ok'])->toBeFalse();
    expect(RetailMainServiceCatalog::add('<script>x</script>', null)['ok'])->toBeFalse();
});

it('quitar desactiva: deja de ofrecerse pero las coordinaciones viejas lo siguen reconociendo', function (): void {
    $id = (int) RetailMainService::query()->where('name', 'EQUIPOS MEDICOS')->value('id');

    $result = RetailMainServiceCatalog::remove($id, null);

    expect($result['ok'])->toBeTrue();
    expect(RetailMainServiceCatalog::active())->not->toContain('EQUIPOS MEDICOS');
    expect(RegisterTpaRetailServicesAction::isStandaloneSpecificService('EQUIPOS MEDICOS'))->toBeTrue();
    expect(RetailMainService::query()->whereKey($id)->exists())->toBeTrue();
    expect(RetailMainServiceCatalog::remove($id, null)['ok'])->toBeFalse();
});

it('volver a agregar un servicio quitado lo reactiva sin duplicarlo', function (): void {
    $id = (int) RetailMainService::query()->where('name', 'SESION DE FISIOTERAPIA')->value('id');
    RetailMainServiceCatalog::remove($id, null);

    $result = RetailMainServiceCatalog::add('Sesión de fisioterapia', null);

    expect($result['ok'])->toBeTrue();
    expect($result['reactivated'])->toBeTrue();
    expect(RetailMainServiceCatalog::active())->toContain('SESION DE FISIOTERAPIA');
    expect(RetailMainService::query()->where('name', 'SESION DE FISIOTERAPIA')->count())->toBe(1);
});

it('telemedicina y AMD no se pueden quitar ni aparecen como removibles', function (): void {
    $telemedicine = (int) RetailMainService::query()->where('name', 'TELEMEDICINA')->value('id');
    $amd = (int) RetailMainService::query()->where('name', 'AMD (ASISTENCIA MEDICA DOMICILIARIA)')->value('id');

    expect(RetailMainServiceCatalog::remove($telemedicine, null)['ok'])->toBeFalse();
    expect(array_keys(RetailMainServiceCatalog::removable()))->not->toContain($telemedicine);
    expect(array_keys(RetailMainServiceCatalog::removable()))->not->toContain($amd);
    expect(RetailMainServiceCatalog::active())->toContain('TELEMEDICINA');
});

it('el registro rechaza un servicio quitado y acepta uno agregado', function (): void {
    RetailMainServiceCatalog::add('Oxigenoterapia en casa', null);
    RetailMainServiceCatalog::remove((int) RetailMainService::query()->where('name', 'EQUIPOS MEDICOS')->value('id'), null);

    expect(fn () => RetailServiceRegistration::normalize(['services' => ['EQUIPOS MEDICOS']]))->toThrow(App\Support\Operations\RetailServiceRegistrationException::class, 'no está permitido');
    $message = '';

    try {
        RetailServiceRegistration::normalize(['services' => ['OXIGENOTERAPIA EN CASA']]);
    } catch (App\Support\Operations\RetailServiceRegistrationException $exception) {
        $message = $exception->getMessage();
    }

    expect(str_contains($message, 'no está permitido'))->toBeFalse();
});

it('la gestión de la sección depende de un permiso propio de Operaciones', function (): void {
    $definition = BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::MANAGE_RETAIL_MAIN_SERVICES] ?? null;
    $page = file_get_contents((new ReflectionClass(RegisterRetailServices::class))->getFileName());

    expect($definition)->not->toBeNull();
    expect($definition['modules'])->toBe(['OPERACIONES']);
    expect($page)->toContain('->afterHeader([$this->addMainServiceAction(), $this->removeMainServiceAction()])');
    expect(substr_count($page, 'abort_unless(self::canManageMainServices(), 403);'))->toBe(2);
    expect(substr_count($page, '->visible(fn (): bool => self::canManageMainServices()'))->toBe(2);
});
