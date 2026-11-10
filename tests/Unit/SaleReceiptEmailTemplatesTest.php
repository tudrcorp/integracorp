<?php

declare(strict_types=1);

use App\Enums\DocumentEmailAudience;
use App\Enums\DocumentEmailType;
use App\Filament\Administration\Resources\SaleReceiptEmailTemplates\SaleReceiptEmailTemplateResource;
use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Mail\MailSaleReciboPago;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\DocumentEmailTemplate;
use App\Models\Sale;
use App\Models\User;
use App\Support\DocumentEmails\DocumentEmailTemplateRenderer;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Sales\SaleReceiptEmailDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    config(['cache.default' => 'array']);
    Mail::fake();
    Illuminate\Support\Facades\Queue::fake();
    App\Models\SystemNotificationRecipientSetting::query()
        ->whereIn('notification_key', ['sale_receipt_witness_copy', 'collection_notice_witness_copy'])
        ->delete();

    /** Los copys sembrados por la migración no deben interferir con los de cada prueba. */
    DocumentEmailTemplate::query()->update(['is_active' => false, 'is_default' => false]);
});

afterEach(function (): void {
    foreach ($this->pdfPaths ?? [] as $path) {
        @unlink($path);
    }

    DB::rollBack();
});

/**
 * Venta sin guardar con afiliado, agente y agencia, y su PDF en disco.
 */
function receiptSale(object $test, array $overrides = []): Sale
{
    $sale = new Sale(array_merge([
        'type' => 'AFILIACION INDIVIDUAL',
        'invoice_number' => 'RDP-COPY-'.uniqid(),
        'affiliate_full_name' => 'Margarita <Torres>',
        'affiliate_email' => 'afiliada@example.com',
        'total_amount' => 163.5,
        'payment_method' => 'ZELLE',
        'agent_id' => 10,
        'code_agency' => 'AC-001',
    ], $overrides));
    $sale->id = random_int(900000000, 999999999);

    $sale->setRelation('agent', new Agent(['name' => 'Agente Demo', 'email' => 'agente@example.com']));
    $sale->setRelation('agency', new Agency(['code' => 'AC-001', 'name_corporative' => 'Agencia Demo', 'email' => 'agencia@example.com']));
    $sale->setRelation('plan', null);

    $directory = public_path('storage/reciboDePago');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = SalesTable::reciboPagoPdfPath($sale);
    file_put_contents($path, '%PDF-1.4 test');
    $test->pdfPaths[] = $path;

    return $sale;
}

it('sanea el cuerpo: quita scripts, estilos y atributos y conserva el formato de texto', function (): void {
    $html = '<p style="color:red" onclick="x()">Hola <strong class="a">mundo</strong></p>'
        .'<script>alert(1)</script><img src="x" onerror="y()"><h1>Titulo</h1>'
        .'<a href="javascript:alert(1)">malo</a><a href="https://tudrencasa.com" target="_blank">bueno</a>';

    $clean = DocumentEmailTemplateRenderer::sanitizeBody($html);

    expect($clean)
        ->toContain('<p>Hola <strong>mundo</strong></p>')
        ->toContain('<a href="https://tudrencasa.com"')
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('<img')
        ->not->toContain('<h1>')
        ->not->toContain('javascript:');
});

it('reemplaza las variables escapando los valores y respeta las desconocidas', function (): void {
    $template = new DocumentEmailTemplate([
        'subject' => 'Recibo {nro_recibo} de { afiliado }',
        'body' => '<p>Estimado(a) {afiliado}, monto {monto}. {inventada}</p>',
    ]);

    $rendered = DocumentEmailTemplateRenderer::render($template, [
        'afiliado' => 'Ana <b>',
        'nro_recibo' => '11-1',
        'monto' => 'US$10.00',
    ]);

    expect($rendered['subject'])->toBe('Recibo 11-1 de Ana')
        ->and($rendered['body_html'])->toContain('Ana &lt;b&gt;')
        ->and($rendered['body_html'])->toContain('US$10.00')
        ->and($rendered['body_html'])->toContain('{inventada}')
        ->and($rendered['body_html'])->toContain('<p style="margin:0 0 14px 0;">');
});

