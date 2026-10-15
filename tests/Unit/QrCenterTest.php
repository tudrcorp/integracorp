<?php

declare(strict_types=1);

use App\Filament\Business\Pages\GeneradorQrPersonalizado;
use App\Http\Controllers\QrHostedPageController;
use App\Http\Controllers\TarjetaAfiliacionController;
use App\Models\QrDestination;
use App\Models\QrDestinationVersion;
use App\Models\QrScan;
use App\Models\User;
use App\Support\AffiliateCard\AffiliateCardPageLayout;
use App\Support\QrCenter\CardQrResolver;
use App\Support\QrCenter\QrCenterAccess;
use App\Support\QrCenter\QrCenterStats;
use App\Support\QrCenter\QrDestinationManager;
use App\Support\QrCenter\QrHostedPageManager;
use App\Support\QrCenter\QrHostedPageReview;
use App\Support\QrCenter\QrScanRecorder;
use App\Support\TarjetaAfiliacionQrPlanCatalog;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Todo escribe en tablas del centro de QR: va en una transacción que siempre se
 * revierte, con el disco simulado para no dejar imágenes ni páginas.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('local');
    CardQrResolver::forget();
});

afterEach(function (): void {
    CardQrResolver::forget();
    DB::rollBack();
});

function qrCenterAdmin(): User
{
    return User::factory()->make(['id' => null, 'name' => 'Analista QR', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);
}

function qrCenterPng(int $side = 400): string
{
    $image = imagecreatetruecolor($side, $side);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/**
 * @return array<string, mixed>
 */
function qrCenterStyle(array $overrides = []): array
{
    return [...['preset' => 'marca', 'dotColor' => '#17335e', 'bgColor' => '#ffffff', 'dotStyle' => 'rounded', 'cornerSquareStyle' => 'extra-rounded', 'cornerDotStyle' => 'dot', 'logo' => true, 'logoSize' => 0.24], ...$overrides];
}

function qrCenterHtml(): string
{
    return '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width"><title>Carnet</title></head><body><h1>Carnet a la medida</h1></body></html>';
}

function qrCenterCorporatePlanId(): int
{
    return TarjetaAfiliacionQrPlanCatalog::allowedCorporatePlanIds()[0];
}

describe('acceso', function (): void {
    it('solo SUPERADMIN administra el centro de QR', function (): void {
        expect(QrCenterAccess::allows(qrCenterAdmin()))->toBeTrue()
            ->and(QrCenterAccess::allows(User::factory()->make(['departament' => ['NEGOCIOS']])))->toBeFalse()
            ->and(QrCenterAccess::allows(User::factory()->make(['departament' => null])))->toBeFalse();
    });

    it('las acciones de la página rechazan a quien no es SUPERADMIN aunque llame directo a Livewire', function (): void {
        Filament::setCurrentPanel('business');
        $this->actingAs(User::factory()->make(['id' => 999999991, 'departament' => ['NEGOCIOS']]));

        expect(fn () => (new GeneradorQrPersonalizado)->openEditor('inclusion:0'))
            ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('las rutas POST que reemplazaban el QR con solo tener sesión ya no existen', function (): void {
        expect(Route::has('business.affiliation-tarjeta-qr.associate-plan'))->toBeFalse()
            ->and(Route::has('business.affiliation-corporate-tarjeta-qr.associate-plan'))->toBeFalse()
            ->and(Route::has('business.company-associate-tarjeta-qr.associate-inclusion'))->toBeFalse()
            ->and(method_exists(TarjetaAfiliacionController::class, 'associatePlanQr'))->toBeFalse();
    });

    it('la imagen de una versión exige ser SUPERADMIN', function (): void {
        $user = qrCenterAdmin();
        $destination = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_INCLUSION, null, $user);
        $version = app(QrDestinationManager::class)->save($destination, QrDestination::TARGET_DOCUMENT, null, null, qrCenterStyle(), qrCenterPng(), $user);

        $this->actingAs(User::factory()->make(['id' => 999999992, 'departament' => ['NEGOCIOS']]))
            ->get(route('business.qr-center.image', ['version' => $version->id]))
            ->assertForbidden();

        $this->actingAs(User::factory()->make(['id' => 999999993, 'departament' => ['SUPERADMIN']]))
            ->get(route('business.qr-center.image', ['version' => $version->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    });
});

describe('destinos y versiones', function (): void {
    it('reserva un solo destino por alcance y plan, con un código estable', function (): void {
        $user = qrCenterAdmin();
        $planId = qrCenterCorporatePlanId();
        $first = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_CORPORATE, $planId, $user);
        $again = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_CORPORATE, $planId, $user);
        $individual = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_INCLUSION, null, $user);

        expect($again->id)->toBe($first->id)
            ->and($first->code)->toMatch('/^[A-HJ-NP-Z2-9]{8}$/')
            ->and($individual->id)->not->toBe($first->id)
            ->and($first->isCustomized())->toBeFalse()
            ->and(QrDestinationManager::stableUrl($first))->toEndWith('/q/'.$first->code);
    });

    it('rechaza un plan que no corresponde al alcance', function (): void {
        app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_INDIVIDUAL, 999999, qrCenterAdmin());
    })->throws(ValidationException::class);

    it('individual y corporativo del mismo plan ya no comparten el QR', function (): void {
        $user = qrCenterAdmin();
        $planId = collect(TarjetaAfiliacionQrPlanCatalog::allowedIndividualPlanIds())
            ->intersect(TarjetaAfiliacionQrPlanCatalog::allowedCorporatePlanIds())
            ->first();
        $corporate = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_CORPORATE, $planId, $user);
        app(QrDestinationManager::class)->save($corporate, QrDestination::TARGET_URL, 'https://tudrencasa.com/corporativo', null, qrCenterStyle(), qrCenterPng(), $user);

        expect(CardQrResolver::customImagePath(QrDestination::SCOPE_CORPORATE, $planId))->not->toBeNull()
            ->and(CardQrResolver::customImagePath(QrDestination::SCOPE_INDIVIDUAL, $planId))->toBeNull();
    });

    it('guarda la versión con su imagen, su autor y su destino, y la tarjeta corporativa la imprime', function (): void {
        $user = qrCenterAdmin();
        $planId = qrCenterCorporatePlanId();
        $page = app(QrHostedPageManager::class)->create('Carnet a la medida', 'carnet-a-la-medida-prueba', qrCenterHtml(), 'carnet.html', $user);
        $destination = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_CORPORATE, $planId, $user);
        $png = qrCenterPng();

        $version = app(QrDestinationManager::class)->save($destination, QrDestination::TARGET_HOSTED_PAGE, null, $page->id, qrCenterStyle(), $png, $user);
        $destination->refresh()->load('currentVersion.hostedPage');

        expect($version->version)->toBe(1)
            ->and($version->created_by_name)->toBe('Analista QR')
            ->and($version->style['encoded_url'])->toBe(QrDestinationManager::stableUrl($destination))
            ->and($version->style['errorCorrection'])->toBe('H')
            ->and(Storage::disk('local')->get($version->image_path))->toBe($png)
            ->and($destination->isCustomized())->toBeTrue()
            ->and(QrDestinationManager::targetUrl($destination))->toBe(route('qr.page', ['slug' => 'carnet-a-la-medida-prueba']));

        $card = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
            'name' => 'Ana Pérez', 'plan_id' => $planId, 'plan' => 'X',
            'card_layout' => AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
            'template_key' => AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
            'qr_scope' => QrDestination::SCOPE_CORPORATE,
        ]);
        $allied = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
            'name' => 'Ana Pérez', 'plan_id' => $planId, 'plan' => 'X',
            'card_layout' => AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
            'template_key' => AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION_ALLIED,
            'qr_scope' => QrDestination::SCOPE_CORPORATE,
        ]);

        expect($card['plan_qr_absolute_path'])->toBe(Storage::disk('local')->path($version->image_path))
            ->and($allied['plan_qr_absolute_path'])->not->toBe($card['plan_qr_absolute_path']);
    });

    it('la tarjeta de inclusión usa el QR personalizado sin perder su nombre de archivo, que elige la plantilla', function (): void {
        $user = qrCenterAdmin();
        $destination = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_INCLUSION, null, $user);
        $version = app(QrDestinationManager::class)->save($destination, QrDestination::TARGET_DOCUMENT, null, null, qrCenterStyle(), qrCenterPng(), $user);

        $card = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
            'name' => 'Asociado', 'plan' => 'INCLUSIÓN', 'plan_qr_filename' => 'qr-plan-inclusion.png',
            'qr_scope' => QrDestination::SCOPE_INCLUSION, 'card_layout' => 'individual', 'template_key' => 'individual',
        ]);

        expect($card['plan_qr_absolute_path'])->toBe(Storage::disk('local')->path($version->image_path))
            ->and($card['plan_qr_filename'])->toBe('qr-plan-inclusion.png');
    });

    it('restaurar crea una versión nueva con la imagen de la anterior, y volver al automático deja el enlace funcionando', function (): void {
        $user = qrCenterAdmin();
        $manager = app(QrDestinationManager::class);
        $destination = $manager->ensure(QrDestination::SCOPE_INCLUSION, null, $user);
        $first = $manager->save($destination, QrDestination::TARGET_URL, 'https://tudrencasa.com/uno', null, qrCenterStyle(), qrCenterPng(400), $user);
        $manager->save($destination, QrDestination::TARGET_URL, 'https://tudrencasa.com/dos', null, qrCenterStyle(['preset' => 'clasico', 'dotColor' => '#000000', 'logo' => false]), qrCenterPng(500), $user);

        $restored = $manager->restore($first, $user);
        $destination->refresh()->load('currentVersion');

        expect($restored->version)->toBe(3)
            ->and($restored->restored_from_version_id)->toBe($first->id)
            ->and($restored->target_url)->toBe('https://tudrencasa.com/uno')
            ->and(Storage::disk('local')->get($restored->image_path))->toBe(Storage::disk('local')->get($first->image_path))
            ->and(QrDestinationVersion::query()->where('qr_destination_id', $destination->id)->count())->toBe(3);

        $manager->resetToAutomatic($destination, $user);
        $destination->refresh()->load('currentVersion');

        expect($destination->isCustomized())->toBeFalse()
            ->and(CardQrResolver::customImagePath(QrDestination::SCOPE_INCLUSION, null))->toBeNull()
            ->and(QrDestinationManager::targetUrl($destination))->toBe(QrDestinationManager::documentUrl(QrDestination::SCOPE_INCLUSION, null));
    });

    it('valida el destino, la legibilidad y la imagen en el servidor', function (string $type, ?string $url, array $style, string $png, string $field): void {
        $user = qrCenterAdmin();
        $destination = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_INCLUSION, null, $user);
        $url = $url !== null ? str_replace('{APP_URL}', rtrim((string) config('app.url'), '/'), $url) : null;

        try {
            app(QrDestinationManager::class)->save($destination, $type, $url, null, qrCenterStyle($style), $png, $user);
            $this->fail('Debió rechazarse');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey($field);
        }

        expect(QrDestinationVersion::query()->where('qr_destination_id', $destination->id)->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    })->with([
        'enlace sin https' => [QrDestination::TARGET_URL, 'http://tudrencasa.com', [], qrCenterPng(), 'targetUrl'],
        'enlace que no es una dirección' => [QrDestination::TARGET_URL, 'tudrencasa', [], qrCenterPng(), 'targetUrl'],
        'enlace a otro QR del centro (bucle)' => [QrDestination::TARGET_URL, '{APP_URL}/q/ABCDEFGH', [], qrCenterPng(), 'targetUrl'],
        'página alojada inexistente' => [QrDestination::TARGET_HOSTED_PAGE, null, [], qrCenterPng(), 'hostedPageId'],
        'tipo automático por esta vía' => [QrDestination::TARGET_AUTOMATIC, null, [], qrCenterPng(), 'targetType'],
        'poco contraste' => [QrDestination::TARGET_DOCUMENT, null, ['dotColor' => '#999999', 'bgColor' => '#ffffff'], qrCenterPng(), 'style'],
        'colores invertidos' => [QrDestination::TARGET_DOCUMENT, null, ['dotColor' => '#ffffff', 'bgColor' => '#000000'], qrCenterPng(), 'style'],
        'logo demasiado grande' => [QrDestination::TARGET_DOCUMENT, null, ['logoSize' => 0.42], qrCenterPng(), 'style'],
        'imagen que no es PNG' => [QrDestination::TARGET_DOCUMENT, null, [], 'GIF89a', 'png'],
        'PNG demasiado pequeño' => [QrDestination::TARGET_DOCUMENT, null, [], qrCenterPng(120), 'png'],
    ]);
});

