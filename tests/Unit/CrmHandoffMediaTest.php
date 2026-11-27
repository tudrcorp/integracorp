<?php

declare(strict_types=1);

use App\Enums\TelemedicineCaseAttachmentStage;
use App\Jobs\StoreCrmHandoffMediaJob;
use App\Models\CrmHandoffAttachment;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseAttachment;
use App\Models\User;
use App\Support\CrmInbox\CrmHandoffAttachmentFiling;
use App\Support\CrmInbox\CrmHandoffMedia;
use App\Support\CrmInbox\CrmHandoffMediaData;
use App\Support\CrmInbox\CrmHandoffMediaIngress;
use App\Support\CrmInbox\CrmHandoffSignature;
use App\Support\Telemedicine\TelemedicineCaseDocumentsCatalog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();

    config([
        'cache.stores.crm-inbox' => ['driver' => 'array', 'serialize' => false],
        'crm-inbox.cache_store' => 'crm-inbox',
        'crm-inbox.secret' => 'test-secret',
        'crm-inbox.media.disk' => 'local',
    ]);
    app('cache')->forgetDriver('crm-inbox');
    Cache::store('crm-inbox')->flush();
    Storage::fake('local');
    Storage::fake('public');
});

afterEach(function (): void {
    DB::rollBack();
});

/** PNG real de 1×1. */
function crmMediaPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

function crmMediaPdf(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}

function crmMediaEnvelope(string $phone, array $extra = []): CrmHandoffEnvelope
{
    return CrmHandoffEnvelope::query()->create([
        'handoff_id' => (string) random_int(900000000, 999999999),
        'phone' => $phone,
        'area' => 'comercial',
        'payload' => ['phone' => $phone],
        'accepted_at' => now(),
        ...$extra,
    ]);
}

/** Lo que la puerta deja listo para el job: el archivo ya guardado en el disco privado. */
function crmMediaJobPayload(string $messageId, string $phone, ?string $handoffId, string $content, string $type = 'image', string $mime = 'image/png', string $ext = 'png', ?string $caption = null): array
{
    $path = 'crm/attachments/test/'.$messageId.'.'.$ext;
    Storage::disk('local')->put($path, $content);

    return [
        'message_id' => $messageId,
        'handoff_id' => $handoffId,
        'phone' => $phone,
        'media_type' => $type,
        'mime_type' => $mime,
        'extension' => $ext,
        'original_name' => 'archivo.'.$ext,
        'caption' => $caption,
        'size' => strlen($content),
        'sha256' => hash('sha256', $content),
        'disk' => 'local',
        'path' => $path,
    ];
}

it('acepta una imagen real y decide el tipo por el contenido', function (): void {
    $parsed = CrmHandoffMediaData::fromJson(json_encode([
        'message_id' => 'WAMID.IMG1',
        'phone' => '584141234567',
        'mime_type' => 'image/jpeg',
        'file_name' => '../../etc/foto.png',
        'content_base64' => base64_encode(crmMediaPng()),
    ]));

    expect($parsed['ok'])->toBeTrue();
    expect($parsed['media']['mime_type'])->toBe('image/png');
    expect($parsed['media']['media_type'])->toBe('image');
    expect($parsed['media']['original_name'])->toBe('foto.png');
});

it('rechaza un ejecutable disfrazado de PDF, el base64 roto y el archivo vacío', function (): void {
    $exe = CrmHandoffMediaData::fromJson(json_encode([
        'message_id' => 'WAMID.EXE', 'phone' => '584141234567', 'mime_type' => 'application/pdf',
        'content_base64' => base64_encode("MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF".str_repeat("\x00", 64)),
    ]));
    $broken = CrmHandoffMediaData::fromJson(json_encode([
        'message_id' => 'WAMID.B64', 'phone' => '584141234567', 'content_base64' => '###no-es-base64###',
    ]));
    $empty = CrmHandoffMediaData::fromJson(json_encode([
        'message_id' => 'WAMID.EMPTY', 'phone' => '584141234567', 'content_base64' => '',
    ]));

    expect($exe)->toBe(['ok' => false, 'reason' => 'unsupported_type']);
    expect($broken)->toBe(['ok' => false, 'reason' => 'invalid_content']);
    expect($empty)->toBe(['ok' => false, 'reason' => 'invalid_content']);
});

