<?php

declare(strict_types=1);

use App\Enums\DocumentEmailType;
use App\Filament\Administration\Resources\CollectionNoticeEmailTemplates\CollectionNoticeEmailTemplateResource;
use App\Filament\Administration\Resources\SaleReceiptEmailTemplates\SaleReceiptEmailTemplateResource;
use App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplateDestinations;
use App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplateFormSchema;
use App\Mail\CollectionNoticeCopyMail;
use App\Models\Collection;
use App\Models\CollectionNoticeRegeneration;
use App\Models\DocumentEmailTemplate;
use App\Models\User;
use App\Support\Collections\CollectionNotice;
use App\Support\Collections\CollectionNoticeAccess;
use App\Support\Collections\CollectionNoticeEmailDelivery;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Storefront\StorefrontPaymentMethodsDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    config(['cache.default' => 'array']);
    Mail::fake();
    Illuminate\Support\Facades\Queue::fake();
    App\Models\SystemNotificationRecipientSetting::query()
        ->whereIn('notification_key', ['sale_receipt_witness_copy', 'collection_notice_witness_copy'])
        ->delete();

    /** Los copys sembrados por las migraciones no deben interferir con los de cada prueba. */
    DocumentEmailTemplate::query()->update(['is_active' => false, 'is_default' => false]);
});

afterEach(function (): void {
    foreach ($this->pdfPaths ?? [] as $path) {
        @unlink($path);
    }

    DB::rollBack();
});

function noticeEmailCollection(object $test, array $attributes = []): Collection
{
    $collection = new Collection(array_merge([
        'collection_invoice_number' => 'AVC-'.uniqid(),
        'affiliate_full_name' => 'Alberto <Martinez>',
        'affiliate_email' => 'afiliado@example.com',
        'total_amount' => 80,
        'payment_frequency' => 'SEMESTRAL',
        'type' => 'AFILIACION INDIVIDUAL',
    ], $attributes));

    $path = CollectionNotice::pdfPath($collection);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, '%PDF-1.4 aviso');
    $test->pdfPaths[] = $path;

    return $collection;
}

it('toma del PDF regenerado el período, la emisión, el nombre y el correo del afiliado', function (): void {
    $collection = noticeEmailCollection($this);

    $regeneration = new CollectionNoticeRegeneration([
        'client' => ['full_name' => 'NOMBRE EDITADO', 'email' => 'Editado@Example.com'],
        'emission_date' => '2026-11-09',
        'period_from' => '2026-11-03',
        'period_to' => '2027-05-02',
    ]);

    $recipients = CollectionNoticeEmailDelivery::recipients($collection, $regeneration);
    $values = CollectionNoticeEmailDelivery::values($collection, $regeneration, $recipients);

    expect($recipients['affiliate_email'])->toBe('editado@example.com')
        ->and($values['afiliado'])->toBe('NOMBRE EDITADO')
        ->and($values['periodo_desde'])->toBe('03/11/2026')
        ->and($values['periodo_hasta'])->toBe('02/05/2027')
        ->and($values['fecha_emision'])->toBe('09/11/2026')
        ->and($values['monto'])->toBe('US$80.00')
        ->and($values['frecuencia'])->toBe('SEMESTRAL');
});

it('sin regeneración usa el correo de la cuota', function (): void {
    $recipients = CollectionNoticeEmailDelivery::recipients(noticeEmailCollection($this), null);

    expect($recipients['affiliate_email'])->toBe('afiliado@example.com')
        ->and($recipients['responsible_emails'])->toBe([]);
});

it('solo ofrece copys del aviso de cobro, nunca los del recibo', function (): void {
    $notice = DocumentEmailTemplate::factory()->collectionNotice()->create(['name' => 'Aviso In House', 'is_default' => true]);
    $receipt = DocumentEmailTemplate::factory()->create(['name' => 'Recibo In House', 'is_default' => true]);

    $options = CollectionNoticeEmailDelivery::options(noticeEmailCollection($this), new CollectionNoticeRegeneration([
        'period_from' => '2026-11-03',
        'period_to' => '2027-05-02',
    ]));

    $ids = array_column($options['affiliate']['templates'], 'id');

    expect($ids)->toContain($notice->id)->not->toContain($receipt->id)
        ->and($options['affiliate']['default_id'])->toBe($notice->id)
        ->and($options['cc_emails'])->toBe(['administracion@tudrencasa.com'])
        ->and($options['payment_methods_available'])->toBe(StorefrontPaymentMethodsDocument::exists());
});

