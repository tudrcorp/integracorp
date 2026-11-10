<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Filament\Business\Pages\ManageCompanyAssociateNotifications;
use App\Jobs\SendNotificacionWhatsAppDocument;
use App\Mail\CollectionNoticeCopyMail;
use App\Mail\MailSaleReciboPago;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Collection;
use App\Models\DocumentEmailTemplate;
use App\Models\Sale;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\Collections\CollectionNotice;
use App\Support\Collections\CollectionNoticeEmailDelivery;
use App\Support\DocumentEmails\DocumentEmailWitnesses;
use App\Support\Filament\Administration\SaleReciboPagoWhatsAppRecipients;
use App\Support\Sales\SaleReceiptEmailDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    config(['cache.default' => 'array']);
    Mail::fake();
    Queue::fake();

    SystemNotificationRecipientSetting::query()
        ->whereIn('notification_key', [
            SystemNotificationKey::SaleReceiptWitnessCopy->value,
            SystemNotificationKey::CollectionNoticeWitnessCopy->value,
        ])
        ->delete();
    DocumentEmailTemplate::query()->update(['is_active' => false, 'is_default' => false]);
});

afterEach(function (): void {
    foreach ($this->pdfPaths ?? [] as $path) {
        @unlink($path);
    }

    DB::rollBack();
});

/**
 * @param  array<string, string>  $modes
 */
function configureWitnesses(SystemNotificationKey $key, array $emails, array $modes, array $phones, bool $active = true): void
{
    SystemNotificationRecipientSetting::for($key)->update([
        'notification_emails' => $emails,
        'notification_email_modes' => $modes,
        'notification_phones' => $phones,
        'is_active' => $active,
    ]);
}

function witnessSale(object $test): Sale
{
    $sale = new Sale([
        'type' => 'AFILIACION INDIVIDUAL',
        'invoice_number' => 'RDP-WIT-'.uniqid(),
        'affiliate_full_name' => 'Margarita Torres',
        'affiliate_email' => 'afiliada@example.com',
        'total_amount' => 163.5,
        'agent_id' => 10,
        'code_agency' => 'AC-001',
    ]);
    $sale->id = random_int(900000000, 999999999);
    $sale->setRelation('agent', new Agent(['name' => 'Agente Demo', 'email' => 'agente@example.com', 'phone' => '04241112233']));
    $sale->setRelation('agency', new Agency(['code' => 'AC-001', 'name_corporative' => 'Agencia Demo', 'email' => 'agencia@example.com']));
    $sale->setRelation('plan', null);

    $path = SalesTable::reciboPagoPdfPath($sale);
    @mkdir(dirname($path), 0755, true);
    file_put_contents($path, '%PDF-1.4 test');
    $test->pdfPaths[] = $path;

    return $sale;
}

it('arranca con los testigos de siempre: recibos con 4 correos en CC y Sol por WhatsApp, avisos con administración', function (): void {
    $receipt = DocumentEmailWitnesses::for(SystemNotificationKey::SaleReceiptWitnessCopy);
    $notice = DocumentEmailWitnesses::for(SystemNotificationKey::CollectionNoticeWitnessCopy);

    expect($receipt['cc'])->toBe([
        'afiliaciones@tudrencasa.com',
        'administracion@tudrencasa.com',
        'solrodriguez@tudrencasa.com',
        'hsanchez@tudrencasa.com',
    ])
        ->and($receipt['bcc'])->toBe([])
        ->and($receipt['phones'])->toBe(['+584143027250'])
        ->and($notice['cc'])->toBe(['administracion@tudrencasa.com'])
        ->and($notice['phones'])->toBe([]);
});

it('separa los correos testigo en CC y CCO según lo marcado', function (): void {
    configureWitnesses(SystemNotificationKey::SaleReceiptWitnessCopy, ['visible@tdg.com', 'oculto@tdg.com'], ['oculto@tdg.com' => 'bcc'], []);

    $witnesses = DocumentEmailWitnesses::for(SystemNotificationKey::SaleReceiptWitnessCopy);

    expect($witnesses['cc'])->toBe(['visible@tdg.com'])
        ->and($witnesses['bcc'])->toBe(['oculto@tdg.com']);
});

