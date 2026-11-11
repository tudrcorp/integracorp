<?php

declare(strict_types=1);

use App\Jobs\SendAffiliationCertificateDeliveryJob;
use App\Mail\AffiliationCertificateDeliveryMail;
use App\Models\AffiliationCertificateDelivery;
use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Services\AffiliationCertificateDeliveryService;
use App\Support\Affiliations\Certificates\CertificateDeliveryParts;
use App\Support\Affiliations\Certificates\CertificateDeliveryRecipients;
use App\Support\Affiliations\Certificates\CertificateDeliveryRememberedCopies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('local');
    Mail::fake();
    Queue::fake();
});

afterEach(fn () => DB::rollBack());

/**
 * Emisión mínima con su PDF guardado en el disco simulado.
 */
function deliveryTestIssue(int $pdfBytes = 2048, array $carnets = [11, 12, 13]): AffiliationCertificateIssue
{
    $key = 'CER-TEST-'.strtoupper(bin2hex(random_bytes(4)));
    $path = 'certificados-generador/'.$key.'.pdf';
    Storage::disk('local')->put($path, str_repeat('x', $pdfBytes));

    return AffiliationCertificateIssue::query()->create([
        'verification_key' => $key,
        'affiliation_type' => AffiliationCertificateIssue::TYPE_INDIVIDUAL,
        'affiliation_id' => 999999,
        'affiliation_code' => 'TDEC-IND-TEST',
        'plan_name' => 'PLAN DE PRUEBA',
        'affiliates_count' => count($carnets),
        'carnets_count' => count($carnets),
        'carnet_affiliate_ids' => $carnets,
        'pdf_path' => $path,
    ]);
}

function deliveryTestUser(): User
{
    // Solo lectura: un usuario existente, para que `sent_by` cumpla su llave foránea.
    return User::query()->orderBy('id')->firstOrFail();
}

it('limpia los correos: minúsculas, sin repetidos y sin inválidos', function (): void {
    expect(CertificateDeliveryRecipients::clean([' Ana@Example.com', 'ana@example.com', 'malo@', '', null, 'luis@example.com']))
        ->toBe(['ana@example.com', 'luis@example.com'])
        ->and(CertificateDeliveryRecipients::normalize(['no-es-texto']))->toBeNull();
});

it('registra el envío del documento completo y lo pone en cola', function (): void {
    $issue = deliveryTestIssue();

    $delivery = app(AffiliationCertificateDeliveryService::class)->request(
        $issue,
        AffiliationCertificateDelivery::CONTENT_FULL,
        [],
        ['Ana@Example.com', 'ana@example.com', 'malo@'],
        '  Saludos  ',
        deliveryTestUser(),
    );

    expect($delivery->status)->toBe(AffiliationCertificateDelivery::STATUS_PENDING)
        ->and($delivery->recipients)->toBe(['ana@example.com'])
        ->and($delivery->message)->toBe('Saludos')
        ->and($delivery->carnet_affiliate_ids)->toBeNull();

    Queue::assertPushed(SendAffiliationCertificateDeliveryJob::class, fn ($job): bool => $job->deliveryId === $delivery->id);
});

it('reparte las copias sin repetir a nadie y siempre agrega la copia oculta fija', function (): void {
    expect(CertificateDeliveryRecipients::copies(
        ['ana@example.com'],
        ['Testigo@Example.com', 'ana@example.com', 'malo@'],
        ['testigo@example.com', 'oculto@example.com'],
    ))->toBe([
        'cc' => ['testigo@example.com'],
        'bcc' => ['solrodriguez@tudrencasa.com', 'oculto@example.com'],
    ])
        ->and(CertificateDeliveryRecipients::copies(['ana@example.com'], [], [])['bcc'])->toBe(['solrodriguez@tudrencasa.com'])
        ->and(CertificateDeliveryRecipients::copies(['solrodriguez@tudrencasa.com'], [], [])['bcc'])->toBe([])
        ->and(CertificateDeliveryRecipients::withMandatoryBcc(['otro@example.com']))->toBe(['solrodriguez@tudrencasa.com', 'otro@example.com']);
});

