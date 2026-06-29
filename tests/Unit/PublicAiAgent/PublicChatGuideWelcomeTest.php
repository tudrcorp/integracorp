<?php

declare(strict_types=1);

use App\Services\PublicAiAgent\IntentSlotFiller;

uses(Tests\TestCase::class);

it('presenta mensaje de bienvenida de guia chat de forma humanizada', function (): void {
    $slotFiller = new IntentSlotFiller;

    expect($slotFiller->publicChatGuideWelcomeMessage())
        ->toContain('GUÍA-CHAT')
        ->toContain('Quiero!')
        ->toContain('**ayuda**')
        ->toContain('dispositivo móvil')
        ->toContain('**?**')
        ->toContain('**Nuestros Planes**')
        ->not->toContain('¿Qué quieres hacer?')
        ->toContain('Asesores Comerciales')
        ->toContain('acompañarte paso a paso');
});

it('detecta solicitud de ayuda en el chat publico', function (): void {
    $slotFiller = new IntentSlotFiller;

    expect($slotFiller->isHelpRequest('ayuda'))->toBeTrue()
        ->and($slotFiller->isHelpRequest('AYUDA'))->toBeTrue()
        ->and($slotFiller->isHelpRequest('necesito ayuda'))->toBeTrue()
        ->and($slotFiller->isHelpRequest('cotizar'))->toBeFalse();
});

it('muestra contacto de asesores comerciales al pedir ayuda', function (): void {
    $slotFiller = new IntentSlotFiller;

    $message = $slotFiller->publicChatHelpMessage(
        'https://wa.me/584127018390',
        '+58 412 701 8390',
    );

    expect($message)
        ->toContain('Asesores Comerciales')
        ->toContain('[+58 412 701 8390](https://wa.me/584127018390)')
        ->toContain('0412 701 8390')
        ->toContain('**Quiero!**')
        ->toContain('dispositivo móvil')
        ->toContain('**?**')
        ->not->toContain('¿Qué quieres hacer?');
});
