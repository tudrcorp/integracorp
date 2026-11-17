<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Plans\Pages\ListPlans;
use App\Models\Affiliation;
use App\Models\Plan;
use App\Models\User;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\Affiliations\Certificates\CarnetDigitalContent;
use App\Support\Affiliations\Certificates\PlanCarnetBrands;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Marca del carnet por plan (Renacer en el plan funerario). Escribe dentro de
 * una transacción revertida y con los discos falsos; usuarios con make().
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
    Storage::fake('local');
    Mail::fake();
    Queue::fake();
    PlanCarnetBrands::flush();
});

afterEach(function (): void {
    DB::rollBack();
    PlanCarnetBrands::flush();
});

function pcbActor(): User
{
    return User::factory()->make(['id' => 987980, 'name' => 'Analista Carnets Pest']);
}

function pcbLogo(string $name = 'renacer.png', int $width = 300, int $height = 300): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 47, 96, 52));
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    $path = PlanCarnetBrands::DIRECTORY.'/'.$name;
    Storage::disk('public')->put($path, $png);

    return $path;
}

function pcbAffiliation(): Affiliation
{
    return Affiliation::query()
        ->whereIn('status', ['ACTIVA', 'PRE-APROBADA'])
        ->whereNotNull('plan_id')
        ->whereHas('affiliates', fn ($q) => $q->where('status', 'ACTIVO'))
        ->latest('id')
        ->firstOrFail();
}

it('un plan sin marca sale con el carnet de siempre', function (): void {
    $plan = Plan::query()->findOrFail(34);

    expect(PlanCarnetBrands::forDocument([34]))->toBe([])
        ->and(PlanCarnetBrands::forWeb(34))->toBeNull()
        ->and(PlanCarnetBrands::hasBrand($plan))->toBeFalse();
});