it('guarda testigos y copias ocultas, con la fija aunque el formulario no la mande', function (): void {
    $delivery = app(AffiliationCertificateDeliveryService::class)->request(
        deliveryTestIssue(),
        AffiliationCertificateDelivery::CONTENT_FULL,
        [],
        ['a@example.com'],
        null,
        deliveryTestUser(),
        ['testigo@example.com'],
        ['oculto@example.com'],
    );

    expect($delivery->cc_recipients)->toBe(['testigo@example.com'])
        ->and($delivery->bcc_recipients)->toBe(['solrodriguez@tudrencasa.com', 'oculto@example.com'])
        ->and($delivery->copies_sent_at)->toBeNull();
});

it('rechaza demasiados testigos o copias ocultas', function (array $cc, array $bcc, string $error): void {
    expect(fn () => app(AffiliationCertificateDeliveryService::class)->request(
        deliveryTestIssue(),
        AffiliationCertificateDelivery::CONTENT_FULL,
        [],
        ['a@example.com'],
        null,
        deliveryTestUser(),
        $cc,
        $bcc,
    ))->toThrow(InvalidArgumentException::class, $error);

    Queue::assertNothingPushed();
})->with([
    'once testigos' => [array_map(fn (int $i): string => "t{$i}@example.com", range(1, 11)), [], 'hasta 10 correos testigos'],
    'diez ocultas más la fija' => [[], array_map(fn (int $i): string => "o{$i}@example.com", range(1, 10)), 'contando la fija'],
]);

it('las copias viajan una sola vez, en el primer correo, aunque haya varios destinatarios y reintentos', function (): void {
    $service = app(AffiliationCertificateDeliveryService::class);
    $delivery = $service->request(deliveryTestIssue(), AffiliationCertificateDelivery::CONTENT_FULL, [], ['a@example.com', 'b@example.com'], null, deliveryTestUser(), ['testigo@example.com'], []);

    $service->deliver($delivery->fresh());
    $service->deliver($delivery->fresh());

    expect($delivery->fresh()->copies_sent_at)->not->toBeNull();

    Mail::assertSentCount(2);
    Mail::assertSent(AffiliationCertificateDeliveryMail::class, fn (AffiliationCertificateDeliveryMail $mail): bool => $mail->hasTo('a@example.com')
        && $mail->hasCc('testigo@example.com')
        && $mail->hasBcc('solrodriguez@tudrencasa.com'));
    Mail::assertSent(AffiliationCertificateDeliveryMail::class, fn (AffiliationCertificateDeliveryMail $mail): bool => $mail->hasTo('b@example.com')
        && $mail->cc === []
        && $mail->bcc === []);
});

it('si el primer correo ya salió con copias, el reintento no las repite', function (): void {
    $service = app(AffiliationCertificateDeliveryService::class);
    $delivery = $service->request(deliveryTestIssue(), AffiliationCertificateDelivery::CONTENT_FULL, [], ['a@example.com', 'b@example.com'], null, deliveryTestUser(), ['testigo@example.com'], []);
    $delivery->forceFill(['delivered_to' => ['a@example.com'], 'copies_sent_at' => now()])->save();

    $service->deliver($delivery->fresh());

    Mail::assertSentCount(1);
    Mail::assertSent(AffiliationCertificateDeliveryMail::class, fn (AffiliationCertificateDeliveryMail $mail): bool => $mail->hasTo('b@example.com') && $mail->bcc === []);
});

