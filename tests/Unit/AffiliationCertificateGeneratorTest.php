<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Affiliations\Pages\CertificateGenerator;
use App\Models\Affiliation;
use App\Models\AffiliationCertificateIssue;
use App\Models\Plan;
use App\Models\User;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\Affiliations\Certificates\CertificateBenefits;
use App\Support\Affiliations\Certificates\CertificateDocumentData;
use App\Support\Affiliations\Certificates\CertificateFormat;
use App\Support\Affiliations\Certificates\CertificatePaymentPeriod;
use App\Support\Affiliations\Certificates\CertificateTheme;
use App\Support\Affiliations\Certificates\CertificateVerificationKey;
use App\Support\Affiliations\Certificates\IndividualCertificateDocument;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Las pruebas que emiten certificados escriben en `affiliation_certificate_issues`
 * y en la bitácora: todo va en una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    // Los PDF se guardan al dibujarse: con el disco simulado no quedan archivos
    // de emisiones que la transacción revierte.
    Illuminate\Support\Facades\Storage::fake('local');
});
afterEach(fn () => DB::rollBack());

function certificateSuperAdmin(): User
{
    return User::query()->where('email', 'like', '%@tudrencasa.com')->where('is_superAdmin', 1)->firstOrFail();
}

function certificatePeople(int $count): array
{
    // Ojo: range(1, 0) devuelve [1, 0] en PHP, no una lista vacía.
    if ($count < 1) {
        return [];
    }

    return array_map(static fn (int $i): array => ['id' => $i, 'num' => $i, 'nombre' => 'Persona '.$i, 'docFmt' => 'C.I. V-1', 'nac' => '01/01/2000', 'parentesco' => 'Hijo'], range(1, $count));
}

describe('período pagado', function (): void {
    $today = CarbonImmutable::parse('2026-11-15');

    it('el pago inicial cubre la primera cuota y la cuota vigente pagada da el sello', function () use ($today): void {
        $period = CertificatePaymentPeriod::resolve(
            CarbonImmutable::parse('2026-09-30'), 'TRIMESTRAL',
            paidCollectionDates: [],
            initialPaymentDates: ['30-09-2026'],
            today: $today,
        );

        expect($period->currentIsPaid)->toBeTrue()
            ->and($period->currentPeriodLabel())->toBe('30/09/2026 – 30/12/2026')
            ->and($period->paidUntil->toDateString())->toBe('2026-12-30')
            ->and($period->end->toDateString())->toBe('2027-09-30');
    });

    it('sin pagos el período vigente queda pendiente', function () use ($today): void {
        $period = CertificatePaymentPeriod::resolve(CarbonImmutable::parse('2026-09-30'), 'TRIMESTRAL', [], [], $today);

        expect($period->currentIsPaid)->toBeFalse()
            ->and($period->paidPeriodLabel())->toBeNull();
    });

    it('un hueco detiene el «pagado hasta» aunque haya cuotas posteriores pagadas', function (): void {
        $period = CertificatePaymentPeriod::resolve(
            CarbonImmutable::parse('2026-01-10'), 'TRIMESTRAL',
            paidCollectionDates: ['2026-01-12', '2026-07-10'],
            initialPaymentDates: [],
            today: CarbonImmutable::parse('2026-08-01'),
        );

        // 10/01 pagada (2 días de diferencia, dentro de la tolerancia), 10/04 no: se corta ahí.
        expect($period->paidUntil->toDateString())->toBe('2026-04-10')
            ->and($period->currentIsPaid)->toBeFalse();
    });

    it('una renovación aceptada antes de tiempo evalúa el año en curso', function (): void {
        $period = CertificatePaymentPeriod::resolve(
            CarbonImmutable::parse('2027-02-04'), 'ANUAL',
            paidCollectionDates: ['2026-02-04'],
            initialPaymentDates: [],
            today: CarbonImmutable::parse('2026-10-05'),
        );

        expect($period->start->toDateString())->toBe('2026-02-04')
            ->and($period->currentIsPaid)->toBeTrue();
    });
});

it('la clave de verificación es aleatoria y se normaliza con o sin guiones', function (): void {
    $key = CertificateVerificationKey::generate();

    expect($key)->toMatch('/^CER-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/')
        ->and(CertificateVerificationKey::generate())->not->toBe($key)
        ->and(CertificateVerificationKey::normalize(strtolower(str_replace('-', '', $key))))->toBe($key)
        ->and(CertificateVerificationKey::normalize(' '.$key.' '))->toBe($key)
        ->and(CertificateVerificationKey::normalize('CER-1234'))->toBeNull()
        ->and(CertificateVerificationKey::normalize("' OR 1=1 --"))->toBeNull();
});

