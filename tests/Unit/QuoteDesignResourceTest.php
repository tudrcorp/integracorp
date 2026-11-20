<?php

declare(strict_types=1);

use App\Filament\Business\Resources\QuoteDesigns\Pages\CreateQuoteDesign;
use App\Filament\Business\Resources\QuoteDesigns\Pages\EditQuoteDesign;
use App\Filament\Business\Resources\QuoteDesigns\Pages\ListQuoteDesigns;
use App\Filament\Business\Resources\QuoteDesigns\QuoteDesignResource;
use App\Models\QuoteDesign;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Quotes\Design\QuoteDesignPersistence;
use App\Support\Quotes\Design\QuoteDesignResolver;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Pantalla «Diseño de cotización» de Negocios. Escribe, así que va en una transacción revertida
 * y con el disco `public` falso. Usuario con make(): no se guarda.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
    QuoteDesignResolver::flush();
    Filament::setCurrentPanel('business');
});

afterEach(function (): void {
    DB::rollBack();
    QuoteDesignResolver::flush();
});

function quoteDesignSuperAdmin(): User
{
    return User::factory()->make([
        'id' => 990000101,
        'name' => 'QA Diseño',
        'email' => 'qa.disenio@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]);
}

it('la pantalla tiene permiso propio en Negocios', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(QuoteDesignResource::class))->toBe(['disenio-de-cotizacion'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(QuoteDesignResource::class))->toBe('NEGOCIOS');
});

it('un analista de Negocios sin el permiso no entra', function (): void {
    $this->actingAs(User::factory()->make([
        'id' => 990000102,
        'email' => 'qa.sinpermiso@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));

    expect(QuoteDesignResource::canAccess())->toBeFalse();
});

it('muestra el listado vacío con su guía', function (): void {
    $this->actingAs(quoteDesignSuperAdmin());

    Livewire::test(ListQuoteDesigns::class)
        ->assertSuccessful()
        ->assertSee('Todavía no hay diseños');
});

it('crea un diseño desde la pantalla con los colores por defecto', function (): void {
    $this->actingAs(quoteDesignSuperAdmin());

    Livewire::test(CreateQuoteDesign::class)
        ->assertSuccessful()
        ->assertActionExists('importHtml')
        ->assertActionExists('preview')
        ->set('data.name', 'Renacer desde la pantalla')
        ->set('data.theme.primary', '#17612F')
        ->call('create')
        ->assertHasNoFormErrors();

    $design = QuoteDesign::query()->where('name', 'Renacer desde la pantalla')->first();

    expect($design)->not->toBeNull()
        ->and($design->theme['primary'])->toBe('#17612F')
        ->and($design->theme['benefits_heading'])->toBe('Beneficios del plan')
        ->and($design->version)->toBe(1)
        ->and($design->status)->toBe(QuoteDesign::STATUS_INACTIVE)
        ->and($design->created_by)->toBe('QA Diseño');
});

it('abre la edición con las condiciones como una por línea', function (): void {
    $this->actingAs(quoteDesignSuperAdmin());

    $design = QuoteDesignPersistence::save(null, [
        'name' => 'Para editar',
        'theme' => ['conditions' => ['Primera condición', 'Segunda condición']],
    ]);

    Livewire::test(EditQuoteDesign::class, ['record' => $design->getKey()])
        ->assertSuccessful()
        ->assertSet('data.theme.conditions', "Primera condición\nSegunda condición")
        ->assertSee('Versión 1');
});

it('genera la vista previa con lo que hay en pantalla y la abre en el modal', function (): void {
    Storage::fake('local');
    $this->actingAs(quoteDesignSuperAdmin());

    $planId = (int) DB::table('fees')
        ->where('status', 'ACTIVO')
        ->whereNotNull('plan_id')
        ->whereNotIn('plan_id', DB::table('quote_design_plan')->pluck('plan_id'))
        ->orderBy('plan_id')
        ->value('plan_id');

    if ($planId === 0) {
        $this->markTestSkipped('No hay un plan con tarifas activas para la vista previa.');
    }

    Livewire::test(CreateQuoteDesign::class)
        ->set('data.name', 'Vista previa')
        ->set('data.theme.primary', '#17612F')
        ->mountAction('preview')
        ->set('mountedActions.0.data.plan_id', $planId)
        ->set('mountedActions.0.data.scope', 'individual')
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertActionMounted('showPreview');

    $files = Storage::disk('local')->allFiles('quote-design-previews/990000101');

    expect($files)->toHaveCount(1)
        ->and(str_starts_with((string) Storage::disk('local')->get($files[0]), '%PDF'))->toBeTrue()
        ->and(QuoteDesign::query()->where('name', 'Vista previa')->exists())->toBeFalse();
});

it('importa el HTML de diseño y llena colores y textos sin guardar', function (): void {
    Storage::fake('local');
    $this->actingAs(quoteDesignSuperAdmin());

    $html = '<style>:root{--verde:#17612F;--ocre:#C89A2B}h1{color:var(--verde)}.filete{background:var(--ocre)}</style>'
        .'<header><img src="logo.png"></header><div><span>Propuesta económica</span><h1>Plan</h1></div>'
        .'<footer><span>Asistencia Funeraria</span><div><span>Un producto de</span><span>Tu Dr. Group</span></div></footer>';

    Livewire::test(CreateQuoteDesign::class)
        ->mountAction('importHtml')
        ->set('mountedActions.0.data.html', UploadedFile::fake()->createWithContent('renacer.html', $html))
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertSet('data.theme.primary', '#17612F')
        ->assertSet('data.theme.accent', '#C89A2B')
        ->assertSet('data.theme.footer_left', 'Asistencia Funeraria')
        ->assertNotified('Diseño importado: revise y guarde');

    expect(QuoteDesign::query()->count())->toBe(0);
});

it('después de guardar con una página nueva, guardar otra vez no falla', function (): void {
    $this->actingAs(quoteDesignSuperAdmin());

    $design = QuoteDesignPersistence::save(null, ['name' => 'Dos guardados']);

    $image = imagecreatetruecolor(816, 1056);
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    $page = Livewire::test(EditQuoteDesign::class, ['record' => $design->getKey()])
        ->set('data.pages_before', [UploadedFile::fake()->createWithContent('portada.png', $png)])
        ->call('save')
        ->assertHasNoFormErrors();

    $saved = $design->refresh()->pages_before;

    $page->call('save')->assertHasNoFormErrors();

    expect($saved)->toHaveCount(1)
        ->and($design->refresh()->pages_before)->toBe($saved)
        ->and(Storage::disk('public')->exists($saved[0]))->toBeTrue();
});
