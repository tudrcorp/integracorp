<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\Plan;
use App\Models\PlanCertificateTemplate;
use App\Models\User;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\Affiliations\Certificates\PlanCertificateCover;
use App\Support\Affiliations\Certificates\PlanCertificateTemplateEngine as Engine;
use App\Support\Affiliations\Certificates\PlanCertificateTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('local');
    Mail::fake();
    Queue::fake();
});

afterEach(function (): void {
    DB::rollBack();
});

function pctActor(): User
{
    return User::factory()->make(['id' => 987970, 'name' => 'Analista Certificados Pest']);
}

function pctExample(): string
{
    return (string) file_get_contents(resource_path('views/documents/affiliation-certificate/custom/plantilla-ejemplo.html'));
}

/**
 * Afiliación individual vigente real (solo se lee; la emisión se revierte).
 */
function pctAffiliation(): Affiliation
{
    return Affiliation::query()
        ->whereIn('status', ['ACTIVA', 'PRE-APROBADA'])
        ->whereNotNull('plan_id')
        ->whereHas('affiliates', fn ($q) => $q->where('status', 'ACTIVO'))
        ->latest('id')
        ->firstOrFail();
}

it('limpia scripts, eventos, marcos y recursos externos, y deja las imágenes del sistema', function (): void {
    $clean = Engine::sanitize('<html><head><style>@import url(http://x.com/a.css); .a{background:url(https://x.com/b.png)}</style><link rel="stylesheet" href="http://x.com/c.css"></head><body>'
        .'<script>alert(1)</script><iframe src="http://x.com"></iframe><p onclick="steal()">Hola</p>'
        .'<img src="{{imagen.qr}}"><img src="https://evil.com/x.png"><img src="data:image/png;base64,iVBORw0KGgo="><a href="javascript:alert(1)">x</a><?php echo 1; ?></body></html>');

    expect($clean['html'])->not->toContain('<script')
        ->and($clean['html'])->not->toContain('<iframe')
        ->and($clean['html'])->not->toContain('onclick')
        ->and($clean['html'])->not->toContain('evil.com')
        ->and($clean['html'])->not->toContain('x.com')
        ->and($clean['html'])->not->toContain('javascript:')
        ->and($clean['html'])->not->toContain('<?php')
        ->and($clean['html'])->toContain('src="{{imagen.qr}}"')
        ->and($clean['html'])->toContain('data:image/png;base64')
        ->and($clean['removed'])->not->toBeEmpty();
});

it('valida marcadores y bloques con mensajes claros', function (): void {
    expect(Engine::validate('{{plan.nombre}} {{beneficio.descripcion}}'))->toContain('El marcador {{beneficio.descripcion}} solo se puede usar dentro de <!-- inicio:beneficios --> … <!-- fin:beneficios -->.')
        ->and(Engine::validate('{{plan.inventado}}')[0])->toContain('no existe')
        ->and(Engine::validate('<!-- inicio:beneficios -->{{beneficio.detalle}}')[0])->toContain('no se cerró')
        ->and(Engine::validate('<!-- si:lunes -->x<!-- fin:lunes -->')[0])->toContain('«si:lunes» no existe')
        ->and(Engine::validate(pctExample()))->toBe([]);
});

it('dibuja datos escapados, bloques que se repiten y condiciones (incluida la negada)', function (): void {
    $html = '<p>{{contratante.nombre}}</p><!-- si:pagado -->PAGADO<!-- fin:pagado --><!-- si:no_pagado -->PENDIENTE<!-- fin:no_pagado -->'
        .'<ul><!-- inicio:beneficios --><!-- si:seccion --><h>{{beneficio.seccion}}</h><!-- fin:seccion --><!-- si:no_seccion --><li>{{beneficio.descripcion}}={{beneficio.detalle}}</li><!-- fin:no_seccion --><!-- fin:beneficios --></ul>';
    $data = ['contratante' => '<b>ACME & Cía</b>', 'pagado' => false, 'benefitRows' => [['section' => 'Salud'], ['t' => 'Consulta', 'cob' => 'Incluido', 'limited' => false]]];

    $out = Engine::render($html, $data);

    expect($out)->toContain('&lt;b&gt;ACME &amp; Cía&lt;/b&gt;')
        ->and($out)->toContain('PENDIENTE')
        ->and($out)->not->toContain('PAGADO')
        ->and($out)->toContain('<h>Salud</h>')
        ->and($out)->toContain('<li>Consulta=Incluido</li>');
});

it('sube versiones, rechaza un diseño inválido sin guardar nada, restaura y vuelve al predeterminado', function (): void {
    $plan = Plan::query()->findOrFail(26);

    expect(fn () => PlanCertificateTemplates::upload($plan, '<p>{{plan.inventado}}</p>', 'malo.html', null, pctActor()))->toThrow(RuntimeException::class, 'no existe')
        ->and(PlanCertificateTemplate::query()->where('plan_id', 26)->count())->toBe(0);

    $v1 = PlanCertificateTemplates::upload($plan, pctExample(), 'v1.html', 'Primera', pctActor());
    $v2 = PlanCertificateTemplates::upload($plan, str_replace('Certificado de afiliación', 'Certificado REPSOL', pctExample()), 'v2.html', null, pctActor());

    expect($v1->version)->toBe(1)
        ->and($v2->version)->toBe(2)
        ->and($v1->fresh()->status)->toBe(PlanCertificateTemplate::STATUS_INACTIVE)
        ->and(PlanCertificateTemplates::activeFor(26)?->getKey())->toBe($v2->getKey())
        ->and($v2->page_count)->toBe(1);

    PlanCertificateTemplates::restore($v1, pctActor(), 'La versión 2 tenía un error de marca.');
    expect(PlanCertificateTemplates::activeFor(26)?->getKey())->toBe($v1->getKey());

    PlanCertificateTemplates::useDefault($plan, pctActor(), 'Marketing pidió volver al diseño estándar.');
    expect(PlanCertificateTemplates::activeFor(26))->toBeNull()
        ->and(fn () => PlanCertificateTemplates::useDefault($plan, pctActor(), 'Segundo intento sin diseño activo.'))->toThrow(RuntimeException::class, 'ya usa');
});

