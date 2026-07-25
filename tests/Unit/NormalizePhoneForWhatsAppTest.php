<?php

declare(strict_types=1);

use App\Services\HelpdeskTicketAssigneeWhatsAppService;

uses(Tests\TestCase::class);

it('normaliza telefonos enviados como string', function (): void {
    expect(HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp('04121234567'))
        ->toBe('+584121234567');
});

it('normaliza telefonos enviados como int por claves de array php', function (): void {
    $phoneRecipients = [];
    $phoneRecipients['4121234567'] = 'Andrés Gomez';

    $rawPhone = array_key_first($phoneRecipients);

    expect($rawPhone)->toBeInt()
        ->and(HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone))
        ->toBe('+584121234567');
});

it('retorna null cuando el telefono es null o vacio', function (): void {
    expect(HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp(null))->toBeNull()
        ->and(HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp(''))->toBeNull()
        ->and(HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp('   '))->toBeNull();
});
