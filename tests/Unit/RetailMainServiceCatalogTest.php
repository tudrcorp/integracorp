<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Filament\Operations\Resources\TelemedicinePatients\Pages\RegisterRetailServices;
use App\Models\RetailMainService;
use App\Models\RetailMainServiceLog;
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
    $result = RetailMainServiceCatalog::add('  Oxigenoterapia   en casa ', 'Lo pidió la coordinación médica', null);
    $again = RetailMainServiceCatalog::add('OXIGENOTERAPIA EN CASA', 'Lo pidió la coordinación médica', null);

    expect($result['ok'])->toBeTrue();
    expect(RetailMainServiceCatalog::active())->toContain('OXIGENOTERAPIA EN CASA');
    expect(RetailMainServiceCatalog::label('OXIGENOTERAPIA EN CASA'))->toBe('Oxigenoterapia en casa');
    expect($again['ok'])->toBeFalse();
    expect(RetailMainService::query()->where('name', 'OXIGENOTERAPIA EN CASA')->count())->toBe(1);
});

it('rechaza nombres vacíos, muy largos o con signos raros', function (): void {
    expect(RetailMainServiceCatalog::add('ab', 'Lo pidió la coordinación médica', null)['ok'])->toBeFalse();
    expect(RetailMainServiceCatalog::add(str_repeat('a', 61), 'Lo pidió la coordinación médica', null)['ok'])->toBeFalse();
    expect(RetailMainServiceCatalog::add('<script>x</script>', 'Lo pidió la coordinación médica', null)['ok'])->toBeFalse();
});

it('quitar desactiva: deja de ofrecerse pero las coordinaciones viejas lo siguen reconociendo', function (): void {
    $id = (int) RetailMainService::query()->where('name', 'EQUIPOS MEDICOS')->value('id');

    $result = RetailMainServiceCatalog::remove($id, 'Lo pidió la coordinación médica', null);

    expect($result['ok'])->toBeTrue();
    expect(RetailMainServiceCatalog::active())->not->toContain('EQUIPOS MEDICOS');
    expect(RegisterTpaRetailServicesAction::isStandaloneSpecificService('EQUIPOS MEDICOS'))->toBeTrue();
    expect(RetailMainService::query()->whereKey($id)->exists())->toBeTrue();
    expect(RetailMainServiceCatalog::remove($id, 'Lo pidió la coordinación médica', null)['ok'])->toBeFalse();
});

it('volver a agregar un servicio quitado lo reactiva sin duplicarlo', function (): void {
    $id = (int) RetailMainService::query()->where('name', 'SESION DE FISIOTERAPIA')->value('id');
    RetailMainServiceCatalog::remove($id, 'Lo pidió la coordinación médica', null);

    $result = RetailMainServiceCatalog::add('Sesión de fisioterapia', 'Lo pidió la coordinación médica', null);

    expect($result['ok'])->toBeTrue();
    expect($result['reactivated'])->toBeTrue();
    expect(RetailMainServiceCatalog::active())->toContain('SESION DE FISIOTERAPIA');
    expect(RetailMainService::query()->where('name', 'SESION DE FISIOTERAPIA')->count())->toBe(1);
});

it('telemedicina y AMD no se pueden quitar ni aparecen como removibles', function (): void {
    $telemedicine = (int) RetailMainService::query()->where('name', 'TELEMEDICINA')->value('id');
    $amd = (int) RetailMainService::query()->where('name', 'AMD (ASISTENCIA MEDICA DOMICILIARIA)')->value('id');

    expect(RetailMainServiceCatalog::remove($telemedicine, 'Lo pidió la coordinación médica', null)['ok'])->toBeFalse();
    expect(array_keys(RetailMainServiceCatalog::removable()))->not->toContain($telemedicine);
    expect(array_keys(RetailMainServiceCatalog::removable()))->not->toContain($amd);
    expect(RetailMainServiceCatalog::active())->toContain('TELEMEDICINA');
});

it('el registro rechaza un servicio quitado y acepta uno agregado', function (): void {
    RetailMainServiceCatalog::add('Oxigenoterapia en casa', 'Lo pidió la coordinación médica', null);
    RetailMainServiceCatalog::remove((int) RetailMainService::query()->where('name', 'EQUIPOS MEDICOS')->value('id'), 'Lo pidió la coordinación médica', null);

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
    expect($page)->toContain('->afterHeader([$this->addMainServiceAction(), $this->removeMainServiceAction(), $this->mainServicesHistoryAction()])');
    expect(substr_count($page, '->visible(fn (): bool => self::canManageMainServices()'))->toBe(3);
});

