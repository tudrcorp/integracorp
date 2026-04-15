<?php

declare(strict_types=1);

use App\Http\Controllers\Business\MarkHelpdeskTicketInProgressController;
use App\Livewire\BusinessHelpdeskTicketsTicker;

it('expone openTicketNotification en el ticker y el controlador HTTP para marcar en proceso', function (): void {
    $ticker = new ReflectionClass(BusinessHelpdeskTicketsTicker::class);

    expect($ticker->hasMethod('openTicketNotification'))->toBeTrue()
        ->and($ticker->getMethod('openTicketNotification')->isPublic())->toBeTrue()
        ->and(class_exists(MarkHelpdeskTicketInProgressController::class))->toBeTrue();
});