it('no guarda una marca sin logo, con color inválido o sin nombre del pie', function (array $data, string $field): void {
    $data += ['logo_path' => pcbLogo(), 'color' => '#2F6034', 'company_label' => 'RENACER ECOFUNERAL'];

    try {
        PlanCarnetBrands::save(Plan::query()->findOrFail(34), $data, pcbActor());
        $this->fail('Debió rechazar la marca.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    expect(DB::table('plan_carnet_brands')->where('plan_id', 34)->exists())->toBeFalse();
})->with([
    'sin logo' => [['logo_path' => null], 'logo_path'],
    'logo fuera del directorio' => [['logo_path' => 'otra-carpeta/logo.png'], 'logo_path'],
    'color inválido' => [['color' => 'verde'], 'color'],
    'sin nombre' => [['company_label' => '   '], 'company_label'],
]);

it('guarda la marca y la plantilla recibe logo acomodado, verde y nombre del pie', function (): void {
    $plan = Plan::query()->findOrFail(34);
    PlanCarnetBrands::save($plan, ['logo_path' => pcbLogo(), 'color' => '2f6034', 'company_label' => '  RENACER   ECOFUNERAL '], pcbActor());

    $brand = PlanCarnetBrands::forDocument([34, null, 34])[34];
    $logo = getimagesize($brand['logo']);

    expect(PlanCarnetBrands::hasBrand($plan))->toBeTrue()
        ->and($brand['th']['pillBg'])->toBe('#2F6034')
        ->and($brand['th']['pillInk'])->toBe('#FFFFFF')
        ->and($brand['th']['sub'])->toBe('#2F6034')
        ->and($brand['th']['bg'])->toBe('#EFEFEF')
        ->and($brand['accent'])->toBe('#2F6034')
        ->and($brand['label'])->toBe('RENACER ECOFUNERAL')
        ->and([$logo[0], $logo[1]])->toBe([PlanCarnetBrands::LOGO_BOX_WIDTH * 4, PlanCarnetBrands::LOGO_BOX_HEIGHT * 4]);

    @unlink($brand['logo']);
});

it('el frente y el reverso del carnet llevan la marca del plan y los demás planes quedan igual', function (): void {
    PlanCarnetBrands::save(Plan::query()->findOrFail(34), ['logo_path' => pcbLogo(), 'color' => '#2F6034', 'company_label' => 'RENACER ECOFUNERAL'], pcbActor());

    $data = [
        'th' => ['bg' => '#EFEFEF', 'ink' => '#12293A', 'sub' => '#35657D', 'title' => '#12293A', 'pillBg' => '#26B4E8', 'pillInk' => '#0B3D52'],
        'images' => ['logoTheme' => '/ruta/logo-tdec.png', 'carnetWave' => '/ruta/ola.svg', 'qr' => '/ruta/qr.png'],
        'planId' => 34, 'planNombre' => 'FUNERARIO 5K', 'carnetCobertura' => 'Beneficios incluidos', 'carnetPreex' => false,
        'desde' => '12/11/2026', 'hasta' => '12/11/2027', 'codigo' => 'TDEC-IND-000457',
        'carnetBrands' => PlanCarnetBrands::forDocument([34]),
    ];
    $person = ['nombre' => 'Marlene Escalona', 'docFmt' => 'C.I. V-8.755.301'];

    $branded = view('documents.affiliation-certificate.partials.carnet-front', ['data' => $data, 'b' => $person])->render()
        .view('documents.affiliation-certificate.partials.carnet-back', ['data' => $data, 'b' => $person])->render();

    $standardData = array_merge($data, ['planId' => 26]);
    $standard = view('documents.affiliation-certificate.partials.carnet-front', ['data' => $standardData, 'b' => $person])->render()
        .view('documents.affiliation-certificate.partials.carnet-back', ['data' => $standardData, 'b' => $person])->render();

    expect($branded)->toContain('RENACER ECOFUNERAL', 'background:#2F6034', 'color:#2F6034;">Humanizamos la salud', 'height:44px', 'height:55px')
        ->not->toContain('/ruta/logo-tdec.png', 'Tu Doctor en Casa, C.A. · RIF J-50358368-1', '#26B4E8', '#2D89CA')
        ->and($standard)->toContain('/ruta/logo-tdec.png', 'Tu Doctor en Casa, C.A. · RIF J-50358368-1', 'background:#26B4E8', 'color:#2D89CA;">Humanizamos la salud', 'height:26px', 'height:70px')
        ->not->toContain('RENACER ECOFUNERAL');

    @unlink($data['carnetBrands'][34]['logo']);
});

it('el certificado emitido para un plan con marca la usa en sus carnets y el PDF se dibuja', function (): void {
    $affiliation = pcbAffiliation();
    $plan = Plan::query()->findOrFail($affiliation->plan_id);
    PlanCarnetBrands::save($plan, ['logo_path' => pcbLogo(), 'color' => '#2F6034', 'company_label' => 'RENACER ECOFUNERAL'], pcbActor());

    $service = app(AffiliationCertificateGeneratorService::class);
    $carnetIds = $affiliation->affiliates()->where('status', 'ACTIVO')->limit(1)->pluck('id')->all();
    $issue = $service->issueIndividual($affiliation, $carnetIds, User::query()->firstOrFail());
    $data = $service->documentData($issue);

    expect($data['planId'])->toBe((int) $plan->getKey())
        ->and($data['carnetBrands'])->toHaveKey((int) $plan->getKey())
        ->and(substr($service->renderPdf($issue->fresh(), false), 0, 4))->toBe('%PDF');

    @unlink($data['carnetBrands'][(int) $plan->getKey()]['logo']);
});

it('quitar la marca devuelve el plan al carnet estándar', function (): void {
    $plan = Plan::query()->findOrFail(34);
    PlanCarnetBrands::save($plan, ['logo_path' => pcbLogo(), 'color' => '#2F6034', 'company_label' => 'RENACER ECOFUNERAL'], pcbActor());

    PlanCarnetBrands::remove($plan);

    expect(PlanCarnetBrands::hasBrand($plan))->toBeFalse()
        ->and(PlanCarnetBrands::forWeb(34))->toBeNull();
});

it('el carnet digital cambia los azules por el color de la marca, su logo y su nombre en el pie', function (): void {
    PlanCarnetBrands::save(Plan::query()->findOrFail(34), ['logo_path' => pcbLogo(), 'color' => '#2F6034', 'company_label' => 'RENACER ECOFUNERAL'], pcbActor());
    $brand = PlanCarnetBrands::forWeb(34);
    $carnet = CarnetDigitalContent::for(CarnetDigitalContent::INDIVIDUAL);
    $base = ['key' => '', 'status' => 'empty', 'verification' => null];

    $branded = view('affiliation-certificate-verification', $base + ['carnet' => PlanCarnetBrands::recolor($carnet, $brand['color']), 'brand' => $brand])->render();
    $standard = view('affiliation-certificate-verification', $base + ['carnet' => $carnet])->render();

    expect($brand['logoUrl'])->toContain(PlanCarnetBrands::DIRECTORY.'/renacer.png')
        ->and(strtoupper($branded))->not->toContain('#2D89CA', '#26B4E8', '#17335E')
        ->and($branded)->toContain('#2F6034', 'RENACER ECOFUNERAL', 'logo is-brand')
        ->not->toContain('tudrencasa.com · RIF J-50358368-1')
        ->and($standard)->toContain('#2D89CA', 'tudrencasa.com · RIF J-50358368-1', 'carnet-digital-logo.png')
        ->not->toContain('RENACER ECOFUNERAL');
});

it('normaliza colores y solo recolorea los azules', function (): void {
    expect(PlanCarnetBrands::normalizeColor('#2f6034'))->toBe('#2F6034')
        ->and(PlanCarnetBrands::normalizeColor('abc'))->toBe('#AABBCC')
        ->and(PlanCarnetBrands::normalizeColor('rgb(1,2,3)'))->toBeNull()
        ->and(PlanCarnetBrands::recolor(['a' => '#2d89ca', 'b' => ['#26B4E8', '#00A859']], '#2F6034'))
        ->toBe(['a' => '#2F6034', 'b' => ['#2F6034', '#00A859']]);
});

it('la tabla de planes ofrece la marca del carnet con permiso y la ventana se abre con lo guardado', function (): void {
    Filament::setCurrentPanel('business');
    $plan = Plan::query()->findOrFail(34);
    $logo = pcbLogo();
    PlanCarnetBrands::save($plan, ['logo_path' => $logo, 'color' => '#2F6034', 'company_label' => 'RENACER ECOFUNERAL'], pcbActor());
    $user = User::factory()->make(['id' => 987981, 'name' => 'Usuario Planes Pest', 'email' => 'planes-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);

    $component = Livewire::actingAs($user)
        ->test(ListPlans::class)
        ->assertTableActionVisible('carnetBrand', $plan)
        ->assertTableActionVisible('carnetBrandRemove', $plan)
        ->mountTableAction('carnetBrand', $plan)
        ->assertHasNoErrors();

    expect($component->get('mountedActions.0.data.color'))->toBe('#2F6034')
        ->and($component->get('mountedActions.0.data.company_label'))->toBe('RENACER ECOFUNERAL');
});

it('un analista de Negocios sin el permiso de diseño no ve la marca del carnet', function (): void {
    $this->actingAs(User::factory()->make(['id' => 987982, 'email' => 'sin-permiso-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['NEGOCIOS']]));

    expect(App\Filament\Business\Resources\Plans\Actions\PlanCarnetBrandActions::canManage())->toBeFalse();
});