it('detecta variables mal escritas según el documento', function (): void {
    expect(DocumentEmailTemplateRenderer::unknownVariables('{nro_aviso} {periodo_desde}', DocumentEmailType::ReceiptPayment))
        ->toBe(['nro_aviso', 'periodo_desde'])
        ->and(DocumentEmailTemplateRenderer::unknownVariables('{nro_aviso} {periodo_desde}', DocumentEmailType::CollectionNotice))
        ->toBe([]);

    expect(DocumentEmailTemplateRenderer::unknownVariables('Hola {afiliado} {nombre} {Monto} {nombre}', DocumentEmailType::ReceiptPayment))
        ->toBe(['nombre']);
});

it('define para qué destinatario sirve cada audiencia', function (): void {
    expect(DocumentEmailAudience::Affiliate->servesRecipient(DocumentEmailAudience::Affiliate))->toBeTrue()
        ->and(DocumentEmailAudience::Affiliate->servesRecipient(DocumentEmailAudience::Responsible))->toBeFalse()
        ->and(DocumentEmailAudience::Both->servesRecipient(DocumentEmailAudience::Responsible))->toBeTrue();
});

it('deja un solo predeterminado por audiencia y quita el predeterminado al desactivar', function (): void {
    $first = DocumentEmailTemplate::factory()->create(['is_default' => true]);
    $second = DocumentEmailTemplate::factory()->create(['is_default' => true]);
    $responsible = DocumentEmailTemplate::factory()->forResponsible()->create(['is_default' => true]);

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($second->refresh()->is_default)->toBeTrue()
        ->and($responsible->refresh()->is_default)->toBeTrue();

    $second->update(['is_active' => false]);

    expect($second->refresh()->is_default)->toBeFalse();
});

it('sanea el cuerpo también al guardar', function (): void {
    $template = DocumentEmailTemplate::factory()->create(['body' => '<p>Hola</p><script>alert(1)</script>']);

    expect($template->refresh()->body)->toBe('<p>Hola</p>');
});

it('ofrece por destinatario solo los copys activos de su audiencia, con el predeterminado preseleccionado', function (): void {
    $affiliate = DocumentEmailTemplate::factory()->create(['name' => 'Copy afiliado', 'is_default' => true]);
    $both = DocumentEmailTemplate::factory()->forBoth()->create(['name' => 'Copy ambos']);
    $responsible = DocumentEmailTemplate::factory()->forResponsible()->create(['name' => 'Copy responsable', 'is_default' => true]);
    DocumentEmailTemplate::factory()->inactive()->create(['name' => 'Copy viejo']);

    $options = SaleReceiptEmailDelivery::options(receiptSale($this));

    $affiliateIds = array_column($options['affiliate']['templates'], 'id');
    $responsibleIds = array_column($options['responsible']['templates'], 'id');

    expect($affiliateIds)->toContain($affiliate->id, $both->id)->not->toContain($responsible->id)
        ->and($responsibleIds)->toContain($responsible->id, $both->id)->not->toContain($affiliate->id)
        ->and($options['affiliate']['default_id'])->toBe($affiliate->id)
        ->and($options['responsible']['default_id'])->toBe($responsible->id)
        ->and($options['affiliate']['emails'])->toBe(['afiliada@example.com'])
        ->and($options['responsible']['emails'])->toBe(['agente@example.com', 'agencia@example.com'])
        ->and(collect($options['affiliate']['templates'])->pluck('name'))->not->toContain('Copy viejo');
});