it('el recibo sale con testigos en CC y CCO y un WhatsApp testigo con el PDF', function (): void {
    configureWitnesses(SystemNotificationKey::SaleReceiptWitnessCopy, ['visible@tdg.com', 'oculto@tdg.com'], ['oculto@tdg.com' => 'bcc'], ['04141234567']);

    $affiliate = DocumentEmailTemplate::factory()->create(['name' => 'Copy In House']);
    $sale = witnessSale($this);

    SaleReceiptEmailDelivery::deliver($sale, $affiliate->id, null);

    Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => $mail->hasTo('afiliada@example.com')
        && $mail->hasCc('visible@tdg.com')
        && ! $mail->hasCc('oculto@tdg.com')
        && $mail->hasBcc('oculto@tdg.com'));

    Queue::assertPushed(SendNotificacionWhatsAppDocument::class, fn (SendNotificacionWhatsAppDocument $job): bool => $job->phone === '+584141234567'
        && str_contains($job->caption, 'Recibo de pago Nro. '.$sale->invoice_number)
        && str_contains($job->caption, 'afiliada@example.com')
        && str_contains($job->caption, 'Copy In House')
        && str_contains($job->documentUrl, '/reciboDePago/RDP-')
        && $job->filename === 'RDP-'.$sale->invoice_number.'.pdf');
});

it('con la entrada inactiva el recibo sale igual, sin testigos', function (): void {
    configureWitnesses(SystemNotificationKey::SaleReceiptWitnessCopy, ['visible@tdg.com'], [], ['04141234567'], active: false);

    $affiliate = DocumentEmailTemplate::factory()->create();

    SaleReceiptEmailDelivery::deliver(witnessSale($this), $affiliate->id, null);

    Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => $mail->hasTo('afiliada@example.com')
        && $mail->ccRecipients === []
        && $mail->bccRecipients === []);
    Queue::assertNotPushed(SendNotificacionWhatsAppDocument::class);
});

it('el modo prueba nunca copia a los testigos', function (): void {
    configureWitnesses(SystemNotificationKey::SaleReceiptWitnessCopy, ['visible@tdg.com'], [], ['04141234567']);

    $affiliate = DocumentEmailTemplate::factory()->create();

    SaleReceiptEmailDelivery::deliver(witnessSale($this), $affiliate->id, null, testMode: true, testEmail: 'prueba@example.com');

    Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => $mail->hasTo('prueba@example.com')
        && $mail->ccRecipients === []
        && $mail->bccRecipients === []);
    Queue::assertNotPushed(SendNotificacionWhatsAppDocument::class);
});

it('el aviso de cobro usa sus propios testigos, en CC, CCO y WhatsApp', function (): void {
    configureWitnesses(SystemNotificationKey::CollectionNoticeWitnessCopy, ['cobranza@tdg.com', 'gerencia@tdg.com'], ['gerencia@tdg.com' => 'bcc'], ['04249998877']);
    configureWitnesses(SystemNotificationKey::SaleReceiptWitnessCopy, ['solo-recibos@tdg.com'], [], ['04140000000']);

    $template = DocumentEmailTemplate::factory()->collectionNotice()->create();
    $collection = new Collection([
        'collection_invoice_number' => 'AVC-WIT-'.uniqid(),
        'affiliate_full_name' => 'Alberto Martinez',
        'affiliate_email' => 'afiliado@example.com',
        'total_amount' => 80,
    ]);
    $path = CollectionNotice::pdfPath($collection);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, '%PDF-1.4 aviso');
    $this->pdfPaths[] = $path;

    CollectionNoticeEmailDelivery::deliver($collection, $template->id, null);

    Mail::assertQueued(CollectionNoticeCopyMail::class, fn (CollectionNoticeCopyMail $mail): bool => $mail->hasCc('cobranza@tdg.com')
        && $mail->hasBcc('gerencia@tdg.com')
        && ! $mail->hasCc('solo-recibos@tdg.com')
        && ! $mail->hasCc('administracion@tudrencasa.com'));

    Queue::assertPushed(SendNotificacionWhatsAppDocument::class, 1);
    Queue::assertPushed(SendNotificacionWhatsAppDocument::class, fn (SendNotificacionWhatsAppDocument $job): bool => $job->phone === '+584249998877'
        && str_contains($job->documentUrl, '/avisoDeCobro/ADP-')
        && str_contains($job->caption, 'Aviso de cobro Nro.'));
});

