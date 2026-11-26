<?php

declare(strict_types=1);

use App\Support\CrmInbox\CrmInboxCopilot;

/**
 * Resumen que SolIA arma al derivar (`sesion.resumen_derivacion`): bloques fijos, en el orden en
 * que el analista los lee, sin bloques vacíos.
 */
it('ordena los bloques para el analista y omite los vacíos', function (): void {
    $blocks = CrmInboxCopilot::handoffSummary([
        'siguiente_paso' => 'Llamarlo para completar la afiliación',
        'necesidad' => 'Plan para él y su esposa',
        'animo' => '   ',
        'motivo_derivacion' => 'Quiere afiliarse',
        'propuesta' => 'N.º 9012 · Ideal US$ 3.000 · US$ 116 trimestral · PDF enviado',
        'extra' => 'no se muestra',
    ]);

    expect(array_column($blocks, 'key'))->toBe(['motivo_derivacion', 'necesidad', 'propuesta', 'siguiente_paso'])
        ->and($blocks[0])->toBe(['key' => 'motivo_derivacion', 'label' => 'Por qué lo derivó', 'value' => 'Quiere afiliarse']);
});

it('sin resumen o con un formato inesperado no muestra bloques', function (mixed $summary): void {
    expect(CrmInboxCopilot::handoffSummary($summary))->toBe([]);
})->with([
    'nulo' => [null],
    'texto' => ['▸ Necesidad: plan'],
    'lista vacía' => [[]],
    'valores no textuales' => [['necesidad' => ['a'], 'animo' => null]],
]);

it('recorta un bloque demasiado largo', function (): void {
    $blocks = CrmInboxCopilot::handoffSummary(['necesidad' => str_repeat('a', 900)]);

    expect(mb_strlen($blocks[0]['value']))->toBe(600);
});

it('la bandeja pinta los bloques y conserva el resumen en texto como respaldo', function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/crm/pages/atencion-whatsapp.blade.php');

    expect($view)->toContain('<dl class="crm-handoff-summary">')
        ->and($view)->toContain("@elseif (\$copilot['summary'])")
        ->and($view)->toContain('white-space: pre-line');
});
