<?php

declare(strict_types=1);

use App\Support\Filament\Administration\InvoiceDocumentNumber;

it('deja solo digitos aunque el documento traiga prefijo V o J', function (mixed $value, ?string $expected): void {
    expect(InvoiceDocumentNumber::digitsOnly($value))->toBe($expected);
})->with([
    'v con guion' => ['V-16007868', '16007868'],
    'v sin guion' => ['V16007868', '16007868'],
    'v minuscula' => ['v-16007868', '16007868'],
    'solo digitos' => ['16007868', '16007868'],
    'j con guion' => ['J-000111222', '000111222'],
    'j sin guion' => ['J000111222', '000111222'],
    'con puntos' => ['V-16.007.868', '16007868'],
    'vacio' => ['', null],
    'nulo' => [null, null],
    'espacios' => ['  V-16007868  ', '16007868'],
]);
