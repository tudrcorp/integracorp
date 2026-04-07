<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

it('relation manager case view url includes from=patient', function () {
    $url = TelemedicineCaseResource::getUrl('view', ['record' => 1])
        .'?relation=consultations&from=patient';

    expect($url)->toContain('from=patient')->toContain('relation=consultations');
});

it('consultations relation manager appends from=patient to consultation url when request has it', function () {
    $request = Request::create('/test', 'GET', ['from' => 'patient']);
    app()->instance('request', $request);

    $base = 'https://example.test/operations/telemedicine-consultation-patients/5';
    $url = $base;
    if (request()->query('from') === 'patient') {
        $url .= (str_contains($url, '?') ? '&' : '?').'from=patient';
    }

    expect($url)->toEndWith('from=patient');
});