it('encola un correo para el afiliado y otro para el agente y la agencia, cada uno con su copy', function (): void {
    $affiliate = DocumentEmailTemplate::factory()->create([
        'subject' => 'Su recibo {nro_recibo}',
        'body' => '<p>Estimado(a) afiliado(a): {afiliado}</p>',
    ]);
    $responsible = DocumentEmailTemplate::factory()->forResponsible()->create([
        'subject' => 'Recibo de su cliente {nro_recibo}',
        'body' => '<p><strong>Estimada Agencia, agente:</strong></p>',
    ]);

    $sale = receiptSale($this);

    $deliveries = SaleReceiptEmailDelivery::deliver($sale, $affiliate->id, $responsible->id);

    expect($deliveries)->toHaveCount(2);

    Mail::assertQueued(MailSaleReciboPago::class, 2);

    Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => $mail->hasTo('afiliada@example.com')
        && ! $mail->hasTo('agente@example.com')
        && $mail->hasCc('administracion@tudrencasa.com')
        && $mail->subjectLine === 'Su recibo '.$sale->invoice_number
        && str_contains((string) $mail->bodyHtml, 'Margarita &lt;Torres&gt;'));

    Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => $mail->hasTo('agente@example.com')
        && $mail->hasTo('agencia@example.com')
        && ! $mail->hasTo('afiliada@example.com')
        && $mail->hasCc('afiliaciones@tudrencasa.com')
        && str_contains((string) $mail->bodyHtml, 'Estimada Agencia, agente:'));
});

it('permite omitir un destinatario', function (): void {
    $responsible = DocumentEmailTemplate::factory()->forResponsible()->create();

    SaleReceiptEmailDelivery::deliver(receiptSale($this), null, $responsible->id);

    Mail::assertQueued(MailSaleReciboPago::class, 1);
    Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => ! $mail->hasTo('afiliada@example.com'));
});

it('no envía nada si falta el correo de un destinatario elegido', function (): void {
    $affiliate = DocumentEmailTemplate::factory()->create();
    $responsible = DocumentEmailTemplate::factory()->forResponsible()->create();

    $sale = receiptSale($this, ['affiliate_email' => 'no-es-correo']);

    expect(fn () => SaleReceiptEmailDelivery::deliver($sale, $affiliate->id, $responsible->id))
        ->toThrow(RuntimeException::class, 'no tiene un correo válido del afiliado');

    Mail::assertNothingQueued();
});

it('rechaza copys inactivos o de otra audiencia', function (): void {
    $inactive = DocumentEmailTemplate::factory()->inactive()->create();
    $responsibleOnly = DocumentEmailTemplate::factory()->forResponsible()->create();
    $sale = receiptSale($this);

    expect(fn () => SaleReceiptEmailDelivery::deliver($sale, $inactive->id, null))
        ->toThrow(RuntimeException::class, 'ya no está disponible')
        ->and(fn () => SaleReceiptEmailDelivery::deliver($sale, $responsibleOnly->id, null))
        ->toThrow(RuntimeException::class, 'ya no está disponible');

    Mail::assertNothingQueued();
});

it('exige al menos un copy y el PDF generado', function (): void {
    $sale = receiptSale($this);

    expect(fn () => SaleReceiptEmailDelivery::deliver($sale, null, null))
        ->toThrow(RuntimeException::class, 'al menos un destinatario');

    $withoutPdf = new Sale(['invoice_number' => 'RDP-SIN-PDF-'.uniqid()]);
    $template = DocumentEmailTemplate::factory()->create();

    expect(fn () => SaleReceiptEmailDelivery::deliver($withoutPdf, $template->id, null))
        ->toThrow(RuntimeException::class, 'No existe el PDF');

    Mail::assertNothingQueued();
});

