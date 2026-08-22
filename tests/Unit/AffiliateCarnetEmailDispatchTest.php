<?php

declare(strict_types=1);

use App\Jobs\SendAffiliateCarnetEmailJob;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Support\AffiliateCard\AffiliateCarnetEmailDispatchService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

it('el correo de carnet explica al afiliado y adjunta carnet y condicionado', function (): void {
    $mail = file_get_contents(dirname(__DIR__, 2).'/app/Mail/AffiliateCarnetIssuedMail.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/affiliate-carnet-issued.blade.php');

    expect($mail)
        ->toContain('Su carnet de afiliado')
        ->toContain('mails.affiliate-carnet-issued')
        ->toContain('carnetPath')
        ->toContain('condicionadoPath')
        ->and($view)->toContain('acaba de recibir su carnet de afiliado')
        ->and($view)->toContain('Condicionado del plan');
});

it('el job envia un correo por afiliado en la cola default', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendAffiliateCarnetEmailJob.php');

    expect($job)
        ->toContain('Batchable')
        ->toContain("onQueue('default')")
        ->toContain('AffiliateCarnetIssuedMail')
        ->toContain('Mail::to($this->email)->send');
});

it('el lote notifica a la campanita al terminar o fallar', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliateCard/AffiliateCarnetEmailDispatchService.php');
    $notifier = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliateCard/AffiliateCarnetEmailNotifier.php');

    expect($service)
        ->toContain('Bus::batch($jobs)')
        ->toContain('allowFailures()')
        ->toContain('AffiliateCarnetEmailNotifier::notifyCompletion')
        ->toContain('AffiliateCarnetEmailNotifier::notifyQueued')
        ->and($notifier)->toContain('notifyNow($notification->toDatabase())')
        ->and($notifier)->toContain('DatabaseNotificationsSent::dispatch($user)')
        ->and($notifier)->toContain('CompanyAssociateDocumentsBellAlert::markPending')
        ->and($notifier)->toContain('Envío de carnets en segundo plano')
        ->and($notifier)->toContain('Puede cerrar esta ventana y seguir trabajando')
        ->and($notifier)->toContain('Carnets enviados')
        ->and($notifier)->toContain('Error al enviar carnets');
});

it('omite afiliados individuales sin correo ni documentos', function (): void {
    $affiliation = new Affiliation([
        'code' => 'TDEC-IND-TEST01',
        'plan_id' => 2,
        'full_name_ti' => 'Maria Lopez',
        'nro_identificacion_ti' => 'V-1',
        'email_ti' => '',
    ]);
    $affiliation->id = 91001;

    $withoutEmail = new Affiliate([
        'full_name' => 'Ana Perez',
        'nro_identificacion' => 'V-2',
        'email' => '',
    ]);
    $withoutEmail->id = 11;

    $affiliation->setRelation('affiliates', collect([$withoutEmail]));

    $collected = AffiliateCarnetEmailDispatchService::collectForIndividual($affiliation);

    expect($collected['sendable'])->toBe([])
        ->and($collected['skipped'])->toBeGreaterThan(0);
});