it('envía el aviso con el copy, el PDF y los métodos de pago cuando el analista lo marca', function (): void {
    if (! StorefrontPaymentMethodsDocument::exists()) {
        $this->markTestSkipped('El PDF de métodos de pago no está cargado en este entorno.');
    }

    $template = DocumentEmailTemplate::factory()->collectionNotice()->create([
        'subject' => 'Aviso {nro_aviso}',
        'body' => '<p>Estimado(a) {afiliado}, monto {monto}.</p>',
    ]);
    $collection = noticeEmailCollection($this);

    $deliveries = CollectionNoticeEmailDelivery::deliver($collection, $template->id, null, attachPaymentMethods: true);

    expect($deliveries[0]['payment_methods'])->toBeTrue();

    Mail::assertQueued(CollectionNoticeCopyMail::class, fn (CollectionNoticeCopyMail $mail): bool => $mail->hasTo('afiliado@example.com')
        && $mail->hasCc('administracion@tudrencasa.com')
        && $mail->subjectLine === 'Aviso '.$collection->collection_invoice_number
        && str_contains($mail->bodyHtml, 'Alberto &lt;Martinez&gt;')
        && $mail->paymentMethodsPdfPath !== null
        && is_file($mail->paymentMethodsPdfPath)
        && count($mail->attachments()) === 2);
});

it('sin marcar el check no adjunta los métodos de pago', function (): void {
    $template = DocumentEmailTemplate::factory()->collectionNotice()->create();

    CollectionNoticeEmailDelivery::deliver(noticeEmailCollection($this), $template->id, null, attachPaymentMethods: false);

    Mail::assertQueued(CollectionNoticeCopyMail::class, fn (CollectionNoticeCopyMail $mail): bool => $mail->paymentMethodsPdfPath === null
        && count($mail->attachments()) === 1);
});

it('rechaza un copy del recibo de pago aunque llegue por la ruta del aviso', function (): void {
    $receipt = DocumentEmailTemplate::factory()->create();

    expect(fn () => CollectionNoticeEmailDelivery::deliver(noticeEmailCollection($this), $receipt->id, null))
        ->toThrow(RuntimeException::class, 'ya no está disponible');

    Mail::assertNothingQueued();
});

it('no envía nada si el agente / agencia elegido no tiene correo', function (): void {
    $affiliate = DocumentEmailTemplate::factory()->collectionNotice()->create();
    $responsible = DocumentEmailTemplate::factory()->collectionNotice()->forResponsible()->create();

    expect(fn () => CollectionNoticeEmailDelivery::deliver(noticeEmailCollection($this), $affiliate->id, $responsible->id))
        ->toThrow(RuntimeException::class, 'Ni el agente ni la agencia');

    Mail::assertNothingQueued();
});

it('exige el PDF del aviso generado', function (): void {
    $template = DocumentEmailTemplate::factory()->collectionNotice()->create();
    $collection = new Collection(['collection_invoice_number' => 'AVC-SIN-PDF-'.uniqid()]);

    expect(fn () => CollectionNoticeEmailDelivery::deliver($collection, $template->id, null))
        ->toThrow(RuntimeException::class, 'No existe el PDF del aviso');
});

it('cada pantalla de copys solo ve los de su documento', function (): void {
    DocumentEmailTemplate::factory()->collectionNotice()->create();
    DocumentEmailTemplate::factory()->create();

    expect(CollectionNoticeEmailTemplateResource::getEloquentQuery()->pluck('document')->unique()->values()->all())
        ->toBe([DocumentEmailType::CollectionNotice])
        ->and(SaleReceiptEmailTemplateResource::getEloquentQuery()->pluck('document')->unique()->values()->all())
        ->toBe([DocumentEmailType::ReceiptPayment]);
});

it('registra permisos propios del aviso, separados de los del recibo', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(CollectionNoticeEmailTemplateResource::class))->toBe(['copys-aviso-de-cobro'])
        ->and(BusinessFilamentActionPermissionRegistry::MANAGE_COLLECTION_NOTICE_EMAIL_TEMPLATES)
        ->not->toBe(BusinessFilamentActionPermissionRegistry::MANAGE_SALE_RECEIPT_EMAIL_TEMPLATES)
        ->and(BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule(
            BusinessFilamentActionPermissionRegistry::MANAGE_COLLECTION_NOTICE_EMAIL_TEMPLATES,
            'ADMINISTRACION',
        ))->toBeTrue()
        ->and(CollectionNoticeEmailTemplateResource::canCreate())->toBeFalse()
        ->and(CollectionNoticeAccess::allows(null))->toBeFalse();
});

it('el modal oculta la vista previa, ofrece el check de métodos de pago y no anida formularios', function (): void {
    $view = file_get_contents(resource_path('views/filament/administration/collections/aviso-cobro-preview-modal.blade.php'));
    $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $view);

    expect($markup)
        ->toContain('Ver vista previa')
        ->toContain('x-if="showPreview"')
        ->toContain('Adjuntar métodos de pago')
        ->toContain("@include('filament.administration.document-emails.test-send'")
        ->not->toContain('x-model="testMode"')
        ->toContain('confirmationLines()')
        ->not->toContain('<form')
        ->not->toContain('type="submit"');
});

