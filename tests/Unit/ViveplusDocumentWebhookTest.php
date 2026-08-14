<?php

declare(strict_types=1);

use App\Enums\ViveplusAffiliationType;
use App\Enums\ViveplusDocumentType;
use App\Exceptions\ViveplusDocumentWebhookPermanentException;
use App\Exceptions\ViveplusDocumentWebhookTransientException;
use App\Jobs\PushAffiliationDocumentToViveplusJob;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\User;
use App\Support\Viveplus\ViveplusDocumentWebhookAnalystNotifier;
use App\Support\Viveplus\ViveplusDocumentWebhookClient;
use App\Support\Viveplus\ViveplusDocumentWebhookDispatcher;
use App\Support\Viveplus\ViveplusDocumentWebhookPayload;
use App\Support\Viveplus\ViveplusDocumentWebhookSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'services.viveplus_documents.webhook_url' => 'https://vivepluss.com/api/documents/webhook',
        'services.viveplus_documents.token' => 'test-token',
        'services.viveplus_documents.signing_secret' => 'test-secret',
        'services.viveplus_documents.timeout' => 15,
        'services.viveplus_documents.max_file_bytes' => 10485760,
        'services.viveplus_documents.later_retry_delay_seconds' => 120,
        'services.viveplus_documents.max_later_retries' => 5,
    ]);

    $this->pdfPath = sys_get_temp_dir().'/viveplus-doc-'.uniqid('', true).'.pdf';
    file_put_contents($this->pdfPath, "%PDF-1.4\nwebhook-fixture\n");
});

afterEach(function (): void {
    if (isset($this->pdfPath) && is_file($this->pdfPath)) {
        unlink($this->pdfPath);
    }
});

function viveplusWebhookPayload(
    string $path,
    string $affiliationType = 'individual',
    string $documentType = 'certificado',
    string $affiliationCode = 'TDEC-IND-0001',
    string $generatedAt = '2026-08-13T20:00:00+00:00',
    string $idempotencyKey = 'uuid-xyz',
    string $affiliateIdentification = '',
): ViveplusDocumentWebhookPayload {
    return new ViveplusDocumentWebhookPayload(
        affiliationType: ViveplusAffiliationType::from($affiliationType),
        affiliationCode: $affiliationCode,
        documentType: ViveplusDocumentType::from($documentType),
        absolutePath: $path,
        generatedAt: $generatedAt,
        idempotencyKey: $idempotencyKey,
        affiliateIdentification: $affiliateIdentification,
    );
}

function viveplusExpectedSignature(string $path, ViveplusDocumentWebhookPayload $payload): string
{
    $checksum = hash_file('sha256', $path);
    $canonical = ViveplusDocumentWebhookSigner::canonicalString($payload->fields($checksum));

    return ViveplusDocumentWebhookSigner::signature($canonical, 'test-secret');
}

function viveplusMultipartField(Request $request, string $name): ?string
{
    foreach ($request->data() as $part) {
        if (is_array($part) && ($part['name'] ?? null) === $name) {
            return (string) ($part['contents'] ?? '');
        }
    }

    return null;
}

it('construye la cadena canónica del certificado con affiliate_identification vacío', function (): void {
    $canonical = ViveplusDocumentWebhookSigner::canonicalString([
        'affiliation_type' => 'individual',
        'affiliation_code' => 'TDEC-IND-000226',
        'document_type' => 'certificado',
        'affiliate_identification' => '',
        'checksum_sha256' => 'abc123',
        'generated_at' => '2026-08-13T21:00:00+00:00',
        'idempotency_key' => 'uuid-xyz',
    ]);

    expect($canonical)->toBe(
        'affiliation_type=individual&affiliation_code=TDEC-IND-000226&document_type=certificado&affiliate_identification=&checksum_sha256=abc123&generated_at=2026-08-13T21:00:00+00:00&idempotency_key=uuid-xyz'
    );
});

it('construye la cadena canónica del carnet con affiliate_identification', function (): void {
    $canonical = ViveplusDocumentWebhookSigner::canonicalString([
        'affiliation_type' => 'individual',
        'affiliation_code' => 'TDEC-IND-000226',
        'document_type' => 'carnet',
        'affiliate_identification' => '13991020',
        'checksum_sha256' => 'abc123',
        'generated_at' => '2026-08-13T21:00:00+00:00',
        'idempotency_key' => 'uuid-xyz',
    ]);

    expect($canonical)->toBe(
        'affiliation_type=individual&affiliation_code=TDEC-IND-000226&document_type=carnet&affiliate_identification=13991020&checksum_sha256=abc123&generated_at=2026-08-13T21:00:00+00:00&idempotency_key=uuid-xyz'
    );
});

