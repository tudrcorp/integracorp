<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Helpdesks\Schemas\HelpdeskInfolist as AdministrationHelpdeskInfolist;
use App\Filament\Business\Resources\Helpdesks\Schemas\HelpdeskInfolist;
use App\Filament\Marketing\Resources\Helpdesks\Schemas\HelpdeskInfolist as MarketingHelpdeskInfolist;
use App\Filament\Operations\Resources\Helpdesks\Schemas\HelpdeskInfolist as OperationsHelpdeskInfolist;
use Filament\Schemas\Schema;

it('configura el infolist helpdesk de business sin error', function (): void {
    $schema = Schema::make();
    $configured = HelpdeskInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('configura el infolist helpdesk de administration sin error', function (): void {
    $schema = Schema::make();
    $configured = AdministrationHelpdeskInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('configura el infolist helpdesk de marketing sin error', function (): void {
    $schema = Schema::make();
    $configured = MarketingHelpdeskInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('configura el infolist helpdesk de operations sin error', function (): void {
    $schema = Schema::make();
    $configured = OperationsHelpdeskInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('cada HelpdeskResource registra infolist y página view', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/HelpdeskResource.php";
    $src = file_get_contents($path);

    expect($src)->toContain('HelpdeskInfolist::configure')
        ->toContain('ViewHelpdesk::route');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);
