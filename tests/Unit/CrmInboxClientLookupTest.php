<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Support\CrmInbox\CrmInboxClient360;
use App\Support\CrmInbox\CrmInboxCopilot;
use App\Support\CrmInbox\CrmInboxDirectory;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Ficha del cliente en Atención WhatsApp: la cédula que el cliente escribe como respuesta (aunque
 * venga sola) y los afiliados de empresas. Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('reconoce la cédula escrita sola solo cuando responde a quien la pidió', function (string $text, bool $asked, ?string $expected): void {
    expect(CrmInboxCopilot::document($text, $asked)['digits'] ?? null)->toBe($expected);
})->with([
    'número solo tras pedirla' => ['Claro 16007868', true, '16007868'],
    'con puntos tras pedirla' => ['es 16.007.868', true, '16007868'],
    'número solo sin pedirla' => ['Claro 16007868', false, null],
    'con la palabra cédula' => ['mi cédula es 16.007.868', false, '16007868'],
    'con C.I.' => ['C.I. 16007868', false, '16007868'],
    'con letra' => ['V-16007868', false, '16007868'],
    'teléfono no es cédula' => ['mi número es 04127018390', true, null],
    'monto en bolívares no' => ['son 150000 bs', true, null],
    'monto en dólares no' => ['pagué 1500000$', true, null],
    'fecha no' => ['nací el 12/05/1980', true, null],
    'muy corto no' => ['12345', true, null],
]);

it('toma la respuesta del cliente como cédula si el último mensaje enviado la pidió', function (): void {
    $case = ['attention' => [
        ['role' => 'out', 'text' => 'Gustavo, ¿me confirmas la cédula del titular para revisar tu ficha?', 'id' => 'a'],
        ['role' => 'in', 'text' => 'Claro 16007868', 'id' => 'b'],
    ], 'messages' => []];

    $notAsked = ['attention' => [
        ['role' => 'out', 'text' => '¿Cuántas personas serían?', 'id' => 'a'],
        ['role' => 'in', 'text' => '16007868', 'id' => 'b'],
    ], 'messages' => []];

    expect(CrmInboxCopilot::detectedDocument($case))->toBe('16007868')
        ->and(CrmInboxCopilot::detectedDocument($notAsked))->toBeNull();
});

it('encuentra a un afiliado corporativo por teléfono y por cédula, con su ficha y su resumen', function (): void {
    $template = AffiliateCorporate::query()->whereNotNull('affiliation_corporate_id')->where('status', 'ACTIVO')->orderByDesc('id')->first();

    if (! $template instanceof AffiliateCorporate) {
        $this->markTestSkipped('No hay afiliados corporativos activos.');
    }

    $document = (string) random_int(80000000, 89999999);
    $phone = '414'.random_int(1000000, 9999999);

    $affiliate = $template->replicate();
    $affiliate->forceFill(['nro_identificacion' => $document, 'phone' => $phone, 'first_name' => 'QA CORPORATIVO', 'last_name' => 'PRUEBA'])->save();

    $byDocument = CrmInboxDirectory::matchDocument($document);
    $byPhone = CrmInboxDirectory::match('58'.$phone);

    expect($byDocument)->toBe(['kind' => 'afiliado_corporativo', 'id' => (int) $affiliate->id, 'affiliation_id' => (int) $affiliate->affiliation_corporate_id, 'name' => 'QA CORPORATIVO PRUEBA'])
        ->and($byPhone['kind'] ?? null)->toBe('afiliado_corporativo')
        ->and($byPhone['id'] ?? null)->toBe((int) $affiliate->id)
        ->and(CrmInboxDirectory::url($byDocument, 'business'))->toContain('/business/affiliation-corporates/'.$affiliate->affiliation_corporate_id)
        ->and(CrmInboxDirectory::url($byDocument, 'administration'))->toContain('/administration/affiliation-corporates/'.$affiliate->affiliation_corporate_id)
        ->and(CrmInboxDirectory::url($byDocument, 'operations'))->toContain('/operations/affiliate-corporates/'.$affiliate->id);

    $lines = CrmInboxClient360::forMatch($byDocument);

    expect(array_column($lines, 'label'))->toContain('Afiliación corporativa')
        ->and($lines[0]['value'])->toContain((string) DB::table('affiliation_corporates')->where('id', $affiliate->affiliation_corporate_id)->value('code'));
});

it('un afiliado individual se sigue encontrando como antes', function (): void {
    $individual = App\Models\Affiliate::query()->whereNotNull('nro_identificacion')->where('nro_identificacion', 'regexp', '^[VEve]?-?[0-9]{6,9}$')->orderByDesc('id')->first();

    if ($individual === null) {
        $this->markTestSkipped('No hay afiliados individuales con cédula comparable.');
    }

    $match = CrmInboxDirectory::matchDocument((string) preg_replace('/\D+/', '', (string) $individual->nro_identificacion));

    expect($match['kind'] ?? null)->toBe('afiliado');
});