it('entrega con éxito un certificado individual y firma el payload canónico', function (): void {
    $payload = viveplusWebhookPayload($this->pdfPath);
    $checksum = hash_file('sha256', $this->pdfPath);
    $signature = viveplusExpectedSignature($this->pdfPath, $payload);

    Http::fake([
        'https://vivepluss.com/api/documents/webhook' => Http::response(['ok' => true], 201),
    ]);

    $result = app(ViveplusDocumentWebhookClient::class)->send($payload);

    expect($result->accepted)->toBeTrue()
        ->and($result->status)->toBe(201);

    Http::assertSent(function (Request $request) use ($payload, $checksum, $signature): bool {
        return $request->url() === 'https://vivepluss.com/api/documents/webhook'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('X-Signature', $signature)
            && viveplusMultipartField($request, 'affiliation_type') === 'individual'
            && viveplusMultipartField($request, 'affiliation_code') === 'TDEC-IND-0001'
            && viveplusMultipartField($request, 'document_type') === 'certificado'
            && viveplusMultipartField($request, 'affiliate_identification') === ''
            && viveplusMultipartField($request, 'checksum_sha256') === $checksum
            && viveplusMultipartField($request, 'generated_at') === $payload->generatedAt
            && viveplusMultipartField($request, 'idempotency_key') === $payload->idempotencyKey
            && $request->hasFile('file');
    });
});

it('entrega con éxito un carnet individual con affiliate_identification y firma canónica', function (): void {
    $payload = viveplusWebhookPayload(
        $this->pdfPath,
        documentType: 'carnet',
        affiliationCode: 'TDEC-IND-000226',
        generatedAt: '2026-08-13T21:00:00+00:00',
        affiliateIdentification: '13991020',
    );
    $checksum = hash_file('sha256', $this->pdfPath);
    $signature = viveplusExpectedSignature($this->pdfPath, $payload);

    Http::fake([
        'https://vivepluss.com/api/documents/webhook' => Http::response(['ok' => true], 201),
    ]);

    $result = app(ViveplusDocumentWebhookClient::class)->send($payload);

    expect($result->accepted)->toBeTrue()
        ->and($result->status)->toBe(201);

    Http::assertSent(function (Request $request) use ($payload, $checksum, $signature): bool {
        return $request->hasHeader('X-Signature', $signature)
            && viveplusMultipartField($request, 'document_type') === 'carnet'
            && viveplusMultipartField($request, 'affiliate_identification') === '13991020'
            && viveplusMultipartField($request, 'checksum_sha256') === $checksum
            && viveplusMultipartField($request, 'affiliation_code') === $payload->affiliationCode;
    });
});

it('entrega con éxito un carnet corporativo con affiliate_identification', function (): void {
    $payload = viveplusWebhookPayload(
        $this->pdfPath,
        affiliationType: 'corporate',
        documentType: 'carnet',
        affiliationCode: 'TDEC-COR-0001',
        affiliateIdentification: 'V-99887766',
    );

    Http::fake([
        'https://vivepluss.com/api/documents/webhook' => Http::response(['ok' => true], 201),
    ]);

    $result = app(ViveplusDocumentWebhookClient::class)->send($payload);

    expect($result->accepted)->toBeTrue()
        ->and($result->status)->toBe(201);

    Http::assertSent(fn (Request $request): bool => viveplusMultipartField($request, 'affiliation_type') === 'corporate'
        && viveplusMultipartField($request, 'affiliation_code') === 'TDEC-COR-0001'
        && viveplusMultipartField($request, 'document_type') === 'carnet'
        && viveplusMultipartField($request, 'affiliate_identification') === 'V-99887766');
});

it('rechaza un carnet sin affiliate_identification', function (): void {
    expect(fn () => app(ViveplusDocumentWebhookClient::class)->send(
        viveplusWebhookPayload($this->pdfPath, documentType: 'carnet')
    ))->toThrow(function (ViveplusDocumentWebhookPermanentException $exception): void {
        expect($exception->statusCode)->toBe(422);
    });
});