it('un envío registrado antes de las copias igual sale con la copia oculta fija', function (): void {
    $issue = deliveryTestIssue();
    $delivery = AffiliationCertificateDelivery::query()->create([
        'affiliation_certificate_issue_id' => $issue->id,
        'content' => AffiliationCertificateDelivery::CONTENT_FULL,
        'recipients' => ['a@example.com'],
        'delivered_to' => [],
        'status' => AffiliationCertificateDelivery::STATUS_PENDING,
    ]);

    app(AffiliationCertificateDeliveryService::class)->deliver($delivery->fresh());

    Mail::assertSent(AffiliationCertificateDeliveryMail::class, fn (AffiliationCertificateDeliveryMail $mail): bool => $mail->hasBcc('solrodriguez@tudrencasa.com'));
});

it('recuerda testigos y copias ocultas por separado, los reemplaza y los olvida al apagar el interruptor', function (): void {
    $user = deliveryTestUser();
    $otherPreferences = ['quick_view' => true];
    $user->forceFill(['ui_preferences' => $otherPreferences])->saveQuietly();
    $cc = CertificateDeliveryRememberedCopies::KIND_CC;
    $bcc = CertificateDeliveryRememberedCopies::KIND_BCC;

    expect(CertificateDeliveryRememberedCopies::for($user->fresh(), $cc))->toBe([])
        ->and(CertificateDeliveryRememberedCopies::for($user->fresh(), $bcc))->toBe([]);

    CertificateDeliveryRememberedCopies::update($user, $cc, [' Testigo@Example.com', 'malo@', 'testigo@example.com', 'otro@example.com'], true);
    CertificateDeliveryRememberedCopies::update($user, $bcc, ['solrodriguez@tudrencasa.com', 'Oculto@Example.com'], true);

    expect(CertificateDeliveryRememberedCopies::for($user->fresh(), $cc))->toBe(['testigo@example.com', 'otro@example.com'])
        ->and(CertificateDeliveryRememberedCopies::for($user->fresh(), $bcc))->toBe(['oculto@example.com'])
        ->and($user->fresh()->ui_preferences['quick_view'])->toBeTrue();

    CertificateDeliveryRememberedCopies::update($user, $cc, ['nuevo@example.com'], true);
    expect(CertificateDeliveryRememberedCopies::for($user->fresh(), $cc))->toBe(['nuevo@example.com'])
        ->and(CertificateDeliveryRememberedCopies::for($user->fresh(), $bcc))->toBe(['oculto@example.com']);

    CertificateDeliveryRememberedCopies::update($user, $cc, ['nuevo@example.com'], false);
    CertificateDeliveryRememberedCopies::update($user, $bcc, ['solrodriguez@tudrencasa.com'], true);
    expect(CertificateDeliveryRememberedCopies::for($user->fresh(), $cc))->toBe([])
        ->and(CertificateDeliveryRememberedCopies::for($user->fresh(), $bcc))->toBe([])
        ->and($user->fresh()->ui_preferences)->toBe($otherPreferences);
});

it('lo recordado se lee con tolerancia: sin usuario, basura, demasiados correos o tipo desconocido', function (): void {
    $many = [...array_map(fn (int $i): string => "t{$i}@example.com", range(1, 12)), 'malo@'];
    $user = User::factory()->make(['ui_preferences' => [
        CertificateDeliveryRememberedCopies::preferenceKey(CertificateDeliveryRememberedCopies::KIND_CC) => $many,
        CertificateDeliveryRememberedCopies::preferenceKey(CertificateDeliveryRememberedCopies::KIND_BCC) => $many,
    ]]);

    expect(CertificateDeliveryRememberedCopies::for(null, CertificateDeliveryRememberedCopies::KIND_CC))->toBe([])
        ->and(CertificateDeliveryRememberedCopies::for($user, CertificateDeliveryRememberedCopies::KIND_CC))->toHaveCount(10)
        ->and(CertificateDeliveryRememberedCopies::for($user, CertificateDeliveryRememberedCopies::KIND_BCC))->toHaveCount(9)
        ->and(CertificateDeliveryRememberedCopies::for(User::factory()->make(['ui_preferences' => ['certificate_delivery_cc' => 'texto']]), CertificateDeliveryRememberedCopies::KIND_CC))->toBe([])
        ->and(fn () => CertificateDeliveryRememberedCopies::for($user, 'otro'))->toThrow(InvalidArgumentException::class);
});

