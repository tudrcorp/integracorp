<?php

declare(strict_types=1);

use App\Http\Controllers\AvisoCobroController;
use App\Http\Requests\SendAvisoCobroEmailRequest;
use App\Mail\CollectionNoticeCopyMail;
use App\Models\Collection;
use App\Models\DocumentEmailTemplate;
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
});

afterEach(function (): void {
    DB::rollBack();
});

function avisoSendCollection(string $invoice, string $email): Collection
{
    $collection = new Collection;
    $collection->collection_invoice_number = $invoice;
    $collection->affiliate_full_name = 'John Doe';
    $collection->affiliate_email = $email;

    $pdfPath = public_path('storage/avisoDeCobro/ADP-'.$invoice.'.pdf');
    @mkdir(dirname($pdfPath), 0777, true);
    file_put_contents($pdfPath, 'dummy pdf');

    return $collection;
}

it('envía al correo del afiliado con el copy elegido y copia a administración', function (): void {
    $template = DocumentEmailTemplate::factory()->collectionNotice()->create();
    $collection = avisoSendCollection('AVS-'.uniqid(), 'john@example.com');

    $request = Mockery::mock(SendAvisoCobroEmailRequest::class);
    $request->shouldReceive('validated')->andReturn(['affiliate_template_id' => $template->id]);

    $response = (new AvisoCobroController)->sendEmail($request, $collection);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['ok'])->toBeTrue()
        ->and((string) $response->getData(true)['message'])->toContain('john@example.com');

    Mail::assertQueued(CollectionNoticeCopyMail::class, fn (CollectionNoticeCopyMail $mail): bool => $mail->queue === 'default'
        && $mail->hasTo('john@example.com')
        && $mail->hasCc('administracion@tudrencasa.com'));

    @unlink(public_path('storage/avisoDeCobro/ADP-'.$collection->collection_invoice_number.'.pdf'));
});

it('con correo de prueba envía solo a ese correo, sin copia a administración', function (): void {
    $template = DocumentEmailTemplate::factory()->collectionNotice()->create();
    $collection = avisoSendCollection('AVS-'.uniqid(), 'jane@example.com');

    $request = Mockery::mock(SendAvisoCobroEmailRequest::class);
    $request->shouldReceive('validated')->andReturn([
        'affiliate_template_id' => $template->id,
        'test_email' => 'other@example.com',
    ]);

    $response = (new AvisoCobroController)->sendEmail($request, $collection);

    expect($response->getStatusCode())->toBe(200);

    Mail::assertQueued(CollectionNoticeCopyMail::class, fn (CollectionNoticeCopyMail $mail): bool => $mail->hasTo('other@example.com')
        && ! $mail->hasTo('jane@example.com')
        && ! $mail->hasCc('administracion@tudrencasa.com'));

    @unlink(public_path('storage/avisoDeCobro/ADP-'.$collection->collection_invoice_number.'.pdf'));
});

it('responde 422 con un mensaje claro si no se puede enviar', function (): void {
    $collection = avisoSendCollection('AVS-'.uniqid(), 'john@example.com');

    $request = Mockery::mock(SendAvisoCobroEmailRequest::class);
    $request->shouldReceive('validated')->andReturn([]);

    $response = (new AvisoCobroController)->sendEmail($request, $collection);

    expect($response->getStatusCode())->toBe(422)
        ->and((string) $response->getData(true)['message'])->toContain('al menos un destinatario');

    Mail::assertNothingQueued();

    @unlink(public_path('storage/avisoDeCobro/ADP-'.$collection->collection_invoice_number.'.pdf'));
});