it('sin motivo, o con un motivo de relleno, no agrega ni quita nada', function (): void {
    $id = (int) RetailMainService::query()->where('name', 'EQUIPOS MEDICOS')->value('id');
    $logs = RetailMainServiceLog::query()->count();

    expect(RetailMainServiceCatalog::add('Oxigenoterapia en casa', '', null)['ok'])->toBeFalse();
    expect(RetailMainServiceCatalog::add('Oxigenoterapia en casa', 'ok', null)['ok'])->toBeFalse();
    expect(RetailMainServiceCatalog::remove($id, '..........', null)['ok'])->toBeFalse();
    expect(RetailMainServiceCatalog::remove($id, '1111111111', null)['ok'])->toBeFalse();
    expect(RetailMainServiceCatalog::active())->not->toContain('OXIGENOTERAPIA EN CASA');
    expect(RetailMainServiceCatalog::active())->toContain('EQUIPOS MEDICOS');
    expect(RetailMainServiceLog::query()->count())->toBe($logs);
});

it('cada cambio deja en la bitácora la acción, el servicio, el motivo y quién lo hizo', function (): void {
    $user = (new App\Models\User)->forceFill(['id' => 2, 'name' => 'Analista Prueba']);

    RetailMainServiceCatalog::add('Oxigenoterapia en casa', 'Nuevo convenio con el proveedor de oxígeno', $user);
    $id = (int) RetailMainService::query()->where('name', 'OXIGENOTERAPIA EN CASA')->value('id');
    RetailMainServiceCatalog::remove($id, 'El convenio se suspendió este mes', $user);
    RetailMainServiceCatalog::add('Oxigenoterapia en casa', 'Se renovó el convenio con el proveedor', $user);

    $logs = RetailMainServiceLog::query()->where('retail_main_service_id', $id)->orderBy('id')->get();

    expect($logs->pluck('action')->all())->toBe(['agregado', 'quitado', 'reactivado']);
    expect($logs[1]->note)->toBe('El convenio se suspendió este mes');
    expect($logs[1]->user_id)->toBe(2);
    expect($logs[1]->user_name)->toBe('Analista Prueba');
    expect($logs[1]->service_label)->toBe('Oxigenoterapia en casa');

    $history = RetailMainServiceCatalog::history();

    expect($history[0]['action'])->toBe('Reactivado');
    expect($history[0]['note'])->toBe('Se renovó el convenio con el proveedor');
    expect($history[0]['user'])->toBe('Analista Prueba');
});

it('si el servicio no cambia, no queda registro en la bitácora', function (): void {
    $telemedicine = (int) RetailMainService::query()->where('name', 'TELEMEDICINA')->value('id');
    $logs = RetailMainServiceLog::query()->count();

    RetailMainServiceCatalog::remove($telemedicine, 'Intento de quitar telemedicina', null);
    RetailMainServiceCatalog::add('Equipos médicos', 'Intento de agregarlo dos veces', null);

    expect(RetailMainServiceLog::query()->count())->toBe($logs);
});

it('la bitácora no se puede editar ni borrar', function (): void {
    RetailMainServiceCatalog::add('Oxigenoterapia en casa', 'Nuevo convenio con el proveedor de oxígeno', null);
    $log = RetailMainServiceLog::query()->latest('id')->first();

    expect(fn () => $log->forceFill(['note' => 'otra cosa distinta'])->save())->toThrow(LogicException::class, 'no se edita');
    expect(fn () => $log->delete())->toThrow(LogicException::class, 'no se borra');
});

it('los diálogos piden el motivo y el historial solo lo ve quien tiene el permiso', function (): void {
    $page = file_get_contents((new ReflectionClass(RegisterRetailServices::class))->getFileName());

    expect(substr_count($page, "Textarea::make('note')"))->toBe(2);
    expect($page)->toContain("Action::make('mainServicesHistory')", '$this->mainServicesHistoryAction()', "->modalSubmitAction(false)");
    expect(substr_count($page, 'abort_unless(self::canManageMainServices(), 403);'))->toBe(3);
});
