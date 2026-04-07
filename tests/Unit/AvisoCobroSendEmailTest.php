<?php

declare(strict_types=1);

use App\Http\Controllers\AvisoCobroController;
use App\Http\Requests\SendAvisoCobroEmailRequest;
use App\Mail\AvisoCobroCertificateEmail;
use App\Models\Collection;
use Illuminate\Support\Facades\Mail;
use Mockery;

uses(Tests\TestCase::class);

it('envia al correo del afiliado con CC administracion cuando el email viene vacio', function (): void {
    Mail::fake();

    $collection = new Collection;
    $collection->collection_invoice_number = '001';
    $collection->affiliate_full_name = 'John Doe';
    $collection->affiliate_email = 'john@example.com';

    $pdfPath = public_path('storage/avisoDeCobro/ADP-001.pdf');
    @mkdir(dirname($pdfPath), 0777, true);
    file_put_contents($pdfPath, 'dummy pdf');

    $request = Mockery::mock(SendAvisoCobroEmailRequest::class);
    $request->shouldReceive('validated')->andReturn([]);

    $controller = new AvisoCobroController;
    $response = $controller->sendEmail($request, $collection);

    expect($response->getStatusCode())->toBe(200);
    $payload = $response->getData(true);
    expect($payload['ok'])->toBeTrue();
    expect((string) $payload['message'])->toContain('Adjuntamos');

    Mail::assertQueued(AvisoCobroCertificateEmail::class, function (AvisoCobroCertificateEmail $mail) use ($collection): bool {
        return $mail->queue === 'default'
            && $mail->hasTo($collection->affiliate_email)
            && $mail->hasCc('administracion@tudrencasa.com');
    });
});

it('envia al correo indicado (sin CC admin) cuando el email viene lleno', function (): void {
    Mail::fake();

    $collection = new Collection;
    $collection->collection_invoice_number = '002';
    $collection->affiliate_full_name = 'Jane Doe';
    $collection->affiliate_email = 'jane@example.com';

    $pdfPath = public_path('storage/avisoDeCobro/ADP-002.pdf');
    @mkdir(dirname($pdfPath), 0777, true);
    file_put_contents($pdfPath, 'dummy pdf');

    $request = Mockery::mock(SendAvisoCobroEmailRequest::class);
    $request->shouldReceive('validated')->andReturn(['email' => 'other@example.com']);

    $controller = new AvisoCobroController;
    $response = $controller->sendEmail($request, $collection);

    expect($response->getStatusCode())->toBe(200);

    Mail::assertQueued(AvisoCobroCertificateEmail::class, function (AvisoCobroCertificateEmail $mail): bool {
        return $mail->queue === 'default'
            && $mail->hasTo('other@example.com')
            && ! $mail->hasCc('administracion@tudrencasa.com');
    });
});