it('en modo prueba envía todos los copys elegidos solo al correo de prueba y sin copias', function (): void {
    $affiliate = DocumentEmailTemplate::factory()->create();
    $responsible = DocumentEmailTemplate::factory()->forResponsible()->create();

    SaleReceiptEmailDelivery::deliver(
        receiptSale($this, ['affiliate_email' => null]),
        $affiliate->id,
        $responsible->id,
        testMode: true,
        testEmail: 'Prueba@Example.com',
    );

    Mail::assertQueued(MailSaleReciboPago::class, 2);
    Mail::assertQueued(MailSaleReciboPago::class, fn (MailSaleReciboPago $mail): bool => $mail->hasTo('prueba@example.com')
        && $mail->ccRecipients === []
        && str_starts_with((string) $mail->subjectLine, '[PRUEBA] '));
});

it('rechaza el mismo envío repetido en segundos (doble clic)', function (): void {
    $affiliate = DocumentEmailTemplate::factory()->create();
    $sale = receiptSale($this);

    SaleReceiptEmailDelivery::deliver($sale, $affiliate->id, null);

    expect(fn () => SaleReceiptEmailDelivery::deliver($sale, $affiliate->id, null))
        ->toThrow(RuntimeException::class, 'se acaba de enviar');

    Mail::assertQueued(MailSaleReciboPago::class, 1);
});

it('renderiza el correo con el copy y conserva el texto histórico sin copy', function (): void {
    $withCopy = new MailSaleReciboPago('11-1', '/tmp/x.pdf', [], 'Asunto copy', '<p>Texto del copy</p>');
    $legacy = new MailSaleReciboPago('11-1', '/tmp/x.pdf');

    expect($withCopy->envelope()->subject)->toBe('Asunto copy')
        ->and($withCopy->render())->toContain('Texto del copy')
        ->and($legacy->envelope()->subject)->toBe('Recibo de pago Nro. 11-1')
        ->and($legacy->content()->view)->toBe('mails.aviso-de-pago');
});

it('muestra la sección de envío por correo debajo de la vista previa', function (): void {
    $template = DocumentEmailTemplate::factory()->create(['name' => 'Copy In House de prueba']);
    $sale = receiptSale($this);

    $html = view('filament.administration.sales.recibo-pago-preview-modal', [
        'sale' => $sale,
        'emailOptions' => SaleReceiptEmailDelivery::options($sale),
    ])->render();

    expect($html)
        ->toContain('Envío por correo')
        ->toContain('Copy In House de prueba')
        ->toContain('No enviar')
        ->toContain('Enviar recibo por correo')
        ->toContain('sendUrl');

    $withoutOptions = view('filament.administration.sales.recibo-pago-preview-modal', ['sale' => $sale])->render();

    expect($withoutOptions)->not->toContain('Envío por correo');
});

it('registra la ruta de envío y rechaza a quien no entra a Ventas', function (): void {
    expect(Route::has('administration.sales.recibo-pago.send-email'))->toBeTrue();

    $saleId = Sale::query()->value('id');

    if ($saleId === null) {
        $this->markTestSkipped('No hay ventas en la base para probar la ruta.');
    }

    $outsider = User::factory()->make(['email' => 'externo@example.com', 'is_admin' => false]);
    $outsider->id = 999999999;

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($outsider)
        ->postJson(route('administration.sales.recibo-pago.send-email', $saleId), ['affiliate_template_id' => 1])
        ->assertForbidden();

    Mail::assertNothingQueued();
});

it('registra el permiso de menú y el de gestión como asignables en Administración', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(SaleReceiptEmailTemplateResource::class))->toBe(['copys-recibo-de-pago'])
        ->and(BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule(
            BusinessFilamentActionPermissionRegistry::MANAGE_SALE_RECEIPT_EMAIL_TEMPLATES,
            'ADMINISTRACION',
        ))->toBeTrue();
});

it('sin sesión no se pueden crear ni editar copys, y nunca se eliminan', function (): void {
    $template = DocumentEmailTemplate::factory()->make();

    expect(SaleReceiptEmailTemplateResource::canCreate())->toBeFalse()
        ->and(SaleReceiptEmailTemplateResource::canEdit($template))->toBeFalse()
        ->and(SaleReceiptEmailTemplateResource::canDelete($template))->toBeFalse();
});