it('trata 409 de duplicado como éxito y no lanza', function (): void {
    Http::fake([
        'https://vivepluss.com/api/documents/webhook' => Http::response([
            'message' => 'Already processed',
        ], 409),
    ]);

    $result = app(ViveplusDocumentWebhookClient::class)->send(viveplusWebhookPayload($this->pdfPath));

    expect($result->accepted)->toBeTrue()
        ->and($result->isDuplicateOrStale())->toBeTrue()
        ->and($result->status)->toBe(409);
});

it('trata 409 de versión desactualizada como éxito y no lanza', function (): void {
    Http::fake([
        'https://vivepluss.com/api/documents/webhook' => Http::response([
            'message' => 'A newer version already exists',
        ], 409),
    ]);

    $result = app(ViveplusDocumentWebhookClient::class)->send(viveplusWebhookPayload($this->pdfPath));

    expect($result->accepted)->toBeTrue()
        ->and($result->isDuplicateOrStale())->toBeTrue();
});

it('no reintenta un 401 y lo marca como error permanente', function (): void {
    Http::fake([
        'https://vivepluss.com/api/documents/webhook' => Http::response([
            'message' => 'Unauthorized',
        ], 401),
    ]);

    $job = (new PushAffiliationDocumentToViveplusJob(
        affiliationType: 'individual',
        affiliationCode: 'TDEC-IND-WEBHOOK-UNIT-TEST',
        documentType: 'certificado',
        absolutePath: $this->pdfPath,
        generatedAt: '2026-08-13T20:00:00+00:00',
        idempotencyKey: 'uuid-401',
    ))->withFakeQueueInteractions();

    try {
        $job->handle(app(ViveplusDocumentWebhookClient::class));
    } catch (ViveplusDocumentWebhookPermanentException) {
        // fail() relanza la excepción permanente para no reintentar
    }

    $job->assertFailed();
});

it('no reintenta un 422 y registra el detalle de errors', function (): void {
    Http::fake([
        'https://vivepluss.com/api/documents/webhook' => Http::response([
            'errors' => [
                'checksum_sha256' => ['does not match the uploaded file'],
                'affiliation_code' => ['not found for affiliation_type'],
            ],
        ], 422),
    ]);

    expect(fn () => app(ViveplusDocumentWebhookClient::class)->send(viveplusWebhookPayload($this->pdfPath)))
        ->toThrow(function (ViveplusDocumentWebhookPermanentException $exception): void {
            expect($exception->statusCode)->toBe(422)
                ->and($exception->errors)->toMatchArray([
                    'checksum_sha256' => ['does not match the uploaded file'],
                    'affiliation_code' => ['not found for affiliation_type'],
                ]);
        });
});

it('convierte timeout o error de red en error transitorio', function (): void {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    expect(fn () => app(ViveplusDocumentWebhookClient::class)->send(viveplusWebhookPayload($this->pdfPath)))
        ->toThrow(ViveplusDocumentWebhookTransientException::class);
});

it('reintenta un 5xx como error transitorio', function (): void {
    Http::fake([
        'https://vivepluss.com/api/documents/webhook' => Http::response(['message' => 'unavailable'], 503),
    ]);

    expect(fn () => app(ViveplusDocumentWebhookClient::class)->send(viveplusWebhookPayload($this->pdfPath)))
        ->toThrow(function (ViveplusDocumentWebhookTransientException $exception): void {
            expect($exception->statusCode)->toBe(503);
        });
});

it('reutiliza la misma idempotency_key entre reintentos de la misma ejecución', function (): void {
    Http::fake([
        'https://vivepluss.com/api/documents/webhook' => Http::sequence()
            ->push(['message' => 'unavailable'], 503)
            ->push(['ok' => true], 201),
    ]);

    $job = new PushAffiliationDocumentToViveplusJob(
        affiliationType: 'individual',
        affiliationCode: 'TDEC-IND-WEBHOOK-UNIT-TEST',
        documentType: 'certificado',
        absolutePath: $this->pdfPath,
        generatedAt: '2026-08-13T20:00:00+00:00',
        idempotencyKey: 'stable-execution-key',
    );

    try {
        $job->handle(app(ViveplusDocumentWebhookClient::class));
    } catch (ViveplusDocumentWebhookTransientException) {
        // primer intento transitorio
    }

    $job->handle(app(ViveplusDocumentWebhookClient::class));

    $keys = [];
    Http::assertSent(function (Request $request) use (&$keys): bool {
        $keys[] = viveplusMultipartField($request, 'idempotency_key');

        return true;
    });

    expect($keys)->toBe(['stable-execution-key', 'stable-execution-key']);
});

