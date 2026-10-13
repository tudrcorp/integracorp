<?php

declare(strict_types=1);

use App\Jobs\SendAffiliationCertificateDeliveryJob;
use App\Mail\AffiliationCertificateDeliveryMail;
use App\Models\AffiliationCertificateDelivery;
use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Services\AffiliationCertificateDeliveryService;
use App\Support\Affiliations\Certificates\CertificateDeliveryRecipients;
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

it('acepta «solo el certificado» aun mientras el completo se dibuja en cola', function (): void {
    $issue = deliveryTestIssue();
    $issue->forceFill(['pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING])->save();

    $delivery = app(AffiliationCertificateDeliveryService::class)->request($issue, AffiliationCertificateDelivery::CONTENT_CERTIFICATE, [11], ['a@example.com'], null, deliveryTestUser());

    expect($delivery->content)->toBe(AffiliationCertificateDelivery::CONTENT_CERTIFICATE)
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
        ->toContain("'Solo el certificado (sin carnets)'");
});
