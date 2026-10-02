<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\Schemas\ProspectAgentForm;
use App\Filament\Business\Resources\ProspectAgents\Schemas\ProspectAgentInfolist;
use Filament\Schemas\Schema;

it('configura el formulario de prospectos con contactos adicionales', function (): void {
    $configured = ProspectAgentForm::configure(Schema::make());

    expect($configured)->toBeInstanceOf(Schema::class);

    $source = file_get_contents(dirname(__DIR__, 3).'/app/Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentForm.php');

    expect($source)
        ->toContain("Repeater::make('prospectAgentContacts')")
        ->toContain('->maxItems(3)')
        ->toContain("TextInput::make('website')")
        ->toContain("Textarea::make('social_networks')")
        ->toContain("Textarea::make('address')");
});

it('muestra dirección, web, redes y contactos en el infolist del prospecto', function (): void {
    $configured = ProspectAgentInfolist::configure(Schema::make());

    expect($configured)->toBeInstanceOf(Schema::class);

    $source = file_get_contents(dirname(__DIR__, 3).'/app/Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentInfolist.php');

    expect($source)
        ->toContain("RepeatableEntry::make('prospectAgentContacts')")
        ->toContain("TextEntry::make('website')")
        ->toContain("TextEntry::make('social_networks')")
        ->toContain("TextEntry::make('address')");
});