it('rechaza envíos sin correos válidos, con demasiados destinatarios o sin carnets', function (string $content, array $carnets, array $emails, string $error): void {
    $issue = deliveryTestIssue();

    expect(fn () => app(AffiliationCertificateDeliveryService::class)->request($issue, $content, $carnets, $emails, null, deliveryTestUser()))
        ->toThrow(InvalidArgumentException::class, $error);

    Queue::assertNothingPushed();
})->with([
    'sin correos válidos' => [AffiliationCertificateDelivery::CONTENT_FULL, [], ['malo@', ''], 'al menos un correo válido'],
    'demasiados correos' => [AffiliationCertificateDelivery::CONTENT_FULL, [], array_map(fn (int $i): string => "c{$i}@example.com", range(1, 11)), 'máximo de 10'],
    'carnets ajenos a la emisión' => [AffiliationCertificateDelivery::CONTENT_CARNETS, [999, 1000], ['a@example.com'], 'al menos un carnet'],
    'contenido desconocido' => ['otro', [], ['a@example.com'], 'qué desea enviar'],
]);

it('ofrece solo las partes que tiene la emisión y las nombra en el correo', function (): void {
    $issue = deliveryTestIssue();
    $single = deliveryTestIssue(carnets: []);
    $single->forceFill(['affiliates_count' => 1])->save();

    expect(CertificateDeliveryParts::availableFor($issue))->toBe(CertificateDeliveryParts::ORDER)
        ->and(CertificateDeliveryParts::optionsFor($issue))->toBe([
            'certificate' => 'Certificado',
            'affiliates' => 'Lista de afiliados (3)',
            'carnets' => 'Carnets (3)',
        ])
        ->and(CertificateDeliveryParts::availableFor($single))->toBe([CertificateDeliveryParts::CERTIFICATE])
        ->and(CertificateDeliveryParts::clean(['carnets', 'certificate', 'certificate', 'otro', 5], $issue))->toBe(['certificate', 'carnets'])
        ->and(CertificateDeliveryParts::clean(['carnets'], $single))->toBe([])
        ->and(CertificateDeliveryParts::pageKinds(['certificate', 'carnets']))->toBe(['cert', 'cont', 'custom', 'carnet'])
        ->and(CertificateDeliveryParts::describe(CertificateDeliveryParts::ORDER, 3))->toBe('el certificado de afiliación, la relación de afiliados y los 3 carnets de afiliado')
        ->and(CertificateDeliveryParts::describe(['affiliates'], 0))->toBe('la relación de afiliados')
        ->and(CertificateDeliveryParts::subject(['affiliates', 'carnets'], 2))->toBe('Documentos de afiliación')
        ->and(CertificateDeliveryParts::subject(['carnets'], 1))->toBe('Carnet de afiliado')
        ->and(CertificateDeliveryParts::subject(['affiliates'], 0))->toBe('Relación de afiliados');
});

it('todo junto con todos los carnets es el documento completo; cualquier otra combinación arma un PDF aparte', function (array $parts, ?array $carnets, string $content, ?array $storedCarnets): void {
    $delivery = app(AffiliationCertificateDeliveryService::class)->request(deliveryTestIssue(), $parts, $carnets, ['a@example.com'], null, deliveryTestUser());

    expect($delivery->content)->toBe($content)
        ->and($delivery->parts)->toBe(array_values(array_intersect(CertificateDeliveryParts::ORDER, $parts)))
        ->and($delivery->carnet_affiliate_ids)->toBe($storedCarnets);
})->with([
    'todo, todos los carnets' => [CertificateDeliveryParts::ORDER, null, AffiliationCertificateDelivery::CONTENT_FULL, null],
    'todo, carnets elegidos que son todos' => [CertificateDeliveryParts::ORDER, [13, 12, 11], AffiliationCertificateDelivery::CONTENT_FULL, null],
    'todo, algunos carnets' => [CertificateDeliveryParts::ORDER, [12], AffiliationCertificateDelivery::CONTENT_CUSTOM, [12]],
    'certificado y carnets, sin lista' => [['carnets', 'certificate'], null, AffiliationCertificateDelivery::CONTENT_CUSTOM, [11, 12, 13]],
    'solo la lista' => [['affiliates'], [11], AffiliationCertificateDelivery::CONTENT_CUSTOM, null],
    'solo algunos carnets' => [['carnets'], [11, 999], AffiliationCertificateDelivery::CONTENT_CUSTOM, [11]],
]);