it('Cobranza Por Mes tiene la misma acción del aviso que Gestión de Cobranza', function (): void {
    $annual = file_get_contents(app_path('Filament/Administration/Resources/AnnualCollections/Tables/AnnualCollectionsTable.php'));
    $collections = file_get_contents(app_path('Filament/Administration/Resources/Collections/Tables/CollectionsTable.php'));

    expect($annual)->toContain('CollectionsTable::avisoCobroAction(')
        ->and($collections)->toContain('self::avisoCobroAction()');
});

describe('campo «Se usará en» del copy', function (): void {
    it('ofrece ambos documentos a quien gestiona los dos y ninguno a quien no tiene permiso', function (): void {
        $this->actingAs(User::factory()->make(['departament' => ['SUPERADMIN']]));

        expect(DocumentEmailTemplateFormSchema::documentOptions())
            ->toHaveKeys([DocumentEmailType::ReceiptPayment->value, DocumentEmailType::CollectionNotice->value]);

        $this->actingAs(User::factory()->make(['departament' => ['ADMINISTRACION']]));

        expect(DocumentEmailTemplateFormSchema::documentOptions())->toBe([]);

        $receipt = DocumentEmailTemplate::factory()->make();

        expect(DocumentEmailTemplateFormSchema::documentOptions($receipt))
            ->toBe([DocumentEmailType::ReceiptPayment->value => 'Envío de recibos de pago']);
    });

    it('guarda en el documento elegido solo si el usuario puede gestionarlo', function (): void {
        $this->actingAs(User::factory()->make(['departament' => ['SUPERADMIN']]));

        expect(DocumentEmailTemplateDestinations::allowedDocument(['document' => 'AVISO_COBRO'], DocumentEmailType::ReceiptPayment))
            ->toBe(DocumentEmailType::CollectionNotice)
            ->and(DocumentEmailTemplateDestinations::allowedDocument([], DocumentEmailType::ReceiptPayment))
            ->toBe(DocumentEmailType::ReceiptPayment)
            ->and(DocumentEmailTemplateDestinations::allowedDocument(['document' => 'INVENTADO'], DocumentEmailType::ReceiptPayment))
            ->toBeNull();

        $this->actingAs(User::factory()->make(['departament' => ['ADMINISTRACION']]));

        expect(DocumentEmailTemplateDestinations::allowedDocument(['document' => 'AVISO_COBRO'], DocumentEmailType::ReceiptPayment))
            ->toBeNull();
    });

    it('avisa cuando el copy queda en la otra pantalla', function (): void {
        expect(DocumentEmailTemplateDestinations::savedMessage(DocumentEmailType::CollectionNotice, DocumentEmailType::ReceiptPayment))
            ->toContain('Copys de avisos de cobro')
            ->and(DocumentEmailTemplateDestinations::savedMessage(DocumentEmailType::ReceiptPayment, DocumentEmailType::ReceiptPayment))
            ->toBeNull()
            ->and(DocumentEmailTemplateDestinations::resourceFor(DocumentEmailType::CollectionNotice))
            ->toBe(CollectionNoticeEmailTemplateResource::class);
    });

    it('el formulario tiene el campo y valida variables según el documento elegido', function (): void {
        $source = file_get_contents(app_path('Filament/Shared/DocumentEmailTemplates/DocumentEmailTemplateFormSchema.php'));

        expect($source)
            ->toContain("Select::make('document')")
            ->toContain("->label('Se usará en')")
            ->toContain('self::selectedDocument($get, $fallback)')
            ->toContain("\$rule->where('document', \$selected(\$get)->value)");
    });
});

describe('encabezados de las pantallas de copys', function (): void {
    it('crear dice dónde se usará y la ficha muestra el estado del copy', function (): void {
        $create = App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplatePageHeader::forCreate(DocumentEmailType::ReceiptPayment)->toHtml();

        $template = DocumentEmailTemplate::factory()->collectionNotice()->inactive()->make(['name' => 'Aviso viejo', 'subject' => 'Aviso {nro_aviso}']);
        $record = App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplatePageHeader::forRecord($template, 'Ficha del copy')->toHtml();

        expect($create)->toContain('Se usará en el envío de recibos de pago')
            ->and($record)->toContain('Aviso viejo')
            ->toContain('Asunto: Aviso {nro_aviso}')
            ->toContain('Inactivo · no aparece al enviar')
            ->toContain('Aviso de cobro');
    });
});

it('el listado y la edición de copys no llevan encabezado extra', function (): void {
    foreach (['SaleReceiptEmailTemplates/Pages/ListSaleReceiptEmailTemplates', 'SaleReceiptEmailTemplates/Pages/EditSaleReceiptEmailTemplate', 'CollectionNoticeEmailTemplates/Pages/ListCollectionNoticeEmailTemplates', 'CollectionNoticeEmailTemplates/Pages/EditCollectionNoticeEmailTemplate'] as $page) {
        expect(file_get_contents(app_path('Filament/Administration/Resources/'.$page.'.php')))->not->toContain('getSubheading');
    }
});
