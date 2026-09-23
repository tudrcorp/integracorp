<?php

declare(strict_types=1);

use App\Jobs\SendNotificacionWhatsApp;
use App\Support\WhatsAppMessageSplitter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    config(['cache.default' => 'array']);
});

afterEach(fn () => DB::rollBack());

/**
 * Doble del job: registra cada envío en lugar de llamar a UltraMsg.
 */
function fakeWhatsAppJob(string $body, array $failAt = []): SendNotificacionWhatsApp
{
    return new class(1, $body, '584140000000', null, [], 'https://example.com/logo.png', $failAt) extends SendNotificacionWhatsApp
    {
        /** @var list<array{url: string, params: array<string, mixed>}> */
        public array $calls = [];

        public int $attempt = 0;

        public function __construct(mixed $user_id, string $body, string $phone, mixed $document, array $auditContext, ?string $imageUrl, public array $failAt = [])
        {
            parent::__construct($user_id, $body, $phone, $document, $auditContext, $imageUrl);
        }

        protected function post(string $url, array $params): array
        {
            $this->attempt++;
            $this->calls[] = ['url' => $url, 'params' => $params];

            if (in_array($this->attempt, $this->failAt, true)) {
                return ['{"error":"falla simulada"}', 500, ''];
            }

            return ['{"sent":"true","message":"ok"}', 200, ''];
        }
    };
}

it('deja intacto un mensaje que cabe en el pie de la imagen', function (): void {
    $parts = WhatsAppMessageSplitter::split("Hola\nmundo");

    expect($parts)->toBe(['caption' => "Hola\nmundo", 'rest' => []]);
});

it('corta el pie por debajo de 1.024 caracteres y en un salto de línea', function (): void {
    $body = implode("\n", array_fill(0, 40, str_repeat('a', 49)));
    $parts = WhatsAppMessageSplitter::split($body);

    expect(mb_strlen($parts['caption']))->toBeLessThanOrEqual(1000)
        ->and($parts['caption'])->toEndWith('a…')
        ->and($parts['rest'])->toHaveCount(1)
        ->and($parts['rest'][0])->toStartWith('a');

    $rebuilt = mb_substr($parts['caption'], 0, -1)."\n".$parts['rest'][0];
    expect($rebuilt)->toBe($body);
});

it('corta entre palabras cuando no hay saltos de línea', function (): void {
    $body = trim(str_repeat('palabra ', 300));
    $parts = WhatsAppMessageSplitter::split($body);

    expect(mb_substr($parts['caption'], 0, -1))->toEndWith('palabra')
        ->and($parts['rest'][0])->toStartWith('palabra');
});

it('parte un texto enorme en varios mensajes bajo el límite de texto', function (): void {
    $body = str_repeat('x', 9500);
    $parts = WhatsAppMessageSplitter::split($body);

    expect(mb_strlen($parts['caption']))->toBeLessThanOrEqual(1000)
        ->and($parts['rest'])->toHaveCount(3);

    foreach ($parts['rest'] as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual(4000);
    }

    expect(mb_strlen(mb_substr($parts['caption'], 0, -1).implode('', $parts['rest'])))->toBe(9500);
});

it('cuenta caracteres multibyte y no rompe emojis ni acentos', function (): void {
    $body = str_repeat('ñ😀 ', 600);
    $parts = WhatsAppMessageSplitter::split($body);

    expect(mb_strlen($parts['caption']))->toBeLessThanOrEqual(1000)
        ->and(mb_check_encoding($parts['caption'], 'UTF-8'))->toBeTrue()
        ->and(mb_check_encoding($parts['rest'][0], 'UTF-8'))->toBeTrue();
});

it('envía un mensaje corto como una sola imagen con pie', function (): void {
    $job = fakeWhatsAppJob('Mensaje corto');
    $job->handle();

    expect($job->calls)->toHaveCount(1)
        ->and($job->calls[0]['url'])->toBe((string) config('parameters.CURLOPT_URL_IMAGE'))
        ->and($job->calls[0]['params']['caption'])->toBe('Mensaje corto')
        ->and($job->calls[0]['params']['image'])->toBe('https://example.com/logo.png');
});

it('envía un mensaje largo como imagen más mensajes de texto', function (): void {
    $job = fakeWhatsAppJob(str_repeat("Línea de detalle del reporte.\n", 80));
    $job->handle();

    expect($job->calls)->toHaveCount(2)
        ->and(mb_strlen($job->calls[0]['params']['caption']))->toBeLessThanOrEqual(1000)
        ->and($job->calls[1]['url'])->toBe((string) config('parameters.CURLOPT_URL'))
        ->and($job->calls[1]['params'])->toHaveKey('body')
        ->and($job->calls[1]['params'])->not->toHaveKey('caption');
});

it('propaga el error para que la cola reintente', function (): void {
    $job = fakeWhatsAppJob('Mensaje', failAt: [1]);

    expect(fn () => $job->handle())->toThrow(Exception::class);
});

it('en un reintento no reenvía las partes que ya salieron', function (): void {
    Cache::flush();
    $body = str_repeat("Línea de detalle del reporte.\n", 80);

    $queueJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $queueJob->shouldReceive('uuid')->andReturn('uuid-reintento-whatsapp');

    $first = fakeWhatsAppJob($body, failAt: [2]);
    $first->setJob($queueJob);

    expect(fn () => $first->handle())->toThrow(Exception::class);

    $retry = fakeWhatsAppJob($body);
    $retry->setJob($queueJob);
    $retry->handle();

    expect($retry->calls)->toHaveCount(1)
        ->and($retry->calls[0]['url'])->toBe((string) config('parameters.CURLOPT_URL'));
});