it('encola un reintento posterior con la misma idempotency_key tras agotar los intentos transitorios', function (): void {
    Bus::fake();

    $this->mock(ViveplusDocumentWebhookAnalystNotifier::class, function ($mock): void {
        $mock->shouldReceive('reasonFromException')->once()->andReturn('Timeout o error de red al entregar el documento a ViVEplus.');
        $mock->shouldReceive('notifyDeliveryFailed')->once();
    });

    $job = new PushAffiliationDocumentToViveplusJob(
        affiliationType: 'individual',
        affiliationCode: 'TDEC-IND-0001',
        documentType: 'carnet',
        absolutePath: $this->pdfPath,
        generatedAt: '2026-08-13T20:00:00+00:00',
        idempotencyKey: 'stable-later-retry',
        notifiedUserId: 15,
        laterRetryRound: 0,
        affiliateIdentification: '13991020',
    );

    $job->failed(new ViveplusDocumentWebhookTransientException('Timeout o error de red al entregar el documento a ViVEplus.'));

    Bus::assertDispatched(PushAffiliationDocumentToViveplusJob::class, function (PushAffiliationDocumentToViveplusJob $queued): bool {
        return $queued->idempotencyKey === 'stable-later-retry'
            && $queued->generatedAt === '2026-08-13T20:00:00+00:00'
            && $queued->documentType === 'carnet'
            && $queued->affiliateIdentification === '13991020'
            && $queued->laterRetryRound === 1;
    });
});

it('no reencola un 401 desde failed y avisa al analista', function (): void {
    Bus::fake();

    $this->mock(ViveplusDocumentWebhookAnalystNotifier::class, function ($mock): void {
        $mock->shouldReceive('reasonFromException')->once()->andReturn('Credenciales inválidas.');
        $mock->shouldReceive('notifyDeliveryFailed')->once();
    });

    $job = new PushAffiliationDocumentToViveplusJob(
        affiliationType: 'individual',
        affiliationCode: 'TDEC-IND-0001',
        documentType: 'certificado',
        absolutePath: $this->pdfPath,
        generatedAt: '2026-08-13T20:00:00+00:00',
        idempotencyKey: 'uuid-401-failed',
    );

    $job->failed(new ViveplusDocumentWebhookPermanentException(
        'Token o firma inválidos al entregar el documento a ViVEplus.',
        401,
    ));

    Bus::assertNotDispatched(PushAffiliationDocumentToViveplusJob::class);
});

it('encola una petición independiente por certificado y por cada carnet', function (): void {
    Bus::fake();

    ViveplusDocumentWebhookDispatcher::dispatchDocuments(
        ViveplusAffiliationType::Individual,
        'TDEC-IND-000226',
        [
            ['type' => ViveplusDocumentType::Certificado, 'path' => $this->pdfPath, 'affiliate_identification' => ''],
            ['type' => ViveplusDocumentType::Carnet, 'path' => $this->pdfPath, 'affiliate_identification' => '13991020'],
            ['type' => ViveplusDocumentType::Carnet, 'path' => $this->pdfPath, 'affiliate_identification' => '22111000'],
        ],
        9,
    );

    Bus::assertDispatched(PushAffiliationDocumentToViveplusJob::class, 3);

    $keys = [];
    $identifications = [];
    Bus::assertDispatched(PushAffiliationDocumentToViveplusJob::class, function (PushAffiliationDocumentToViveplusJob $job) use (&$keys, &$identifications): bool {
        $keys[] = $job->idempotencyKey;
        $identifications[] = $job->affiliateIdentification;

        return $job->affiliationType === 'individual'
            && $job->affiliationCode === 'TDEC-IND-000226'
            && $job->notifiedUserId === 9;
    });

    expect($keys)->toHaveCount(3)
        ->and(count(array_unique($keys)))->toBe(3)
        ->and($identifications)->toBe(['', '13991020', '22111000']);
});