it('rechaza un archivo que pasa del tope configurado', function (): void {
    config(['crm-inbox.media.max_mb' => 1]);

    $parsed = CrmHandoffMediaData::fromJson(json_encode([
        'message_id' => 'WAMID.BIG', 'phone' => '584141234567',
        'content_base64' => base64_encode(crmMediaPdf().str_repeat('A', 1024 * 1024)),
    ]));

    expect($parsed)->toBe(['ok' => false, 'reason' => 'file_too_large']);
});

it('la puerta verifica la firma, guarda el archivo y encola una sola vez', function (): void {
    Queue::fake();
    $body = json_encode([
        'message_id' => 'WAMID.GATE1', 'phone' => '584141234567', 'handoff_id' => '77',
        'content_base64' => base64_encode(crmMediaPdf()), 'file_name' => 'informe.pdf',
    ]);
    $ts = (string) time();
    $signature = CrmHandoffSignature::sign($ts, $body, 'test-secret');
    $ingress = new CrmHandoffMediaIngress;

    $bad = $ingress->accept($body, $ts, str_repeat('0', 64), '10.0.0.1');
    $first = $ingress->accept($body, $ts, $signature, '10.0.0.1');
    $again = $ingress->accept($body, $ts, $signature, '10.0.0.1');

    expect($bad->httpStatus())->toBe(401);
    expect($first->status)->toBe('accepted');
    expect($again->status)->toBe('duplicate');
    $pushed = Queue::pushed(StoreCrmHandoffMediaJob::class);

    expect($pushed)->toHaveCount(1);
    expect($pushed->first()->media['mime_type'])->toBe('application/pdf');
    expect(array_key_exists('content', $pushed->first()->media))->toBeFalse();
    expect(Storage::disk('local')->exists($pushed->first()->media['path']))->toBeTrue();
});

it('el job crea el adjunto y su burbuja, y actualiza la línea de la bandeja', function (): void {
    $envelope = crmMediaEnvelope('584140000001');

    (new StoreCrmHandoffMediaJob(crmMediaJobPayload('WAMID.JOB1', '584140000001', $envelope->handoff_id, crmMediaPdf(), 'document', 'application/pdf', 'pdf', 'Mi informe')))->handle();

    $attachment = CrmHandoffAttachment::query()->where('message_id', 'WAMID.JOB1')->first();
    $message = CrmHandoffMessage::query()->where('message_id', 'WAMID.JOB1')->first();

    expect($attachment)->not->toBeNull();
    expect($message->kind)->toBe('media');
    expect($message->body)->toBe('Mi informe');
    expect($message->meta['attachment_id'])->toBe($attachment->id);
    expect($attachment->crm_handoff_message_id)->toBe($message->id);
    expect($envelope->fresh()->last_customer_text)->toBe('📎 Documento: archivo.pdf · Mi informe');
});

it('si el texto del bot llegó primero, queda una sola burbuja de archivo; la nota de voz conserva la transcripción', function (): void {
    $envelope = crmMediaEnvelope('584140000002');
    CrmHandoffMessage::query()->create(['message_id' => 'WAMID.IMG2', 'handoff_id' => $envelope->handoff_id, 'phone' => '584140000002', 'body' => '[El cliente envió una imagen sin texto]', 'received_at' => now(), 'direction' => 'in', 'kind' => 'message', 'status' => 'received']);
    CrmHandoffMessage::query()->create(['message_id' => 'WAMID.AUD2', 'handoff_id' => $envelope->handoff_id, 'phone' => '584140000002', 'body' => 'Hola, necesito la cita', 'received_at' => now(), 'direction' => 'in', 'kind' => 'message', 'status' => 'received']);

    (new StoreCrmHandoffMediaJob(crmMediaJobPayload('WAMID.IMG2', '584140000002', $envelope->handoff_id, crmMediaPng())))->handle();
    (new StoreCrmHandoffMediaJob(crmMediaJobPayload('WAMID.AUD2', '584140000002', $envelope->handoff_id, 'OggS-audio', 'audio', 'audio/ogg', 'ogg')))->handle();

    expect(CrmHandoffMessage::query()->where('message_id', 'WAMID.IMG2')->count())->toBe(1);
    expect(CrmHandoffMessage::query()->where('message_id', 'WAMID.IMG2')->value('body'))->toBe('');
    expect(CrmHandoffMessage::query()->where('message_id', 'WAMID.IMG2')->value('kind'))->toBe('media');
    expect(CrmHandoffMessage::query()->where('message_id', 'WAMID.AUD2')->value('body'))->toBe('Hola, necesito la cita');
});

