<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\Schemas;


use App\Models\City;
use App\Models\Agent;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\Country;
use App\Models\Affiliation;
use Filament\Schemas\Schema;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\AffiliationCorporate;
use App\Models\DetailCorporateQuote;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AffiliationCorporateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                
            ]);
    }
}