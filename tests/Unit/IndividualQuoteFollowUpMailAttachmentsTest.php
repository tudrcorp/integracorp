<?php

declare(strict_types=1);

use App\Mail\IndividualQuoteFollowUpMail;
use App\Support\IndividualQuotes\IndividualQuoteDayFiveFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteDayNineFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteDaySevenFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteFollowUp;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

it('resuelve la ruta local de assets publicos de seguimiento', function (): void {
    $relativePath = 'imagenes-seguimiento-cotizaciones/test-asset-'.uniqid('', true).'.txt';
    $fullPath = storage_path('app/public/'.$relativePath);

    File::ensureDirectoryExists(dirname($fullPath));
    File::put($fullPath, 'contenido-de-prueba');

    try {
        expect(IndividualQuoteFollowUp::localPublicAssetPath($relativePath))
            ->toBe($fullPath);
    } finally {
        File::delete($fullPath);
    }
});

it('adjunta el video del dia 5 cuando existe en storage publico', function (): void {
    Storage::fake('public');

    $relativePath = IndividualQuoteDayFiveFollowUp::FOLLOW_UP_VIDEO;
    Storage::disk('public')->put($relativePath, 'fake-video-bytes');

    $mailable = new IndividualQuoteFollowUpMail(
        recipientEmail: 'agente@example.com',
        recipientName: 'Juan Pérez',
        subjectLine: 'Seguimiento',
        followUpLabel: 'Seguimiento cotizaciones (5 días)',
        messageBody: 'Mensaje de prueba',
        audienceLabel: 'te compartimos el seguimiento:',
        attachmentRelativePaths: [$relativePath],
    );

    expect($mailable->attachments())->toHaveCount(1);
});

it('adjunta las imagenes del dia 7 cuando existen en storage publico', function (): void {
    Storage::fake('public');

    Storage::disk('public')->put(IndividualQuoteDaySevenFollowUp::IMAGE_PLAN_GUIDE, 'img1');
    Storage::disk('public')->put(IndividualQuoteDaySevenFollowUp::IMAGE_PAYMENT_METHODS, 'img2');

    $mailable = new IndividualQuoteFollowUpMail(
        recipientEmail: 'agente@example.com',
        recipientName: 'Juan Pérez',
        subjectLine: 'Seguimiento',
        followUpLabel: 'Seguimiento cotizaciones (7 días)',
        messageBody: 'Mensaje de prueba',
        audienceLabel: 'te compartimos el seguimiento:',
        attachmentRelativePaths: [
            IndividualQuoteDaySevenFollowUp::IMAGE_PLAN_GUIDE,
            IndividualQuoteDaySevenFollowUp::IMAGE_PAYMENT_METHODS,
        ],
    );

    expect($mailable->attachments())->toHaveCount(2);
});

it('adjunta el flyer pdf del dia 9 cuando existe en storage publico', function (): void {
    Storage::fake('public');

    $relativePath = IndividualQuoteDayNineFollowUp::BENEFITS_FLYER;
    Storage::disk('public')->put($relativePath, '%PDF-fake');

    $mailable = new IndividualQuoteFollowUpMail(
        recipientEmail: 'agente@example.com',
        recipientName: 'Juan Pérez',
        subjectLine: 'Seguimiento',
        followUpLabel: 'Seguimiento cotizaciones (9 días)',
        messageBody: 'Mensaje de prueba',
        audienceLabel: 'te compartimos el seguimiento:',
        attachmentRelativePaths: [$relativePath],
    );

    expect($mailable->attachments())->toHaveCount(1);
});