it('rechaza partes vacías o una elección de carnets sin carnets válidos', function (array $parts, ?array $carnets, string $error): void {
    expect(fn () => app(AffiliationCertificateDeliveryService::class)->request(deliveryTestIssue(), $parts, $carnets, ['a@example.com'], null, deliveryTestUser()))
        ->toThrow(InvalidArgumentException::class, $error);

    Queue::assertNothingPushed();
})->with([
    'sin partes' => [[], null, 'qué desea enviar'],
    'partes desconocidas' => [['otra'], null, 'qué desea enviar'],
    'elegir carnets sin elegir' => [['carnets'], [], 'al menos un carnet'],
    'carnets ajenos' => [['certificate', 'carnets'], [500], 'al menos un carnet'],
]);

it('el correo nombra las partes enviadas y el archivo lleva su nombre', function (): void {
    $issue = deliveryTestIssue();
    $mail = new AffiliationCertificateDeliveryMail(1, 'TDEC-IND-TEST', 'PLAN', false, 2, null, null, 'x.pdf', null, null, ['certificate', 'carnets']);
    $html = $mail->render();
    $list = app(AffiliationCertificateDeliveryService::class)->request($issue, ['affiliates'], null, ['a@example.com'], null, deliveryTestUser());

    expect($html)->toContain('el certificado de afiliación y los 2 carnets de afiliado')
        ->toContain('conservar estos documentos')
        ->and($mail->envelope()->subject)->toBe('Certificado de afiliación TDEC-IND-TEST — Tu Doctor en Casa')
        ->and(AffiliationCertificateDeliveryService::fileName($list, $issue))->toStartWith('Relacion-afiliados-')
        ->and(AffiliationCertificateDeliveryService::carnetsCount($list, $issue))->toBe(0);
});

it('los envíos anteriores a la elección por partes se leen desde su contenido', function (): void {
    $legacy = fn (string $content): AffiliationCertificateDelivery => new AffiliationCertificateDelivery(['content' => $content]);

    expect($legacy(AffiliationCertificateDelivery::CONTENT_FULL)->documentParts())->toBe(CertificateDeliveryParts::ORDER)
        ->and($legacy(AffiliationCertificateDelivery::CONTENT_CERTIFICATE)->documentParts())->toBe(['certificate', 'affiliates'])
        ->and($legacy(AffiliationCertificateDelivery::CONTENT_CARNETS)->isCarnetsOnly())->toBeTrue()
        ->and($legacy(AffiliationCertificateDelivery::CONTENT_FULL)->usesOwnFile())->toBeFalse()
        ->and($legacy(AffiliationCertificateDelivery::CONTENT_CERTIFICATE)->usesOwnFile())->toBeTrue();
});

it('acepta «solo el certificado» aun mientras el completo se dibuja en cola', function (): void {
    $issue = deliveryTestIssue();
    $issue->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING])->save();

    $delivery = app(AffiliationCertificateDeliveryService::class)->request($issue, AffiliationCertificateDelivery::CONTENT_CERTIFICATE, [11], ['a@example.com'], null, deliveryTestUser());

    expect($delivery->content)->toBe(AffiliationCertificateDelivery::CONTENT_CUSTOM)
        ->and($delivery->documentParts())->toBe([CertificateDeliveryParts::CERTIFICATE, CertificateDeliveryParts::AFFILIATES])
        ->and($delivery->carnet_affiliate_ids)->toBeNull()
        ->and($delivery->isCertificateOnly())->toBeTrue()
        ->and($delivery->usesOwnFile())->toBeTrue();

    Queue::assertPushed(SendAffiliationCertificateDeliveryJob::class);
});