it('formatea nombres, documentos y parentescos como el diseño', function (): void {
    expect(CertificateFormat::name('GLADIS  GUILLEN '))->toBe('Gladis Guillen')
        ->and(CertificateFormat::name('...'))->toBe('—')
        ->and(CertificateFormat::document('24440387'))->toBe('C.I. V-24.440.387')
        ->and(CertificateFormat::document('E-84123456'))->toBe('C.I. E-84.123.456')
        ->and(CertificateFormat::document('J-40123456-7'))->toBe('RIF J-40123456-7')
        ->and(CertificateFormat::relationship('HIJA'))->toBe('Hija')
        ->and(CertificateFormat::relationship(null))->toBe('Titular');
});

it('elige el tema de color por plan', function (): void {
    expect(CertificateTheme::keyForPlan('PLAN ESPECIAL'))->toBe(CertificateTheme::CELESTE)
        ->and(CertificateTheme::keyForPlan('NIVEL 4'))->toBe(CertificateTheme::OSCURO)
        ->and(CertificateTheme::keyForPlan('PLAN CORPORATIVO A LA MEDIDA'))->toBe(CertificateTheme::GRIS)
        ->and(CertificateDocumentData::shortPlanName('PLAN IDEAL'))->toBe('IDEAL');
});

it('pagina como la maqueta: certificado, relación y carnets de a cuatro con la guía al final', function (int $people, int $carnets, array $kinds): void {
    $rows = array_fill(0, 11, ['t' => 'Beneficio', 'cob' => 'Incluido', 'limited' => false]);

    $pages = CertificateDocumentData::paginate($rows, certificatePeople($people), certificatePeople($carnets), $people === 1, true);

    expect(array_column($pages, 'kind'))->toBe($kinds)
        ->and(end($pages)['hasInfo'] ?? false)->toBe($carnets > 0);
})->with([
    'individual con carnet' => [1, 1, ['cert', 'carnet']],
    'familia de 7 con todos los carnets' => [7, 7, ['cert', 'rel', 'carnet', 'carnet', 'carnet']],
    'familia sin carnets' => [3, 0, ['cert', 'rel']],
]);

it('la relación de afiliados se reparte de a 40 por página', function (): void {
    $pages = CertificateDocumentData::paginate([], certificatePeople(95), [], false, false);
    $relation = array_values(array_filter($pages, fn (array $page): bool => $page['kind'] === 'rel'));

    expect(array_map(fn (array $page): int => count($page['people']), $relation))->toBe([40, 40, 15])
        ->and($relation[2]['lastRel'])->toBeTrue()
        ->and($relation[0]['lastRel'])->toBeFalse();
});

it('los beneficios del plan Especial muestran el límite de emergencia de la cobertura', function (): void {
    $plan = Plan::query()->with('benefitPlans')->find(3);

    if ($plan === null) {
        $this->markTestSkipped('No existe el plan 3 en esta base.');
    }

    $benefits = CertificateBenefits::for($plan, 7, 20000.0);

    expect($benefits['hero']['label'])->toBe('Asistencia por emergencia')
        ->and($benefits['hero']['value'])->toBe('US$ 20.000')
        ->and($benefits['emergency'])->toBeTrue()
        ->and($benefits['carnet'])->toBe('Emergencia US$ 20.000')
        ->and(collect($benefits['rows'])->where('limited', true)->count())->toBe(1);
});

it('emite el certificado solo con carnets de afiliados vigentes de la afiliación', function (): void {
    $affiliation = Affiliation::query()->where('status', 'ACTIVA')->whereHas('affiliates', fn ($q) => $q->whereIn('status', IndividualCertificateDocument::PRINTABLE_STATUSES))->latest('id')->firstOrFail();
    $validIds = IndividualCertificateDocument::printableAffiliates($affiliation)->pluck('id')->all();

    $issue = app(AffiliationCertificateGeneratorService::class)->issueIndividual($affiliation, [...$validIds, 999999999], certificateSuperAdmin());

    expect($issue->exists)->toBeTrue()
        ->and($issue->verification_key)->toMatch('/^CER-/')
        ->and($issue->carnet_affiliate_ids)->toBe(array_map('intval', $validIds))
        ->and($issue->carnets_count)->toBe(count($validIds));

    $pdf = app(AffiliationCertificateGeneratorService::class)->renderPdf($issue);

    expect(str_starts_with($pdf, '%PDF'))->toBeTrue();
});