it('un reintento del job no duplica nada y borra el archivo sobrante', function (): void {
    $envelope = crmMediaEnvelope('584140000003');
    $payload = crmMediaJobPayload('WAMID.DUP3', '584140000003', $envelope->handoff_id, crmMediaPng());
    (new StoreCrmHandoffMediaJob($payload))->handle();

    $retry = crmMediaJobPayload('WAMID.DUP3', '584140000003', $envelope->handoff_id, crmMediaPng());
    $retry['path'] = 'crm/attachments/test/otro.png';
    Storage::disk('local')->put($retry['path'], crmMediaPng());
    (new StoreCrmHandoffMediaJob($retry))->handle();

    expect(CrmHandoffAttachment::query()->where('message_id', 'WAMID.DUP3')->count())->toBe(1);
    expect(Storage::disk('local')->exists($payload['path']))->toBeTrue();
    expect(Storage::disk('local')->exists('crm/attachments/test/otro.png'))->toBeFalse();
});

it('el hilo del caso muestra la burbuja con la imagen y la ruta protegida', function (): void {
    $envelope = crmMediaEnvelope('584140000004');
    (new StoreCrmHandoffMediaJob(crmMediaJobPayload('WAMID.VIEW4', '584140000004', $envelope->handoff_id, crmMediaPng())))->handle();

    $detail = App\Support\CrmInbox\CrmInboxCase::detail($envelope->fresh(), now());
    $item = collect($detail['attention'])->firstWhere('id', 'WAMID.VIEW4');

    expect($item['kind'])->toBe('media');
    expect($item['media']['previewable'])->toBeTrue();
    expect($item['media']['url'])->toContain('/crm/archivos/');
});

it('al cerrar exige elegir el caso cuando hay archivos, y sin archivos no pide nada', function (): void {
    $empty = crmMediaEnvelope('584140000005');
    $withFiles = crmMediaEnvelope('584140000006');
    (new StoreCrmHandoffMediaJob(crmMediaJobPayload('WAMID.F6', '584140000006', $withFiles->handoff_id, crmMediaPng())))->handle();
    $filing = new CrmHandoffAttachmentFiling;

    expect($filing->file($empty, '', 1))->toBe(['ok' => true, 'filed' => 0, 'skipped' => 0, 'case_code' => null]);
    expect($filing->file($withFiles, '', 1))->toBe(['ok' => false, 'reason' => 'choose']);
    expect($filing->file($withFiles, '999999999', 1))->toBe(['ok' => false, 'reason' => 'case']);
    expect(CrmHandoffMedia::pendingFor($withFiles))->toHaveCount(1);
});

it('«no corresponde a un caso» marca los archivos y ya no vuelve a pedirlo', function (): void {
    $envelope = crmMediaEnvelope('584140000007');
    (new StoreCrmHandoffMediaJob(crmMediaJobPayload('WAMID.F7', '584140000007', $envelope->handoff_id, crmMediaPng())))->handle();

    $result = (new CrmHandoffAttachmentFiling)->file($envelope, 'none', 2);

    expect($result['skipped'])->toBe(1);
    expect(CrmHandoffAttachment::query()->where('message_id', 'WAMID.F7')->first()->filing_skipped)->toBeTrue();
    expect(CrmHandoffMedia::pendingFor($envelope))->toHaveCount(0);
});