it('el correo de solo certificado no menciona carnets', function (): void {
    $html = (new AffiliationCertificateDeliveryMail(1, 'TDEC-IND-TEST', 'PLAN', false, 0, null, null, 'Certificado-TDEC-IND-TEST.pdf', null, null))->render();

    expect($html)
        ->toContain('el certificado de afiliación de Tu Doctor en Casa')
        ->toContain('conservar el certificado en su teléfono')
        ->not->toContain('carnet');
});

it('no permite enviar el completo mientras se dibuja en cola', function (): void {
    $issue = deliveryTestIssue();
    $issue->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING])->save();

    expect(fn () => app(AffiliationCertificateDeliveryService::class)->request($issue, AffiliationCertificateDelivery::CONTENT_FULL, [], ['a@example.com'], null, deliveryTestUser()))
        ->toThrow(InvalidArgumentException::class, 'todavía se está generando');
});

it('solo acepta carnets de la emisión y respeta el tope por correo', function (): void {
    $issue = deliveryTestIssue(carnets: range(1, 70));
    $service = app(AffiliationCertificateDeliveryService::class);

    $delivery = $service->request($issue, AffiliationCertificateDelivery::CONTENT_CARNETS, [2, 3, 3, 500], ['a@example.com'], null, deliveryTestUser());

    expect($delivery->carnet_affiliate_ids)->toBe([2, 3])
        ->and(fn () => $service->request($issue, AffiliationCertificateDelivery::CONTENT_CARNETS, range(1, 61), ['a@example.com'], null, deliveryTestUser()))
        ->toThrow(InvalidArgumentException::class, 'hasta 60 carnets');
});

it('adjunta el PDF cuando es liviano y envía a cada destinatario una sola vez', function (): void {
    $issue = deliveryTestIssue(pdfBytes: 4096);
    $service = app(AffiliationCertificateDeliveryService::class);
    $delivery = $service->request($issue, AffiliationCertificateDelivery::CONTENT_FULL, [], ['a@example.com', 'b@example.com'], 'Hola', deliveryTestUser());

    $service->deliver($delivery->fresh());
    $service->deliver($delivery->fresh());

    $delivery->refresh();

    expect($delivery->status)->toBe(AffiliationCertificateDelivery::STATUS_SENT)
        ->and($delivery->delivery_method)->toBe(AffiliationCertificateDelivery::METHOD_ATTACHMENT)
        ->and($delivery->delivered_to)->toBe(['a@example.com', 'b@example.com'])
        ->and($delivery->link_expires_at)->toBeNull();

    Mail::assertSentCount(2);
    Mail::assertSent(AffiliationCertificateDeliveryMail::class, fn (AffiliationCertificateDeliveryMail $mail): bool => $mail->hasTo('a@example.com')
        && $mail->downloadUrl === null
        && count($mail->attachments()) === 1
        && $mail->attachmentName === 'Certificado-TDEC-IND-TEST.pdf');
});

it('si un reintento llega a mitad de envío, no repite a quien ya lo recibió', function (): void {
    $issue = deliveryTestIssue();
    $service = app(AffiliationCertificateDeliveryService::class);
    $delivery = $service->request($issue, AffiliationCertificateDelivery::CONTENT_FULL, [], ['a@example.com', 'b@example.com'], null, deliveryTestUser());
    $delivery->forceFill(['delivered_to' => ['a@example.com']])->save();

    $service->deliver($delivery->fresh());

    Mail::assertSentCount(1);
    Mail::assertSent(AffiliationCertificateDeliveryMail::class, fn (AffiliationCertificateDeliveryMail $mail): bool => $mail->hasTo('b@example.com'));
});