it('la verificación pública encuentra la emisión, rechaza claves inventadas y pide la clave si falta', function (): void {
    $affiliation = Affiliation::query()->where('status', 'ACTIVA')->latest('id')->firstOrFail();
    $issue = app(AffiliationCertificateGeneratorService::class)->issueIndividual($affiliation, [], certificateSuperAdmin());

    $this->get(route('affiliation-certificate.verify', ['key' => strtolower($issue->verification_key)]))
        ->assertSuccessful()
        ->assertSee($issue->affiliation_code)
        ->assertSee('Estamos contigo, 24/7')
        ->assertSee('<title>Carnet digital · Tu Dr en Casa</title>', false)
        ->assertSee('Telemedicina y Atención Médica Domiciliaria (AMD)')
        ->assertSee('Coordinación de servicios derivados')
        ->assertDontSee('Plan a la medida');

    $this->get(route('affiliation-certificate.verify', ['key' => CertificateVerificationKey::generate()]))
        ->assertSuccessful()
        ->assertSee('No encontramos este certificado');

    $this->get(route('affiliation-certificate.verify'))
        ->assertSuccessful()
        ->assertSee('Verifica un certificado');
});

it('el QR de un certificado corporativo abre el carnet a la medida, solo con MediChat', function (): void {
    $affiliation = App\Models\AffiliationCorporate::query()->where('status', 'ACTIVA')->latest('id')->firstOrFail();
    $issue = app(AffiliationCertificateGeneratorService::class)->issueCorporate($affiliation, [], certificateSuperAdmin());

    $this->get(route('affiliation-certificate.verify', ['key' => $issue->verification_key]))
        ->assertSuccessful()
        ->assertSee($issue->affiliation_code)
        ->assertSee('Contratante')
        ->assertSee('<title>Carnet digital · Plan a la medida · Tu Dr en Casa</title>', false)
        ->assertSee('Tu plan a la medida: un médico para ti, en casa o en la oficina.')
        ->assertSee('carnet-digital-portada-a-la-medida.jpg', false)
        ->assertSee('MediChat')
        ->assertDontSee('Telemedicina y Atención Médica Domiciliaria (AMD)')
        ->assertDontSee('Coordinación de servicios derivados');
});

it('el PDF exige sesión y permiso sobre afiliaciones de Negocios', function (): void {
    $affiliation = Affiliation::query()->where('status', 'ACTIVA')->latest('id')->firstOrFail();
    $issue = app(AffiliationCertificateGeneratorService::class)->issueIndividual($affiliation, [], certificateSuperAdmin());
    $url = route('business.affiliation-certificate.pdf', ['issue' => $issue->id]);

    $this->get($url)->assertRedirect();

    $this->actingAs(User::factory()->make(['departament' => [], 'is_superAdmin' => 0]))
        ->get($url)
        ->assertForbidden();

    $this->actingAs(certificateSuperAdmin())
        ->get($url.'?descargar=1')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.AffiliationCertificateGeneratorService::fileName($issue).'"');
});

it('la página elige la afiliación, marca todos los carnets y genera la vista previa', function (): void {
    Filament::setCurrentPanel('business');
    $this->actingAs(certificateSuperAdmin());

    $affiliation = Affiliation::query()->where('status', 'ACTIVA')->whereHas('affiliates', fn ($q) => $q->whereIn('status', IndividualCertificateDocument::PRINTABLE_STATUSES))->latest('id')->firstOrFail();
    $expected = CertificateGenerator::printableIds($affiliation);

    $page = Livewire::test(CertificateGenerator::class)
        ->assertSuccessful()
        ->set('data.affiliation_id', $affiliation->id)
        ->assertSet('data.carnets', $expected)
        ->call('generate')
        ->assertNotified('Certificado generado');

    $issueId = $page->get('issueId');

    expect($issueId)->not->toBeNull()
        ->and(AffiliationCertificateIssue::query()->find($issueId)?->affiliation_id)->toBe($affiliation->id);

    $page->assertSee('Vista previa')->assertSee('Descargar PDF');
});

it('la tabla de afiliaciones individuales ya no tiene el botón en el encabezado', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($table)
        ->not->toContain('->headerActions(')
        ->not->toContain("Action::make('certificateGenerator')");

    expect(CertificateGenerator::searchAffiliations('a'))->toBe([]);
});