describe('enlace estable y páginas alojadas', function (): void {
    it('el enlace estable redirige al destino vigente, sin caché ni sesión, y anota la lectura', function (): void {
        $user = qrCenterAdmin();
        $destination = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_INCLUSION, null, $user);
        app(QrDestinationManager::class)->save($destination, QrDestination::TARGET_URL, 'https://tudrencasa.com/inclusion', null, qrCenterStyle(), qrCenterPng(), $user);

        $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
            ->get('/q/'.strtolower($destination->code));

        $response->assertRedirect('https://tudrencasa.com/inclusion')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        expect($response->headers->getCookies())->toBe([])
            ->and(QrScan::query()->where('qr_destination_id', $destination->id)->first())
            ->kind->toBe(QrScan::KIND_SCAN)
            ->device->toBe('movil')
            ->is_bot->toBeFalse();
    });

    it('un código que no existe muestra una página amable, no un error', function (): void {
        $this->get('/q/ZZZZZZZZ')->assertNotFound()->assertSee('Este enlace no está disponible');
    });

    it('la página alojada se sirve aislada: sandbox sin mismo origen, sin cookies y sin indexar', function (): void {
        $page = app(QrHostedPageManager::class)->create('Carnet', 'carnet-aislado-prueba', qrCenterHtml(), null, qrCenterAdmin());

        $response = $this->get('/p/carnet-aislado-prueba');

        $response->assertOk()->assertSee('Carnet a la medida', false);
        $policy = (string) $response->headers->get('Content-Security-Policy');

        expect($policy)->toStartWith('sandbox ')
            ->toContain('allow-scripts')
            ->not->toContain('allow-same-origin')
            ->and($policy)->toBe(QrHostedPageController::SANDBOX_POLICY)
            ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
            ->and($response->headers->getCookies())->toBe([])
            ->and(QrScan::query()->where('qr_hosted_page_id', $page->id)->where('kind', QrScan::KIND_VIEW)->count())->toBe(1);
    });

    it('una versión nueva de la página reemplaza a la anterior en el mismo enlace', function (): void {
        $user = qrCenterAdmin();
        $page = app(QrHostedPageManager::class)->create('Carnet', 'carnet-versiones-prueba', qrCenterHtml(), null, $user);
        app(QrHostedPageManager::class)->addVersion($page, str_replace('Carnet a la medida', 'Versión dos', qrCenterHtml()), 'v2.html', $user);

        $this->get('/p/carnet-versiones-prueba')->assertOk()->assertSee('Versión dos')->assertDontSee('Carnet a la medida');
        expect($page->versions()->count())->toBe(2);
    });

    it('no publica un archivo que no es HTML ni repite un enlace', function (): void {
        $user = qrCenterAdmin();
        app(QrHostedPageManager::class)->create('Uno', 'repetido-prueba', qrCenterHtml(), null, $user);

        expect(fn () => app(QrHostedPageManager::class)->create('Dos', 'repetido-prueba', qrCenterHtml(), null, $user))->toThrow(ValidationException::class)
            ->and(fn () => app(QrHostedPageManager::class)->create('Tres', 'otro-prueba', 'texto plano', null, $user))->toThrow(ValidationException::class)
            ->and(QrHostedPageManager::suggestSlug('Carnet Tu Dr en Casa v4 (Tailor Made)'))->toBe('carnet-tu-dr-en-casa-v4-tailor-made');
    });
});