it('envía un enlace firmado de 30 días cuando el PDF supera los 8 MB', function (): void {
    $issue = deliveryTestIssue(pdfBytes: AffiliationCertificateDeliveryService::MAX_ATTACHMENT_BYTES + 1);
    $service = app(AffiliationCertificateDeliveryService::class);
    $delivery = $service->request($issue, AffiliationCertificateDelivery::CONTENT_FULL, [], ['a@example.com'], null, deliveryTestUser());

    $service->deliver($delivery->fresh());
    $delivery->refresh();

    expect($delivery->delivery_method)->toBe(AffiliationCertificateDelivery::METHOD_LINK)
        ->and($delivery->link_expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    Mail::assertSent(AffiliationCertificateDeliveryMail::class, function (AffiliationCertificateDeliveryMail $mail): bool {
        $html = $mail->render();

        return $mail->attachments() === []
            && str_contains((string) $mail->downloadUrl, 'signature=')
            && str_contains($html, 'Descargar documento (PDF)')
            && str_contains($html, 'TU DOCTOR EN CASA, C. A.');
    });
});

it('el enlace firmado descarga el PDF; sin firma o vencido, no', function (): void {
    $issue = deliveryTestIssue();
    $delivery = AffiliationCertificateDelivery::query()->create([
        'affiliation_certificate_issue_id' => $issue->id,
        'content' => AffiliationCertificateDelivery::CONTENT_FULL,
        'recipients' => ['a@example.com'],
        'status' => AffiliationCertificateDelivery::STATUS_SENT,
        'delivery_method' => AffiliationCertificateDelivery::METHOD_LINK,
        'link_expires_at' => now()->addDays(30),
    ]);

    $this->get(AffiliationCertificateDeliveryService::downloadUrl($delivery))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('affiliation-certificate.delivery.download', ['delivery' => $delivery->id]))->assertForbidden();

    $delivery->forceFill(['link_expires_at' => now()->subDay()])->save();

    $this->get(URL::signedRoute('affiliation-certificate.delivery.download', ['delivery' => $delivery->id]))->assertStatus(410);
});

it('el generador individual y el corporativo muestran «Enviar por correo» junto a la descarga', function (): void {
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/resources/views/filament/business/resources/affiliations/pages/certificate-generator.blade.php'))
        ->toContain('{{ $this->sendByEmailAction }}')
        ->and(file_get_contents($root.'/app/Filament/Business/Resources/Affiliations/Pages/CertificateGenerator.php'))
        ->toContain('use SendsAffiliationCertificateByEmail;')
        ->and(file_get_contents($root.'/app/Filament/Business/Resources/AffiliationCorporates/Pages/CorporateCertificateGenerator.php'))
        ->toContain('use SendsAffiliationCertificateByEmail;')
        ->and(file_get_contents($root.'/app/Filament/Business/Resources/Affiliations/Concerns/SendsAffiliationCertificateByEmail.php'))
        ->toContain('CertificateDeliveryRecipients::defaultsFor($issue)')
        ->toContain("->nestedRecursiveRules(['email:filter', 'max:150'])")
        ->toContain("CheckboxList::make('parts')")
        ->toContain("Radio::make('carnets_scope')")
        ->toContain("Section::make('Copias y mensaje')")
        ->toContain('->collapsible()')
        ->toContain("TagsInput::make('cc_recipients')")
        ->toContain("TagsInput::make('bcc_recipients')")
        ->toContain('CertificateDeliveryRecipients::withMandatoryBcc(')
        ->toContain("Toggle::make('remember_cc')")
        ->toContain("Toggle::make('remember_bcc')")
        ->toContain("'cc_recipients' => \$rememberedCc,")
        ->toContain("'bcc_recipients' => CertificateDeliveryRecipients::withMandatoryBcc(\$rememberedBcc),")
        ->toContain('CertificateDeliveryRememberedCopies::KIND_BCC, (array) ($data[\'bcc_recipients\']');
});