it('usa el correo del titular cuando el afiliado no tiene email propio', function (): void {
    $affiliation = new Affiliation([
        'code' => 'TDEC-IND-TEST03',
        'plan_id' => 2,
        'full_name_ti' => 'Maria Lopez',
        'nro_identificacion_ti' => 'V-1',
        'email_ti' => 'titular@example.com',
    ]);
    $affiliation->id = 91003;

    $affiliate = new Affiliate([
        'full_name' => 'Ana Perez',
        'nro_identificacion' => 'V-2',
        'email' => '',
    ]);
    $affiliate->id = 44;
    $affiliation->setRelation('affiliates', collect([$affiliate]));

    $carnetDir = public_path('storage/tarjeta-afiliacion');
    $condDir = storage_path('app/public/condicionados');
    if (! is_dir($carnetDir)) {
        mkdir($carnetDir, 0755, true);
    }
    if (! is_dir($condDir)) {
        mkdir($condDir, 0755, true);
    }

    $carnetPath = $carnetDir.'/TAR-TDEC-IND-TEST03-44.pdf';
    $condicionadoPath = $condDir.'/CondicionesIDEAL.pdf';
    $createdCondicionado = ! is_file($condicionadoPath);

    file_put_contents($carnetPath, '%PDF-1.4 test');
    if ($createdCondicionado) {
        file_put_contents($condicionadoPath, '%PDF-1.4 test');
    }

    try {
        $collected = AffiliateCarnetEmailDispatchService::collectForIndividual($affiliation);

        expect($collected['sendable'])->toHaveCount(1)
            ->and($collected['sendable'][0]['email'])->toBe('titular@example.com');
    } finally {
        @unlink($carnetPath);
        if ($createdCondicionado) {
            @unlink($condicionadoPath);
        }
    }
});

it('omite afiliados corporativos sin correo', function (): void {
    $affiliation = new AffiliationCorporate([
        'code' => 'TDEC-COR-TEST01',
    ]);
    $affiliation->id = 92001;

    $affiliate = new AffiliateCorporate([
        'first_name' => 'Luis',
        'last_name' => 'Rivas',
        'email' => '',
        'plan_id' => 2,
    ]);
    $affiliate->id = 21;

    $affiliation->setRelation('corporateAffiliates', collect([$affiliate]));

    $collected = AffiliateCarnetEmailDispatchService::collectForCorporate($affiliation);

    expect($collected['sendable'])->toBe([])
        ->and($collected['skipped'])->toBe(1);
});

it('encola un job por afiliado con correo y documentos listos', function (): void {
    Bus::fake();
    Cache::forget('affiliate-carnet-emails.individual:91002');

    $affiliation = new Affiliation([
        'code' => 'TDEC-IND-TEST02',
        'plan_id' => 2,
        'full_name_ti' => 'Maria Lopez',
        'nro_identificacion_ti' => 'V-111',
        'email_ti' => '',
    ]);
    $affiliation->id = 91002;

    $affiliate = new Affiliate([
        'full_name' => 'Ana Perez',
        'nro_identificacion' => 'V-222',
        'email' => 'ana.perez@example.com',
    ]);
    $affiliate->id = 33;
    $affiliation->setRelation('affiliates', collect([$affiliate]));

    $carnetDir = public_path('storage/tarjeta-afiliacion');
    $condDir = storage_path('app/public/condicionados');
    if (! is_dir($carnetDir)) {
        mkdir($carnetDir, 0755, true);
    }
    if (! is_dir($condDir)) {
        mkdir($condDir, 0755, true);
    }

    $carnetPath = $carnetDir.'/TAR-TDEC-IND-TEST02-33.pdf';
    $condicionadoPath = $condDir.'/CondicionesIDEAL.pdf';
    $createdCondicionado = ! is_file($condicionadoPath);

    file_put_contents($carnetPath, '%PDF-1.4 test');
    if ($createdCondicionado) {
        file_put_contents($condicionadoPath, '%PDF-1.4 test');
    }

    try {
        $result = AffiliateCarnetEmailDispatchService::queueForIndividual($affiliation, 9_000_001);

        expect($result['ok'])->toBeTrue()
            ->and($result['queued'])->toBe(1)
            ->and($result['message'])->toContain('se envían en segundo plano')
            ->and($result['message'])->toContain('seguir trabajando');

        Bus::assertBatched(function ($batch): bool {
            return $batch->jobs->count() === 1
                && $batch->jobs->first() instanceof SendAffiliateCarnetEmailJob;
        });
    } finally {
        @unlink($carnetPath);
        if ($createdCondicionado) {
            @unlink($condicionadoPath);
        }
        Cache::forget('affiliate-carnet-emails.individual:91002');
    }
});