describe('revisión del HTML y lecturas', function (): void {
    it('detecta recursos de internet aunque vengan comprimidos dentro de una página empaquetada', function (): void {
        $asset = base64_encode((string) gzencode("const CDN = 'https://unpkg.com/lucide-static@0.427.0/icons/';"));
        $bundle = '<!DOCTYPE html><html><head></head><body><script type="__bundler/manifest">'
            .json_encode(['a' => ['mime' => 'text/javascript', 'compressed' => true, 'data' => $asset]])
            .'</script><script type="__bundler/template">"<meta name=\"viewport\" content=\"width=device-width\">"</script></body></html>';

        $review = collect(QrHostedPageReview::review($bundle))->keyBy('title');

        expect(QrHostedPageReview::externalResourceHosts($bundle))->toBe(['unpkg.com'])
            ->and($review['Carga recursos de internet']['level'])->toBe(QrHostedPageReview::LEVEL_WARN)
            ->and($review->has('Se adapta al móvil'))->toBeTrue()
            ->and(QrHostedPageReview::blocks($review->values()->all()))->toBeFalse();
    });

    it('un archivo demasiado grande bloquea la publicación', function (): void {
        $review = QrHostedPageReview::review('<!DOCTYPE html>'.str_repeat('a', QrHostedPageReview::MAX_BYTES));

        expect(QrHostedPageReview::blocks($review))->toBeTrue();
    });

    it('las lecturas no cuentan robots y agrupan visitantes por día sin guardar la IP', function (): void {
        $user = qrCenterAdmin();
        $destination = app(QrDestinationManager::class)->ensure(QrDestination::SCOPE_INCLUSION, null, $user);
        $phone = 'Mozilla/5.0 (Linux; Android 14) Mobile Safari/537.36';

        foreach ([[$phone, '10.0.0.1'], [$phone, '10.0.0.1'], [$phone, '10.0.0.2'], ['WhatsApp/2.23 A', '10.0.0.3']] as [$agent, $ip]) {
            QrScanRecorder::record(QrScan::KIND_SCAN, $destination->id, null, Illuminate\Http\Request::create('/q/x', 'GET', server: ['HTTP_USER_AGENT' => $agent, 'REMOTE_ADDR' => $ip]));
        }

        expect(QrCenterStats::byDestination()[$destination->id])->toBe(['scans' => 3, 'visitors' => 2, 'mobile' => 3])
            ->and(QrScan::query()->where('qr_destination_id', $destination->id)->pluck('visitor_hash')->every(fn (string $hash): bool => ! str_contains($hash, '10.0.0')))->toBeTrue()
            ->and(collect(QrCenterStats::dailyForDestination($destination->id))->last()['scans'])->toBe(3)
            ->and(QrCenterStats::dailyForDestination($destination->id))->toHaveCount(QrCenterStats::DAYS);
    });
});

it('la página del centro de QR abre en Negocios para SUPERADMIN', function (): void {
    Filament::setCurrentPanel('business');
    $this->actingAs(User::factory()->make(['id' => 999999994, 'departament' => ['SUPERADMIN']]));

    Livewire::test(GeneradorQrPersonalizado::class)
        ->assertOk()
        ->assertSee('Centro de QR')
        ->assertSee('Tarjetas de nuevos negocios')
        ->call('setTab', 'paginas')
        ->assertSee('Subir página')
        ->call('setTab', 'libre')
        ->assertSee('QR libre');
});
