<?php

declare(strict_types=1);

use App\Support\Filament\BusinessGlobalSearch;

it('normaliza documentos y detecta codigos', function (): void {
    expect(BusinessGlobalSearch::normalizeDocument('V-12.345.678'))->toBe('V12345678')
        ->and(BusinessGlobalSearch::normalizeDocument('j-12345678-9'))->toBe('J123456789')
        ->and(BusinessGlobalSearch::looksLikeCode('AGT-000123'))->toBeTrue()
        ->and(BusinessGlobalSearch::looksLikeCode('Maria Perez'))->toBeFalse()
        ->and(BusinessGlobalSearch::looksLikeDocument('V-12345678'))->toBeTrue()
        ->and(BusinessGlobalSearch::extractAgentDisplayCodeId('AGT-00045'))->toBe(45)
        ->and(BusinessGlobalSearch::extractAgentDisplayCodeId('agt0007'))->toBe(7)
        ->and(BusinessGlobalSearch::extractAgentDisplayCodeId('no-code'))->toBeNull();
});

it('configura busqueda global en el panel de negocios', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/BusinessPanelProvider.php');

    expect($provider)->not->toBeFalse()
        ->toContain('->globalSearch(')
        ->toContain('globalSearchDebounce')
        ->toContain('globalSearchKeyBindings')
        ->toContain('GlobalSearchPosition::Topbar');
});

it('habilita busqueda global optimizada en recursos clave de negocios', function (): void {
    $resources = [
        'Agencies/AgencyResource.php' => ['code', 'rif', 'ci_responsable', 'name_corporative'],
        'Agents/AgentResource.php' => ['code_agent', 'ci', 'rif', 'extractAgentDisplayCodeId'],
        'Affiliations/AffiliationResource.php' => ['nro_identificacion_ti', 'affiliates', 'code'],
        'AffiliationCorporates/AffiliationCorporateResource.php' => ['name_corporate', 'corporateAffiliates', 'rif'],
        'IndividualQuotes/IndividualQuoteResource.php' => ['full_name', 'code'],
        'CorporateQuotes/CorporateQuoteResource.php' => ['full_name', 'rif', 'code'],
        'Companies/CompanyResource.php' => ['name', 'rif'],
        'CompanyAssociates/CompanyAssociateResource.php' => ['identity_card', 'full_name'],
        'WhiteCompanies/WhiteCompanyResource.php' => ['name', 'rif'],
        'Users/UserResource.php' => ['name', 'email', 'identity_card', 'phone'],
    ];

    foreach ($resources as $relative => $needles) {
        $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/'.$relative;
        $src = file_get_contents($path);

        expect($src)->not->toBeFalse()
            ->toContain('ConfiguresBusinessGlobalSearch')
            ->toContain('businessGlobalSearchSelectColumns');

        foreach ($needles as $needle) {
            expect($src)->toContain($needle);
        }
    }

    expect(file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Concerns/ConfiguresBusinessGlobalSearch.php'
    ))->toContain('getGlobalSearchResults');
});

it('define trait BusinessGlobalSearchTest busqueda global sin dividir terminos y con limite', function (): void {
    $trait = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Concerns/ConfiguresBusinessGlobalSearch.php'
    );

    expect($trait)->not->toBeFalse()
        ->toContain('shouldSplitGlobalSearchTerms')
        ->toContain('BusinessGlobalSearch::constrain')
        ->toContain('getGlobalSearchResultsLimit');
});

it('incluye migracion de indices para busqueda global de negocios', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_08_06_204741_add_business_global_search_indexes.php'
    );

    expect($migration)->not->toBeFalse()
        ->toContain('agencies_code_index')
        ->toContain('agents_ci_index')
        ->toContain('affiliations_code_index')
        ->toContain('affiliates_nro_identificacion_index')
        ->toContain('affiliation_corporates_rif_index')
        ->toContain('corporate_quotes_code_index')
        ->toContain('companies_rif_index')
        ->toContain('white_companies_name_index')
        ->and(file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_27_131200_add_users_business_global_search_indexes.php'
        ))->toContain('users_name_index')
        ->toContain('users_identity_card_index')
        ->toContain('users_phone_index');
});