it('el generador usa el diseño del plan fijado en la emisión, con la numeración completa, y sin diseño queda igual', function (): void {
    $affiliation = pctAffiliation();
    $plan = Plan::query()->findOrFail($affiliation->plan_id);
    $service = app(AffiliationCertificateGeneratorService::class);
    $issuer = User::query()->firstOrFail();

    $standard = $service->issueIndividual($affiliation, [], $issuer);
    $standardData = $service->documentData($standard);
    expect($standard->plan_certificate_template_id)->toBeNull()
        ->and($standardData['pages'][0]['kind'])->toBe('cert');

    $template = PlanCertificateTemplates::upload($plan, pctExample(), 'diseño.html', null, pctActor());
    $issue = $service->issueIndividual($affiliation, [], $issuer);
    $data = $service->documentData($issue);

    expect((int) $issue->plan_certificate_template_id)->toBe((int) $template->getKey())
        ->and($data['pages'][0]['kind'])->toBe(PlanCertificateCover::KIND)
        ->and(collect($data['pages'])->pluck('kind')->intersect(['cert', 'cont'])->all())->toBe([])
        ->and($data['totalPaginas'])->toBe($data['pages'][0]['pageCount'] + count($data['pages']) - 1);

    // Una versión nueva no cambia la emisión que ya se hizo.
    PlanCertificateTemplates::upload($plan, str_replace('Certificado de afiliación', 'Otro título', pctExample()), 'v2.html', null, pctActor());
    expect($service->documentData($issue->fresh())['pages'][0]['templateId'])->toBe((int) $template->getKey());

    $pdf = $service->renderPdf($issue->fresh(), false);
    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBeGreaterThanOrEqual(1);
});

it('la tabla de planes muestra el estado del certificado y ofrece las acciones con permiso', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $plan = Plan::query()->findOrFail(26);
    PlanCertificateTemplates::upload($plan, pctExample(), 'diseño.html', null, pctActor());
    App\Filament\Business\Resources\Plans\Actions\PlanCertificateTemplateActions::flush();
    $user = User::factory()->make(['id' => 987971, 'name' => 'Usuario Planes Pest', 'email' => 'planes-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);

    Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\Plans\Pages\ListPlans::class)
        ->assertTableColumnStateSet('certificate_template', 'Diseño propio (v1)', $plan)
        ->assertTableActionVisible('certificateTemplate', $plan)
        ->assertTableActionVisible('certificateTemplateSample', $plan)
        ->assertTableActionVisible('certificateTemplateDefault', $plan);
});

it('la ventana «Diseño del certificado» se abre y dibuja el estado y la guía de marcadores', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $plan = Plan::query()->findOrFail(26);
    $user = User::factory()->make(['id' => 987972, 'name' => 'Usuario Planes Pest', 'email' => 'planes-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);

    $guide = view('filament.business.plans.certificate-template-guide')->render();
    expect($guide)->toContain('{{plan.nombre}}', 'Bloque que se repite: beneficios', 'Bloque que se repite: afiliados', '{{beneficio.descripcion}}', 'si:no_pagado');

    $status = view('filament.business.plans.certificate-template-status', ['versions' => PlanCertificateTemplate::query()->where('plan_id', 26)->get()])->render();
    expect($status)->toContain('Este plan usa el certificado predeterminado');

    $component = Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\Plans\Pages\ListPlans::class)
        ->mountTableAction('certificateTemplate', $plan)
        ->assertHasNoErrors();

    expect($component->get('mountedActions.0.name'))->toBe('certificateTemplate');
});

it('rechaza un diseño que arma su contenido con JavaScript o que no usa marcadores, y avisa de flex y fuentes WOFF', function (): void {
    $plan = Plan::query()->findOrFail(26);
    $jsBuilt = '<html><head><style>.a{display:flex} @font-face{src:url(data:font/woff2;base64,d09GMg==)}</style></head><body><div id="app"></div><script>document.getElementById("app").innerHTML = "<h1>Renacer</h1>";</script></body></html>';
    $static = '<html><body><table><tr><td>Certificado del plan Renacer, con textos fijos que no cambian nunca entre una afiliación y otra.</td></tr></table></body></html>';

    $js = PlanCertificateTemplates::inspect($plan, $jsBuilt);
    expect(implode(' ', $js['errors']))->toContain('lo arma JavaScript')
        ->and($js['warnings'])->toHaveCount(2);

    expect(implode(' ', PlanCertificateTemplates::inspect($plan, $static)['errors']))->toContain('no usa ningún marcador')
        ->and(fn () => PlanCertificateTemplates::upload($plan, $jsBuilt, 'renacer.html', null, pctActor()))->toThrow(RuntimeException::class, 'JavaScript')
        ->and(PlanCertificateTemplate::query()->where('plan_id', 26)->count())->toBe(0)
        ->and(Engine::MAX_BYTES)->toBe(5242880);
});
