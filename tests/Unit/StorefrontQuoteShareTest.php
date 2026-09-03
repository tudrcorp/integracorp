<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Support\Storefront\StorefrontQuoteShare;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('siembra solo el telefono; el correo lo agrega el cliente', function (): void {
    $quote = new IndividualQuote([
        'phone' => '0412 754 6890',
        'email' => 'cliente@example.com',
    ]);

    expect(StorefrontQuoteShare::seedFromQuote($quote))->toBe([
        ['channel' => 'whatsapp', 'value' => '04127546890'],
    ])
        ->and(StorefrontQuoteShare::seedFromQuote(new IndividualQuote([
            'phone' => '',
            'email' => 'cliente@example.com',
        ])))->toBe([
            ['channel' => 'whatsapp', 'value' => ''],
        ])
        ->and(StorefrontQuoteShare::emptyRecipient('email'))->toBe([
            'channel' => 'email',
            'value' => '',
        ]);
});

it('normaliza destinatarios y rechaza correo o telefono invalidos', function (): void {
    expect(StorefrontQuoteShare::normalize([
        ['channel' => 'whatsapp', 'value' => '0412 754 6890'],
        ['channel' => 'email', 'value' => ' ANA@TDG.COM '],
        ['channel' => 'whatsapp', 'value' => ''],
        ['channel' => 'email', 'value' => 'ana@tdg.com'],
    ]))->toBe([
        ['channel' => 'whatsapp', 'value' => '04127546890'],
        ['channel' => 'email', 'value' => 'ana@tdg.com'],
    ]);

    expect(fn () => StorefrontQuoteShare::normalize([]))
        ->toThrow(ValidationException::class);

    expect(fn () => StorefrontQuoteShare::normalize([
        ['channel' => 'email', 'value' => 'no-es-correo'],
    ]))->toThrow(ValidationException::class);

    expect(fn () => StorefrontQuoteShare::normalize([
        ['channel' => 'whatsapp', 'value' => '0412'],
    ]))->toThrow(ValidationException::class);
});

it('el enlace de la pwa y el de negocios no sacan al cliente al flujo /in', function (): void {
    expect(StorefrontQuoteShare::proposalPath('COT-IND-0099'))
        ->toBe('/app/cotizacion/COT-IND-0099/propuesta')
        ->and(StorefrontQuoteShare::negociosWhatsAppUrl('COT-IND-0099', '584127018390'))
        ->toStartWith('https://wa.me/584127018390')
        ->toContain('COT-IND-0099')
        ->not->toContain('/in/');
});

it('el envio de la pwa reutiliza correo y whatsapp del sistema en cola', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendStorefrontQuoteShareJob.php');
    $share = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontQuoteShare.php');

    expect($job)
        ->toContain('ShouldQueue')
        ->toContain('ShouldBeUnique')
        ->toContain('MailLinkIndividualQuote')
        ->toContain('sendLinkIndividualQuote')
        ->and($share)->toContain('SendStorefrontQuoteShareJob::dispatch');
});

it('recuerda el codigo reciente y descarta valores invalidos', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontQuoteShare.php');

    expect($source)
        ->toContain('LAST_CODE_COOKIE')
        ->toContain('rememberCode')
        ->toContain('lastCode')
        ->and(StorefrontQuoteShare::lastCode())->toBeNull();

    StorefrontQuoteShare::rememberCode('');
    StorefrontQuoteShare::rememberCode('no vale!');

    expect(StorefrontQuoteShare::lastCode())->toBeNull();
});
