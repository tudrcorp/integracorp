<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\CollectionNoticeRegeneration;
use App\Models\User;
use App\Support\Collections\CollectionNotice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

/**
 * Escribe en `collection_notice_regenerations` y dibuja PDF: transacción que siempre se revierte
 * y disco `public` simulado (el real es `public/storage`, donde viven los avisos de verdad).
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
    CarbonImmutable::setTestNow('2026-10-15 10:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    DB::rollBack();
});

function noticeCollection(string $type = 'AFILIACION INDIVIDUAL'): Collection
{
    return Collection::query()->where('type', $type)->where('status', 'POR PAGAR')->latest('id')->firstOrFail();
}

function noticeAnalyst(array $departments = ['SUPERADMIN', 'ADMINISTRACION']): User
{
    return User::factory()->make([
        'id' => null,
        'name' => 'Analista Cobranza',
        'email' => 'cobranza.prueba@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]);
}

describe('período de cobro', function (): void {
    it('va desde la fecha de la cuota hasta esa fecha más la frecuencia, menos un día', function (string $frequency, string $from, string $to): void {
        $collection = new Collection(['next_payment_date' => $from, 'payment_frequency' => $frequency]);

        [$start, $end] = CollectionNotice::defaultPeriod($collection);

        expect($start->format('d/m/Y'))->toBe($from)
            ->and($end?->format('d/m/Y'))->toBe($to);
    })->with([
        'mensual' => ['MENSUAL', '05/07/2027', '04/08/2027'],
        'trimestral' => ['TRIMESTRAL', '05/07/2027', '04/10/2027'],
        'semestral' => ['SEMESTRAL', '05/07/2027', '04/01/2028'],
        'anual' => ['ANUAL', '05/07/2027', '04/07/2028'],
        'fin de mes sin desbordar' => ['MENSUAL', '31/01/2027', '27/02/2027'],
        'frecuencia en minúsculas' => ['trimestral', '01/03/2027', '31/05/2027'],
    ]);

    it('sin frecuencia conocida no inventa el fin del período', function (): void {
        [$start, $end] = CollectionNotice::defaultPeriod(new Collection(['next_payment_date' => '05/07/2027', 'payment_frequency' => 'QUINCENAL']));

        expect($start?->format('d/m/Y'))->toBe('05/07/2027')->and($end)->toBeNull();
    });

    it('usa la fecha ISO de la cuota si la fecha de próximo pago no es legible', function (): void {
        [$start] = CollectionNotice::defaultPeriod(new Collection(['next_payment_date' => '', 'filter_next_payment_date' => '2027-07-05', 'payment_frequency' => 'ANUAL']));

        expect($start?->format('d/m/Y'))->toBe('05/07/2027');
    });
});

describe('regeneración', function (): void {
    it('el formulario propone la emisión de hoy y el período según la frecuencia', function (): void {
        $collection = noticeCollection();
        $defaults = CollectionNotice::formDefaults($collection);
        [$from, $to] = CollectionNotice::defaultPeriod($collection);

        expect($defaults['emission_date'])->toBe('15/10/2026')
            ->and($defaults['period_from'])->toBe($from->format('d/m/Y'))
            ->and($defaults['period_to'])->toBe($to->format('d/m/Y'))
            ->and($defaults['full_name'])->toBe(trim((string) $collection->affiliate_full_name))
            ->and($defaults['last_regenerated'])->toBeNull();
    });

    it('regenera con lo editado sin tocar la cuota y lo precarga la próxima vez', function (): void {
        $collection = noticeCollection();
        $before = $collection->fresh()->getAttributes();

        $regeneration = app(CollectionNotice::class)->regenerate($collection, [
            'full_name' => 'NOMBRE CORREGIDO',
            'document' => (string) $collection->affiliate_ci_rif,
            'address' => 'DIRECCIÓN NUEVA',
            'phone' => (string) $collection->affiliate_phone,
            'email' => 'nuevo@correo.com',
            'emission_date' => '14/10/2026',
            'period_from' => '01/11/2026',
            'period_to' => '31/01/2027',
        ], noticeAnalyst());

        expect($regeneration->user_name)->toBe('Analista Cobranza')
            ->and($regeneration->emission_date->toDateString())->toBe('2026-10-14')
            ->and($regeneration->period_to->toDateString())->toBe('2027-01-31')
            ->and($regeneration->changed_fields)->toContain('full_name', 'address', 'email')
            ->and($regeneration->changed_fields)->not->toContain('phone')
            ->and(Storage::disk('public')->exists('avisoDeCobro/'.CollectionNotice::pdfFilename($collection)))->toBeTrue()
            ->and(Storage::disk('public')->allFiles())->toHaveCount(1)
            ->and($collection->fresh()->getAttributes())->toBe($before);

        $next = CollectionNotice::formDefaults($collection);

        expect($next['full_name'])->toBe('NOMBRE CORREGIDO')
            ->and($next['address'])->toBe('DIRECCIÓN NUEVA')
            ->and($next['period_from'])->toBe('01/11/2026')
            ->and($next['emission_date'])->toBe('15/10/2026')
            ->and($next['last_regenerated'])->toContain('Analista Cobranza');
    });

    it('rechaza un período al revés o una fecha mal escrita sin dibujar ni registrar nada', function (array $edits, string $field): void {
        $collection = noticeCollection();

        try {
            app(CollectionNotice::class)->regenerate($collection, $edits, noticeAnalyst());
            $this->fail('Debió rechazarse');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey($field);
        }

        expect(CollectionNoticeRegeneration::query()->where('collection_id', $collection->id)->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    })->with([
        'período al revés' => [['period_from' => '01/12/2026', 'period_to' => '30/11/2026'], 'period_to'],
        'fecha imposible' => [['emission_date' => '31/02/2026'], 'emission_date'],
        'formato ISO' => [['period_from' => '2026-12-01'], 'period_from'],
        'período vacío desde el formulario' => [['period_from' => '', 'period_to' => ''], 'period_from'],
    ]);

    it('la regeneración automática (rutas antiguas) usa los datos por defecto', function (): void {
        $collection = noticeCollection('AFILIACION CORPORATIVA');

        $regeneration = app(CollectionNotice::class)->regenerate($collection, null, null);

        expect($regeneration->emission_date->toDateString())->toBe('2026-10-15')
            ->and($regeneration->period_from)->not->toBeNull()
            ->and($regeneration->changed_fields)->toBeNull()
            ->and($regeneration->user_id)->toBeNull();
    });

    it('si otro analista está regenerando el mismo aviso, avisa en vez de pisarlo', function (): void {
        $collection = noticeCollection();
        $lock = Cache::lock('collection-notice:'.$collection->id, 30);
        $lock->get();

        try {
            expect(fn () => app(CollectionNotice::class)->regenerate($collection, null, null))
                ->toThrow(ValidationException::class, 'Otro analista está regenerando este aviso');
        } finally {
            $lock->release();
        }
    });
});

describe('rutas', function (): void {
    // Con la configuración cacheada Laravel no detecta que corre en tests y exigiría el token CSRF.
    beforeEach(fn () => $this->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class));

    it('regenerar exige acceso a Gestión de Cobranza en Administración', function (): void {
        $collection = noticeCollection();

        $this->actingAs(noticeAnalyst(['NEGOCIOS']))
            ->postJson(route('aviso-cobro.regenerate-async', $collection), [])
            ->assertForbidden()
            ->assertJson(['message' => 'No tiene permiso para regenerar avisos de cobro.']);

        $this->actingAs(noticeAnalyst(['NEGOCIOS']))
            ->postJson(route('aviso-cobro.send-email', $collection), [])
            ->assertForbidden();
    });

    it('regenera desde el formulario y devuelve la vista previa', function (): void {
        $collection = noticeCollection();
        $defaults = CollectionNotice::formDefaults($collection);

        $this->actingAs(noticeAnalyst())
            ->postJson(route('aviso-cobro.regenerate-async', $collection), [
                'notice' => [...Illuminate\Support\Arr::only($defaults, ['full_name', 'document', 'address', 'phone', 'email', 'emission_date', 'period_from', 'period_to'])],
            ])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('preview_url', fn (string $url): bool => str_contains($url, 'avisoDeCobro/'.CollectionNotice::pdfFilename($collection)));
    });

    it('valida el formulario y responde con el campo y el mensaje en español', function (): void {
        $collection = noticeCollection();

        $response = $this->actingAs(noticeAnalyst())
            ->postJson(route('aviso-cobro.regenerate-async', $collection), [
                'notice' => ['full_name' => 'X', 'email' => 'no-es-correo', 'emission_date' => '15/10/2026', 'period_from' => '10/10/2026', 'period_to' => '01/10/2026'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['notice.email', 'notice.period_to']);

        expect($response->json('errors')['notice.period_to'][0])->toBe('El fin del período de cobro debe ser igual o posterior al inicio.')
            ->and($response->json('errors')['notice.email'][0])->toBe('Escriba un correo válido.');
    });
});

describe('modal', function (): void {
    it('no anida un form ni usa botones submit: la ventana de la modal de Filament ya es un form', function (): void {
        $view = file_get_contents(resource_path('views/filament/administration/collections/aviso-cobro-preview-modal.blade.php'));
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $view);

        // Un <form> anidado se descarta y su botón enviaría el form de Filament: ejecuta la acción y cierra la modal.
        expect($markup)->not->toContain('<form')
            ->not->toContain('type="submit"')
            ->toContain('x-on:click="regenerate()"')
            ->toContain('x-on:keydown.enter=');
    });
});

describe('plantillas', function (): void {
    it('ya no imprimen el número de aviso en el encabezado e imprimen el período al final', function (string $view): void {
        $source = file_get_contents(resource_path('views/documents/'.$view.'.blade.php'));

        expect($source)->not->toContain('Aviso de Cobro: Nro.')
            ->toContain("PERIODO DE COBRO: {{ \$data['period_label'] }}");
    })->with(['aviso-de-cobro', 'aviso-de-cobro-corporativo']);

    it('el PDF individual lleva la emisión y el período elegidos', function (): void {
        $collection = noticeCollection();
        $build = Closure::bind(
            fn (...$args) => $this->documentData(...$args),
            new CollectionNotice,
            CollectionNotice::class,
        );
        $data = $build($collection, Illuminate\Support\Arr::only(CollectionNotice::formDefaults($collection), CollectionNotice::CLIENT_FIELDS), CarbonImmutable::parse('2026-10-15'), CarbonImmutable::parse('2027-07-05'), CarbonImmutable::parse('2027-10-04'));
        $html = view('documents.aviso-de-cobro', ['data' => $data])->render();

        expect($html)->toContain('Fecha de Emisión: 15/10/2026')
            ->toContain('PERIODO DE COBRO: 05/07/2027 AL 04/10/2027')
            ->not->toContain('Aviso de Cobro: Nro.');
    });
});