it('el envío del recibo por WhatsApp copia a los teléfonos testigo del Centro', function (): void {
    configureWitnesses(SystemNotificationKey::SaleReceiptWitnessCopy, [], [], ['0414-555-6677', '04143027250']);

    $recipients = SaleReciboPagoWhatsAppRecipients::resolve(new Sale(['invoice_number' => 'INV-WA-WIT']));

    expect(collect($recipients['targets'])->pluck('phone')->all())->toBe(['04145556677', '04143027250'])
        ->and($recipients['internal'])->toHaveCount(2)
        ->and($recipients['internal'][1]['name'])->toBe('Sol Rodriguez');

    configureWitnesses(SystemNotificationKey::SaleReceiptWitnessCopy, [], [], ['04145556677'], active: false);

    expect(SaleReciboPagoWhatsAppRecipients::resolve(new Sale(['invoice_number' => 'INV-WA-OFF']))['targets'])->toBe([]);
});

it('el Centro guarda el modo de cada correo y ante duplicados gana CCO', function (): void {
    $modes = (fn (array $items, array $emails): array => self::emailModesFromState($items, $emails))
        ->bindTo(null, ManageCompanyAssociateNotifications::class)(
            [
                ['email' => 'A@tdg.com', 'copy_mode' => 'cc'],
                ['email' => 'b@tdg.com', 'copy_mode' => 'bcc'],
                ['email' => 'a@tdg.com', 'copy_mode' => 'bcc'],
                ['email' => 'c@tdg.com'],
            ],
            ['a@tdg.com', 'b@tdg.com', 'c@tdg.com'],
        );

    expect($modes)->toBe(['a@tdg.com' => 'bcc', 'b@tdg.com' => 'bcc', 'c@tdg.com' => 'cc']);
});

it('las dos entradas nuevas están completas en el Centro y solo ellas usan CC/CCO', function (SystemNotificationKey $key): void {
    expect(SystemNotificationKey::managed())->toContain($key)
        ->and($key->label())->not->toBe('')
        ->and($key->description())->not->toBe('')
        ->and($key->heroTitle())->not->toBe('')
        ->and($key->heroBody())->not->toBe('')
        ->and($key->flowSteps())->toHaveCount(4)
        ->and($key->calloutBody())->not->toBe('')
        ->and($key->emptyRecipientsHint())->not->toBe('')
        ->and($key->activationHelp())->not->toBe('')
        ->and($key->savedRecipientsMessage(['x@tdg.com'], []))->not->toBe('')
        ->and($key->pausesScheduledTask())->toBeFalse()
        ->and($key->supportsEmailCopyModes())->toBeTrue()
        ->and($key->usesEmailRecipients())->toBeTrue();
})->with([SystemNotificationKey::SaleReceiptWitnessCopy, SystemNotificationKey::CollectionNoticeWitnessCopy]);

it('las demás notificaciones no muestran el modo de copia', function (): void {
    $others = array_filter(SystemNotificationKey::cases(), fn (SystemNotificationKey $key): bool => $key->supportsEmailCopyModes());

    expect(array_values($others))->toBe([SystemNotificationKey::SaleReceiptWitnessCopy, SystemNotificationKey::CollectionNoticeWitnessCopy])
        ->and(file_get_contents(app_path('Filament/Business/Pages/Schemas/SystemNotificationRecipientSettingsForm.php')))
        ->toContain("Select::make('copy_mode')")
        ->toContain('CCO · copia oculta');
});