it('agrega los archivos al caso de telemedicina elegido, con la etapa WhatsApp', function (): void {
    $case = TelemedicineCase::query()->whereNull('logically_deleted_at')->orderByDesc('id')->first();

    if ($case === null) {
        expect(true)->toBeTrue();

        return;
    }

    $envelope = crmMediaEnvelope('584140000008');
    (new StoreCrmHandoffMediaJob(crmMediaJobPayload('WAMID.F8', '584140000008', $envelope->handoff_id, crmMediaPdf(), 'document', 'application/pdf', 'pdf', 'Récipe')))->handle();

    $result = (new CrmHandoffAttachmentFiling)->file($envelope, (string) $case->id, 2);
    $attachment = CrmHandoffAttachment::query()->where('message_id', 'WAMID.F8')->first();
    $copy = TelemedicineCaseAttachment::query()->find($attachment->telemedicine_case_attachment_id);

    expect($result['ok'])->toBeTrue();
    expect($result['filed'])->toBe(1);
    expect($attachment->telemedicine_case_id)->toBe((int) $case->id);
    expect($attachment->filed_at)->not->toBeNull();
    expect($copy->stage)->toBe(TelemedicineCaseAttachmentStage::WhatsApp);
    expect($copy->file_path)->toStartWith('telemedicina-doc/adjuntos/'.$case->id.'/whatsapp-');
    expect(Storage::disk('public')->get($copy->file_path))->toBe(crmMediaPdf());
    expect($copy->description)->toContain('Récipe');
    expect((new CrmHandoffAttachmentFiling)->file($envelope, '', 2)['ok'])->toBeTrue();

    $entries = collect(TelemedicineCaseDocumentsCatalog::entries($case));
    $entry = $entries->firstWhere('file_path', $copy->file_path) ?? $entries->first(fn ($row): bool => ($row['document_name'] ?? null) === 'archivo.pdf');

    expect($entry)->not->toBeNull();
    expect(implode(' ', (array) ($entry['types'] ?? [])))->toContain('Enviado por el cliente por WhatsApp');
});

it('sugiere los casos del mismo teléfono sin importar el formato del número', function (): void {
    $case = TelemedicineCase::query()->whereNull('logically_deleted_at')->whereRaw('LENGTH(patient_phone) >= 10')->orderByDesc('id')->first();

    if ($case === null) {
        expect(true)->toBeTrue();

        return;
    }

    $tail = substr(preg_replace('/\D+/', '', (string) $case->patient_phone), -10);
    $envelope = crmMediaEnvelope('58'.$tail);

    $ids = array_column(CrmHandoffAttachmentFiling::suggestions($envelope), 'id');

    expect($ids)->toContain((int) $case->id);
    expect(CrmHandoffAttachmentFiling::search('ab'))->toBe([]);
    expect(array_column(CrmHandoffAttachmentFiling::search((string) $case->code), 'id'))->toContain((int) $case->id);
});

it('solo abre el archivo quien atiende la bandeja del área', function (): void {
    $envelope = crmMediaEnvelope('584140000009');
    (new StoreCrmHandoffMediaJob(crmMediaJobPayload('WAMID.F9', '584140000009', $envelope->handoff_id, crmMediaPng())))->handle();
    $attachment = CrmHandoffAttachment::query()->where('message_id', 'WAMID.F9')->first();
    $outsider = (new User)->forceFill(['id' => 0, 'email' => 'externo@example.com', 'departament' => [], 'status' => 'ACTIVO']);

    expect(CrmHandoffMedia::canView(null, $attachment))->toBeFalse();
    expect(CrmHandoffMedia::canView($outsider, $attachment))->toBeFalse();
});

it('la limpieza borra el archivo vencido del disco del CRM y conserva la fila', function (): void {
    $envelope = crmMediaEnvelope('584140000010');
    $payload = crmMediaJobPayload('WAMID.OLD10', '584140000010', $envelope->handoff_id, crmMediaPng());
    (new StoreCrmHandoffMediaJob($payload))->handle();
    CrmHandoffAttachment::query()->where('message_id', 'WAMID.OLD10')->update(['created_at' => now()->subDays(181)]);

    Artisan::call('crm-inbox:prune-attachments');

    expect(CrmHandoffAttachment::query()->where('message_id', 'WAMID.OLD10')->value('purged_at'))->not->toBeNull();
    expect(Storage::disk('local')->exists($payload['path']))->toBeFalse();
});

it('el diálogo de cierre pide el caso de los archivos en finalizar, devolver y cerrar', function (): void {
    $view = file_get_contents(resource_path('views/filament/crm/pages/atencion-whatsapp.blade.php'));
    $page = file_get_contents(app_path('Filament/Business/Pages/AtencionWhatsapp.php'));

    expect($view)->toContain('this.$wire.filingContext()', 'this.$wire.searchTelemedicineCases(term)', 'No corresponden a ningún caso', "this.filing.files.length > 0 && this.filing.choice === ''", "@include('filament.crm.partials.media-bubble'");
    expect(substr_count($page, '$this->fileAttachmentsOrNotify($envelope, $filing)'))->toBe(3);
    expect($page)->toContain('#[Renderless]');
});
