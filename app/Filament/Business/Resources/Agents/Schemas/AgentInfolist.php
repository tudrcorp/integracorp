<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Agents\Schemas;

use App\Filament\Shared\CommercialStructure\AgentInfolist as SharedAgentInfolist;
use Filament\Schemas\Schema;

class AgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return SharedAgentInfolist::configure($schema);
    }
}