describe('prueba antes de enviar', function (): void {
    it('el correo de prueba del recibo dice a quién llegaría y no copia a los testigos', function (): void {
        configureWitnesses(SystemNotificationKey::SaleReceiptWitnessCopy, ['visible@tdg.com', 'oculto@tdg.com'], ['oculto@tdg.com' => 'bcc'], ['04141234567']);

        $affiliate = DocumentEmailTemplate::factory()->create(['body' => '<p>Texto del copy</p>']);
        $responsible = DocumentEmailTemplate::factory()->forResponsible()->create();

        SaleReceiptEmailDelivery::deliver(witnessSale($this), $affiliate->id, $responsible->id, testMode: true, testEmail: 'analista@tdg.com');

        Mail::assertQueued(MailSaleReciboPago::class, 2);
        Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => $mail->hasTo('analista@tdg.com')
            && $mail->ccRecipients === []
            && $mail->bccRecipients === []
            && str_contains((string) $mail->bodyHtml, 'Correo de prueba · así llegará a «Afiliado»')
            && str_contains((string) $mail->bodyHtml, 'afiliada@example.com')
            && str_contains((string) $mail->bodyHtml, 'visible@tdg.com')
            && str_contains((string) $mail->bodyHtml, 'oculto@tdg.com')
            && str_contains((string) $mail->bodyHtml, '+584141234567')
            && str_contains((string) $mail->bodyHtml, 'Texto del copy'));
        Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => str_contains((string) $mail->bodyHtml, 'así llegará a «Agente / agencia»')
            && str_contains((string) $mail->bodyHtml, 'agente@example.com, agencia@example.com'));
        Queue::assertNotPushed(SendNotificacionWhatsAppDocument::class);
    });

    it('la prueba avisa cuando un destinatario no tiene correo, sin bloquear la prueba', function (): void {
        $affiliate = DocumentEmailTemplate::factory()->create();
        $sale = witnessSale($this);
        $sale->affiliate_email = null;

        SaleReceiptEmailDelivery::deliver($sale, $affiliate->id, null, testMode: true, testEmail: 'analista@tdg.com');

        Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => str_contains((string) $mail->bodyHtml, 'sin correo válido: no se le enviaría'));
    });

    it('el envío real no lleva el aviso de prueba', function (): void {
        $affiliate = DocumentEmailTemplate::factory()->create();

        SaleReceiptEmailDelivery::deliver(witnessSale($this), $affiliate->id, null);

        Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => ! str_contains((string) $mail->bodyHtml, 'Correo de prueba')
            && ! str_starts_with((string) $mail->subjectLine, '[PRUEBA]'));
    });

    it('la prueba del aviso lleva el aviso de prueba y los métodos de pago si se marcan', function (): void {
        $template = DocumentEmailTemplate::factory()->collectionNotice()->create();
        $collection = new Collection([
            'collection_invoice_number' => 'AVC-TEST-'.uniqid(),
            'affiliate_email' => 'afiliado@example.com',
        ]);
        $path = CollectionNotice::pdfPath($collection);
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, '%PDF-1.4 aviso');
        $this->pdfPaths[] = $path;

        CollectionNoticeEmailDelivery::deliver($collection, $template->id, null, attachPaymentMethods: false, testMode: true, testEmail: 'analista@tdg.com');

        Mail::assertQueued(CollectionNoticeCopyMail::class, fn (CollectionNoticeCopyMail $mail): bool => $mail->hasTo('analista@tdg.com')
            && ! $mail->hasTo('afiliado@example.com')
            && $mail->ccRecipients === []
            && str_starts_with($mail->subjectLine, '[PRUEBA] ')
            && str_contains($mail->bodyHtml, 'afiliado@example.com'));
    });

    it('la ruta del recibo acepta el correo de prueba', function (): void {
        $affiliate = DocumentEmailTemplate::factory()->create();
        $sale = witnessSale($this);

        $request = Mockery::mock(App\Http\Requests\SendReciboPagoEmailRequest::class);
        $request->shouldReceive('validated')->andReturn(['affiliate_template_id' => $affiliate->id, 'test_email' => 'analista@tdg.com']);

        $response = (new App\Http\Controllers\ReciboPagoController)->sendEmail($request, $sale);

        expect($response->getStatusCode())->toBe(200)
            ->and((string) $response->getData(true)['message'])->toContain('Prueba enviada a analista@tdg.com');

        Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => $mail->hasTo('analista@tdg.com') && ! $mail->hasTo('afiliada@example.com'));
    });

    it('los dos modales tienen la acción «Probar antes de enviar» con el correo del analista', function (): void {
        $receipt = file_get_contents(resource_path('views/filament/administration/sales/recibo-pago-preview-modal.blade.php'));
        $notice = file_get_contents(resource_path('views/filament/administration/collections/aviso-cobro-preview-modal.blade.php'));
        $partial = file_get_contents(resource_path('views/filament/administration/document-emails/test-send.blade.php'));

        expect($receipt)->toContain('document-emails.test-send')->toContain('testEmailDefault')
            ->and($notice)->toContain('document-emails.test-send')->toContain('testEmailDefault')
            ->and($partial)->toContain('Probar antes de enviar')->toContain('x-on:click="sendTest()"')->not->toContain('type="submit"');
    });
});