describe('corporativas', function (): void {
    it('con varios planes titula «CORPORATIVO», separa los beneficios por plan y muestra el plan en la relación', function (): void {
        $affiliation = App\Models\AffiliationCorporate::query()->where('code', 'TDEC-COR-0001')->first();

        if ($affiliation === null) {
            $this->markTestSkipped('No existe TDEC-COR-0001 en esta base.');
        }

        $issue = new AffiliationCertificateIssue(['verification_key' => CertificateVerificationKey::generate()]);
        $data = App\Support\Affiliations\Certificates\CorporateCertificateDocument::build($affiliation, $issue, []);

        expect($data['planNombre'])->toBe('CORPORATIVO')
            ->and($data['relationPlanColumn'])->toBeTrue()
            ->and($data['heroLabel'])->toBe('Planes contratados')
            ->and(collect($data['pages'][0]['rows'])->filter(fn (array $row): bool => isset($row['section']))->count())->toBeGreaterThan(1)
            ->and($data['contratanteId'])->toStartWith('RIF ');
    });

    it('no repite los apellidos cuando el nombre ya los trae', function (): void {
        $duplicated = new App\Models\AffiliateCorporate(['first_name' => 'AZUAJE RODRIGUEZ DIEGO ALESSANDRO', 'last_name' => 'AZUAJE RODRIGUEZ']);
        $split = new App\Models\AffiliateCorporate(['first_name' => 'DIEGO', 'last_name' => 'AZUAJE']);

        expect(App\Support\Affiliations\Certificates\CorporateCertificateDocument::fullName($duplicated))->toBe('Azuaje Rodriguez Diego Alessandro')
            ->and(App\Support\Affiliations\Certificates\CorporateCertificateDocument::fullName($split))->toBe('Diego Azuaje')
            ->and(App\Support\Affiliations\Certificates\CertificateFormat::short(str_repeat('Nombre ', 10)))->toEndWith('…');
    });

    it('estima páginas y manda a cola solo los documentos grandes', function (): void {
        expect(AffiliationCertificateGeneratorService::estimatedPages(1, 1))->toBe(2)
            ->and(AffiliationCertificateGeneratorService::estimatedPages(2646, 2646))->toBe(730)
            ->and(AffiliationCertificateGeneratorService::estimatedPages(40, 0))->toBe(2);
    });

    it('un colectivo grande se emite en cola por tramos cortos, con solo afiliados vigentes', function (): void {
        Illuminate\Support\Facades\Storage::fake('local');
        Illuminate\Support\Facades\Bus::fake();

        $affiliation = App\Models\AffiliationCorporate::query()->where('code', 'TDEC-COR-00054')->first();

        if ($affiliation === null) {
            $this->markTestSkipped('No existe TDEC-COR-00054 en esta base.');
        }

        $ids = App\Support\Affiliations\Certificates\CorporateCertificateDocument::printableAffiliates($affiliation)->pluck('id')->all();
        $issue = app(AffiliationCertificateGeneratorService::class)->issueCorporate($affiliation, [...$ids, 999999999], certificateSuperAdmin());
        $expectedChunks = (int) ceil($issue->pdf_pages / AffiliationCertificateGeneratorService::CHUNK_PAGES);

        expect($issue->pdf_status)->toBe(AffiliationCertificateIssue::PDF_PROCESSING)
            ->and($issue->isQueued())->toBeTrue()
            ->and($issue->carnets_count)->toBe(count($ids))
            ->and($issue->pdf_chunks_total)->toBe($expectedChunks)
            ->and($issue->pdf_chunks_done)->toBe(0)
            ->and($issue->progressPercent())->toBe(0)
            ->and(Illuminate\Support\Facades\Storage::disk('local')->exists(AffiliationCertificateGeneratorService::chunkDirectory($issue).'/datos.ser'))->toBeTrue();

        Illuminate\Support\Facades\Bus::assertBatched(fn (Illuminate\Bus\PendingBatch $batch): bool => $batch->jobs->count() === $expectedChunks
            && $batch->jobs->every(fn (mixed $job): bool => $job instanceof App\Jobs\RenderAffiliationCertificateChunkJob)
            && $batch->queue() === AffiliationCertificateGeneratorService::queue());
    });

    it('los tramos se dibujan, se unen en el PDF final, se marca listo y se avisa al analista', function (): void {
        Illuminate\Support\Facades\Storage::fake('local');
        Illuminate\Support\Facades\Notification::fake();
        Illuminate\Support\Facades\Bus::fake();

        $affiliation = App\Models\AffiliationCorporate::query()->whereIn('status', ['ACTIVA'])->where('code', '!=', 'TDEC-COR-00054')->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $generator = app(AffiliationCertificateGeneratorService::class);
        $issue = $generator->issueCorporate($affiliation, [], certificateSuperAdmin());
        $issue->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING])->save();

        // El job antiguo de un solo paso, si quedó en la cola, ahora delega en los tramos.
        (new App\Jobs\GenerateAffiliationCertificatePdfJob($issue->id))->handle($generator);
        $issue->refresh();

        expect($issue->pdf_chunks_total)->toBeGreaterThanOrEqual(1);

        for ($index = 0; $index < $issue->pdf_chunks_total; $index++) {
            (new App\Jobs\RenderAffiliationCertificateChunkJob($issue->id, $index))->handle($generator);
            // Idempotente: repetir un tramo no lo vuelve a contar.
            (new App\Jobs\RenderAffiliationCertificateChunkJob($issue->id, $index))->handle($generator);
        }

        expect($issue->fresh()->pdf_chunks_done)->toBe($issue->pdf_chunks_total);

        (new App\Jobs\MergeAffiliationCertificateChunksJob($issue->id))->handle($generator);
        $issue->refresh();
        $disk = Illuminate\Support\Facades\Storage::disk('local');

        expect($issue->pdf_status)->toBe(AffiliationCertificateIssue::PDF_READY)
            ->and($issue->progressPercent())->toBe(100)
            ->and($disk->exists($issue->pdf_path))->toBeTrue()
            ->and(str_starts_with((string) $disk->get($issue->pdf_path), '%PDF'))->toBeTrue()
            ->and($disk->exists(AffiliationCertificateGeneratorService::chunkDirectory($issue)))->toBeFalse();

        Illuminate\Support\Facades\Notification::assertSentTo(
            certificateSuperAdmin(),
            \Filament\Notifications\DatabaseNotification::class,
            fn ($notification): bool => ($notification->data['title'] ?? null) === 'Certificado listo',
        );

        // Unir de nuevo no vuelve a avisar.
        Illuminate\Support\Facades\Notification::fake();
        (new App\Jobs\MergeAffiliationCertificateChunksJob($issue->id))->handle($generator);
        Illuminate\Support\Facades\Notification::assertNothingSent();

        $this->actingAs(certificateSuperAdmin())
            ->get(route('business.affiliation-certificate.pdf', ['issue' => $issue->id]))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    });

    it('un fallo definitivo marca el certificado y reintentar solo encola los tramos que faltan', function (): void {
        Illuminate\Support\Facades\Storage::fake('local');
        Illuminate\Support\Facades\Notification::fake();
        Illuminate\Support\Facades\Bus::fake();

        $affiliation = App\Models\AffiliationCorporate::query()->where('code', 'TDEC-COR-00054')->first();

        if ($affiliation === null) {
            $this->markTestSkipped('No existe TDEC-COR-00054 en esta base.');
        }

        $generator = app(AffiliationCertificateGeneratorService::class);
        $ids = App\Support\Affiliations\Certificates\CorporateCertificateDocument::printableAffiliates($affiliation)->pluck('id')->all();
        $issue = $generator->issueCorporate($affiliation, $ids, certificateSuperAdmin());

        // Dos tramos ya listos (archivos simulados) y una falla.
        Illuminate\Support\Facades\Storage::disk('local')->put(AffiliationCertificateGeneratorService::chunkFile($issue, 0), '%PDF-1.4');
        Illuminate\Support\Facades\Storage::disk('local')->put(AffiliationCertificateGeneratorService::chunkFile($issue, 1), '%PDF-1.4');
        AffiliationCertificateGeneratorService::markFailed($issue->id, new RuntimeException('worker reiniciado'));

        expect($issue->fresh()->pdf_status)->toBe(AffiliationCertificateIssue::PDF_FAILED)
            ->and($issue->fresh()->pdf_error)->toBe('worker reiniciado');

        Illuminate\Support\Facades\Bus::fake();
        $generator->queueRender($issue->fresh());

        expect($issue->fresh()->pdf_status)->toBe(AffiliationCertificateIssue::PDF_PROCESSING)
            ->and($issue->fresh()->pdf_chunks_done)->toBe(2);

        Illuminate\Support\Facades\Bus::assertBatched(fn (Illuminate\Bus\PendingBatch $batch): bool => $batch->jobs->count() === $issue->fresh()->pdf_chunks_total - 2
            && $batch->jobs->every(fn (App\Jobs\RenderAffiliationCertificateChunkJob $job): bool => $job->chunkIndex >= 2));
    });

    it('un certificado en cola sin avance por más de diez minutos se marca como detenido', function (): void {
        $issue = new AffiliationCertificateIssue(['pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING, 'pdf_chunks_total' => 13, 'pdf_chunks_done' => 6]);
        $issue->pdf_progress_at = now()->subMinutes(11);

        expect($issue->isStalled())->toBeTrue()
            ->and($issue->progressPercent())->toBe(43);

        $issue->pdf_progress_at = now()->subMinutes(2);
        expect($issue->isStalled())->toBeFalse();

        $issue->pdf_status = AffiliationCertificateIssue::PDF_READY;
        expect($issue->isStalled())->toBeFalse()->and($issue->progressPercent())->toBe(100);
    });

    it('un certificado pequeño se dibuja una vez y la descarga reutiliza el mismo archivo', function (): void {
        Illuminate\Support\Facades\Storage::fake('local');

        $affiliation = App\Models\AffiliationCorporate::query()->whereIn('status', ['ACTIVA'])->where('code', '!=', 'TDEC-COR-00054')->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $generator = app(AffiliationCertificateGeneratorService::class);
        $issue = $generator->issueCorporate($affiliation, [], certificateSuperAdmin());

        expect($issue->isQueued())->toBeFalse();

        $first = $generator->pdfFile($issue);
        $issue->refresh();
        $modified = filemtime($first);
        $second = $generator->pdfFile($issue);

        expect($second)->toBe($first)
            ->and(filemtime($second))->toBe($modified)
            ->and($issue->pdf_path)->not->toBeNull();
    });

    it('un PDF en preparación responde 409 sin romper la vista previa', function (): void {
        $affiliation = App\Models\AffiliationCorporate::query()->whereIn('status', ['ACTIVA'])->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $issue = app(AffiliationCertificateGeneratorService::class)->issueCorporate($affiliation, [], certificateSuperAdmin());
        $issue->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING])->save();

        $this->actingAs(certificateSuperAdmin())
            ->get(route('business.affiliation-certificate.pdf', ['issue' => $issue->id]))
            ->assertStatus(409)
            ->assertSee('se está generando');
    });

    it('un documento de más de 80 páginas se dibuja por tramos y conserva todas las páginas', function (): void {
        $affiliation = App\Models\AffiliationCorporate::query()->where('code', 'TDEC-COR-00054')->first();

        if ($affiliation === null) {
            $this->markTestSkipped('No existe TDEC-COR-00054 en esta base.');
        }

        $issue = new AffiliationCertificateIssue([
            'verification_key' => CertificateVerificationKey::generate(),
            'affiliation_type' => AffiliationCertificateIssue::TYPE_CORPORATE,
            'affiliation_id' => $affiliation->id,
            'carnet_affiliate_ids' => App\Support\Affiliations\Certificates\CorporateCertificateDocument::printableAffiliates($affiliation)->pluck('id')->take(60)->all(),
        ]);
        $issue->created_at = now();

        $pdf = app(AffiliationCertificateGeneratorService::class)->renderPdf($issue, fromStorage: false);
        $expected = count(app(AffiliationCertificateGeneratorService::class)->documentData($issue)['pages']);

        expect($expected)->toBeGreaterThan(AffiliationCertificateGeneratorService::CHUNK_PAGES)
            ->and(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBe($expected);
    });

    it('la página corporativa genera con todos los carnets o exige elegir al menos uno', function (): void {
        Filament::setCurrentPanel('business');
        $this->actingAs(certificateSuperAdmin());

        $affiliation = App\Models\AffiliationCorporate::query()->where('status', 'ACTIVA')->where('code', '!=', 'TDEC-COR-00054')->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $page = App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporateCertificateGenerator::class;

        Livewire::test($page)
            ->set('data.affiliation_id', $affiliation->id)
            ->set('data.carnet_mode', 'pick')
            ->call('generate')
            ->assertHasErrors(['data.carnets' => 'required']);

        $test = Livewire::test($page)
            ->set('data.affiliation_id', $affiliation->id)
            ->assertSet('data.carnet_mode', 'all')
            ->call('generate')
            ->assertHasNoErrors();

        $issue = AffiliationCertificateIssue::query()->find($test->get('issueId'));

        expect($issue?->affiliation_type)->toBe(AffiliationCertificateIssue::TYPE_CORPORATE)
            ->and($issue?->carnets_count)->toBe(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporateCertificateGenerator::printableCount($affiliation->id));
    });

    it('la página no lanza un segundo certificado si ya hay uno de esa afiliación generándose', function (): void {
        Illuminate\Support\Facades\Bus::fake();
        Filament::setCurrentPanel('business');
        $this->actingAs(certificateSuperAdmin());

        $affiliation = App\Models\AffiliationCorporate::query()->where('status', 'ACTIVA')->where('code', '!=', 'TDEC-COR-00054')->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $inFlight = app(AffiliationCertificateGeneratorService::class)->issueCorporate($affiliation, [], certificateSuperAdmin());
        $inFlight->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING, 'pdf_chunks_total' => 4, 'pdf_chunks_done' => 1, 'pdf_progress_at' => now()])->save();
        $before = AffiliationCertificateIssue::query()->count();

        Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporateCertificateGenerator::class)
            ->set('data.affiliation_id', $affiliation->id)
            ->call('generate')
            ->assertSet('issueId', $inFlight->id)
            ->assertNotified('Ya se está generando un certificado de esta afiliación')
            ->assertSee('Parte 2 de 4')
            ->assertSee('23 %');

        expect(AffiliationCertificateIssue::query()->count())->toBe($before);
    });

    it('reintentar desde la página retoma un certificado fallido', function (): void {
        Illuminate\Support\Facades\Bus::fake();
        Filament::setCurrentPanel('business');
        $this->actingAs(certificateSuperAdmin());

        $affiliation = App\Models\AffiliationCorporate::query()->where('status', 'ACTIVA')->where('code', '!=', 'TDEC-COR-00054')->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $issue = app(AffiliationCertificateGeneratorService::class)->issueCorporate($affiliation, [], certificateSuperAdmin());
        $issue->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_FAILED, 'pdf_error' => 'worker reiniciado'])->save();

        Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporateCertificateGenerator::class)
            ->set('issueId', $issue->id)
            ->assertSee('No se pudo generar el certificado')
            ->assertSee('Reintentar')
            ->call('retry')
            ->assertNotified('Reanudando el certificado');

        expect($issue->fresh()->pdf_status)->toBe(AffiliationCertificateIssue::PDF_PROCESSING);
        Illuminate\Support\Facades\Bus::assertBatched(fn (Illuminate\Bus\PendingBatch $batch): bool => $batch->jobs->isNotEmpty());
    });

    it('un certificado grande listo muestra la vista previa liviana y ofrece el PDF completo', function (): void {
        Filament::setCurrentPanel('business');
        $this->actingAs(certificateSuperAdmin());

        $affiliation = App\Models\AffiliationCorporate::query()->where('status', 'ACTIVA')->where('code', '!=', 'TDEC-COR-00054')->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $issue = app(AffiliationCertificateGeneratorService::class)->issueCorporate($affiliation, [], certificateSuperAdmin());
        Illuminate\Support\Facades\Storage::disk('local')->put('certificados-generador/grande.pdf', '%PDF-1.4');
        $issue->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_READY, 'pdf_path' => 'certificados-generador/grande.pdf', 'pdf_pages' => 730, 'carnets_count' => 2646])->save();

        Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporateCertificateGenerator::class)
            ->set('issueId', $issue->id)
            ->assertSee('Certificado listo')
            ->assertSee('730 páginas')
            ->assertSee('Descargar PDF')
            ->assertSee('La vista previa muestra la portada y la primera hoja de carnets')
            ->assertSeeHtml('vista=previa');
    });

    it('el comando de soporte genera desde la consola un certificado que la cola no procesó', function (): void {
        Illuminate\Support\Facades\Bus::fake();
        Illuminate\Support\Facades\Notification::fake();

        $affiliation = App\Models\AffiliationCorporate::query()->where('status', 'ACTIVA')->where('code', '!=', 'TDEC-COR-00054')->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $issue = app(AffiliationCertificateGeneratorService::class)->issueCorporate($affiliation, [], certificateSuperAdmin());
        $issue->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING])->save();

        $this->artisan('certificates:render', ['issue' => $issue->verification_key])->assertSuccessful();

        expect($issue->fresh()->pdf_status)->toBe(AffiliationCertificateIssue::PDF_READY);
        Illuminate\Support\Facades\Bus::assertNothingBatched();

        $this->artisan('certificates:render', ['issue' => 'CER-NO-EXISTE'])->assertFailed();
    });

    it('la vista previa liviana toma la portada y la primera hoja de carnets, con su número real', function (): void {
        $pages = [
            ['num' => 1, 'kind' => 'cert'],
            ['num' => 2, 'kind' => 'rel'],
            ['num' => 3, 'kind' => 'rel'],
            ['num' => 4, 'kind' => 'carnet'],
            ['num' => 5, 'kind' => 'carnet'],
        ];

        expect(array_column(AffiliationCertificateGeneratorService::previewData(['pages' => $pages, 'totalPaginas' => 5])['pages'], 'num'))->toBe([1, 4])
            // Sin carnets: la portada y la primera hoja de la relación.
            ->and(array_column(AffiliationCertificateGeneratorService::previewData(['pages' => array_slice($pages, 0, 3)])['pages'], 'num'))->toBe([1, 2])
            ->and(array_column(AffiliationCertificateGeneratorService::previewData(['pages' => [$pages[0]]])['pages'], 'num'))->toBe([1])
            // El total no cambia: las páginas siguen diciendo «de 5».
            ->and(AffiliationCertificateGeneratorService::previewData(['pages' => $pages, 'totalPaginas' => 5])['totalPaginas'])->toBe(5);
    });

    it('un certificado grande trae su vista previa de dos páginas desde la emisión, antes de terminar', function (): void {
        Illuminate\Support\Facades\Bus::fake();

        $affiliation = App\Models\AffiliationCorporate::query()->where('code', 'TDEC-COR-00054')->first();

        if ($affiliation === null) {
            $this->markTestSkipped('No existe TDEC-COR-00054 en esta base.');
        }

        $generator = app(AffiliationCertificateGeneratorService::class);
        $issue = $generator->issueCorporate($affiliation, App\Support\Affiliations\Certificates\CorporateCertificateDocument::printableAffiliates($affiliation)->pluck('id')->all(), certificateSuperAdmin());

        expect($issue->pdf_status)->toBe(AffiliationCertificateIssue::PDF_PROCESSING)
            ->and(Illuminate\Support\Facades\Storage::disk('local')->exists(AffiliationCertificateGeneratorService::previewFileName($issue)))->toBeTrue()
            ->and((new setasign\Fpdi\Fpdi)->setSourceFile($generator->previewFile($issue)))->toBe(2);

        $this->actingAs(certificateSuperAdmin())
            ->get(route('business.affiliation-certificate.pdf', ['issue' => $issue->id, 'vista' => 'previa']))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');

        // El completo sigue en preparación.
        $this->actingAs(certificateSuperAdmin())
            ->get(route('business.affiliation-certificate.pdf', ['issue' => $issue->id]))
            ->assertStatus(409);
    });

    it('un certificado corto se previsualiza completo, con el mismo archivo que se descarga', function (): void {
        $affiliation = App\Models\AffiliationCorporate::query()->whereIn('status', ['ACTIVA'])->where('code', '!=', 'TDEC-COR-00054')->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $generator = app(AffiliationCertificateGeneratorService::class);
        $issue = $generator->issueCorporate($affiliation, [], certificateSuperAdmin());

        if (count($generator->documentData($issue)['pages']) > AffiliationCertificateGeneratorService::FULL_PREVIEW_MAX_PAGES) {
            $this->markTestSkipped('La primera afiliación activa no es corta.');
        }

        expect($generator->previewFile($issue))->toBe($generator->pdfFile($issue->fresh()));
    });

    it('mientras se genera, la página muestra la vista previa liviana debajo del avance', function (): void {
        Filament::setCurrentPanel('business');
        $this->actingAs(certificateSuperAdmin());

        $affiliation = App\Models\AffiliationCorporate::query()->where('status', 'ACTIVA')->where('code', '!=', 'TDEC-COR-00054')->whereHas('corporateAffiliates', fn ($q) => $q->whereIn('status', ['ACTIVO', 'PRE-APROBADA']))->firstOrFail();
        $issue = app(AffiliationCertificateGeneratorService::class)->issueCorporate($affiliation, [], certificateSuperAdmin());
        $issue->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING, 'pdf_chunks_total' => 4, 'pdf_chunks_done' => 0, 'pdf_progress_at' => now()])->save();

        Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporateCertificateGenerator::class)
            ->set('issueId', $issue->id)
            ->assertSee('Generando el certificado')
            ->assertSee('Portada y primera hoja de carnets')
            ->assertSeeHtml('vista=previa');
    });

    it('la tabla corporativa ya no tiene el botón en el encabezado', function (): void {
        expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php'))
            ->not->toContain('->headerActions(')
            ->not->toContain("Action::make('certificateGenerator')");
    });
});