it('encola un webhook de carnet por cada persona de la afiliación individual', function (): void {
    Bus::fake();

    $affiliation = new Affiliation([
        'code' => 'TDEC-IND-000226',
        'nro_identificacion_ti' => '13991020',
        'code_agency' => 'VP-1',
    ]);
    $affiliation->setRelation('whiteCompanyUser', (new User)->forceFill([
        'code_agency' => 'VP-1',
        'white_company_id' => 21,
    ]));
    $titular = new Affiliate(['nro_identificacion' => '13991020']);
    $titular->id = 10;
    $dependiente = new Affiliate(['nro_identificacion' => '22111000']);
    $dependiente->id = 11;
    $affiliation->setRelation('affiliates', collect([$titular, $dependiente]));

    $certDir = public_path('storage/certificados-doc');
    $cardDir = public_path('storage/tarjeta-afiliacion');
    foreach ([$certDir, $cardDir] as $directory) {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    $certificatePath = $certDir.'/CER-TDEC-IND-000226.pdf';
    $titularPath = $cardDir.'/TAR-TDEC-IND-000226-10.pdf';
    $dependientePath = $cardDir.'/TAR-TDEC-IND-000226-11.pdf';
    file_put_contents($certificatePath, '%PDF-1.4 cert');
    file_put_contents($titularPath, '%PDF-1.4 titular');
    file_put_contents($dependientePath, '%PDF-1.4 dependiente');

    try {
        ViveplusDocumentWebhookDispatcher::dispatchForIndividual($affiliation, 4);

        Bus::assertDispatched(PushAffiliationDocumentToViveplusJob::class, 3);
        Bus::assertDispatched(
            PushAffiliationDocumentToViveplusJob::class,
            fn (PushAffiliationDocumentToViveplusJob $job): bool => $job->documentType === 'certificado'
                && $job->affiliateIdentification === ''
        );
        Bus::assertDispatched(
            PushAffiliationDocumentToViveplusJob::class,
            fn (PushAffiliationDocumentToViveplusJob $job): bool => $job->documentType === 'carnet'
                && $job->affiliateIdentification === '13991020'
                && $job->absolutePath === $titularPath
        );
        Bus::assertDispatched(
            PushAffiliationDocumentToViveplusJob::class,
            fn (PushAffiliationDocumentToViveplusJob $job): bool => $job->documentType === 'carnet'
                && $job->affiliateIdentification === '22111000'
                && $job->absolutePath === $dependientePath
        );
    } finally {
        @unlink($certificatePath);
        @unlink($titularPath);
        @unlink($dependientePath);
    }
});

it('no encola webhook de ViVEplus cuando la afiliación individual no es de empresa aliada', function (): void {
    Bus::fake();

    $affiliation = new Affiliation([
        'code' => 'TDEC-IND-000394',
        'nro_identificacion_ti' => '123',
        'code_agency' => 'TDG-1',
    ]);
    $affiliation->setRelation('whiteCompanyUser', null);
    $affiliation->setRelation('affiliates', collect());

    ViveplusDocumentWebhookDispatcher::dispatchForIndividual($affiliation, 2);

    Bus::assertNothingDispatched();
});

it('omite en el job el envío a ViVEplus de afiliaciones individuales que no son de empresa aliada', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/PushAffiliationDocumentToViveplusJob.php');
    $dispatcher = file_get_contents(dirname(__DIR__, 2).'/app/Support/Viveplus/ViveplusDocumentWebhookDispatcher.php');

    expect($dispatcher)
        ->toContain('AffiliationWhiteCompany::belongsToAlliedCompany')
        ->toContain('dispatchForIndividual')
        ->and($job)
        ->toContain('AffiliationWhiteCompany::belongsToAlliedCompany')
        ->toContain('ViveplusAffiliationType::Individual');
});

it('no elimina el PDF local después de enviarlo o fallar', function (): void {
    $client = file_get_contents(dirname(__DIR__, 2).'/app/Support/Viveplus/ViveplusDocumentWebhookClient.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/PushAffiliationDocumentToViveplusJob.php');

    expect($client)->not->toContain('unlink(')
        ->and($job)->not->toContain('unlink(');
});

it('usa backoff de 5s, 30s y 2min en el job', function (): void {
    $job = new PushAffiliationDocumentToViveplusJob(
        affiliationType: 'individual',
        affiliationCode: 'TDEC-IND-0001',
        documentType: 'certificado',
        absolutePath: $this->pdfPath,
        generatedAt: '2026-08-13T20:00:00+00:00',
        idempotencyKey: 'uuid-backoff',
    );

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(30)
        ->and($job->backoff())->toBe([5, 30, 120]);
});
