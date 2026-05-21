<?php

declare(strict_types=1);

it('define tabla y enum BusinessAgendaCorporativaSocialPublicationsTest publicaciones de redes en agenda corporativa', function (): void {
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_05_16_212617_create_corporate_agenda_social_publications_table.php';
    $attachmentsMigrationPath = dirname(__DIR__, 2).'/database/migrations/2026_05_16_222727_add_attachments_to_corporate_agenda_social_publications_table.php';
    $enumPath = dirname(__DIR__, 2).'/app/Enums/CorporateAgendaSocialPlatform.php';
    $catalogPath = dirname(__DIR__, 2).'/app/Support/CorporateAgendaSocialPlatformCatalog.php';
    $modelPath = dirname(__DIR__, 2).'/app/Models/CorporateAgendaSocialPublication.php';
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/AgendaCorporativa.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/agenda-corporativa.blade.php';
    $marketingFormPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/agenda-corporativa-marketing-form.blade.php';
    $marketingSidebarPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/agenda-corporativa-marketing-sidebar.blade.php';
    $iconPath = dirname(__DIR__, 2).'/resources/views/components/corporate-agenda-social-icon.blade.php';

    expect(file_get_contents($migrationPath))
        ->toContain("Schema::create('corporate_agenda_social_publications'")
        ->toContain("->date('publication_date')")
        ->toContain("->string('platform')")
        ->toContain('cas_publication_date_platform_unique');

    expect(file_get_contents($attachmentsMigrationPath))
        ->toContain("Schema::table('corporate_agenda_social_publications'")
        ->toContain("->json('attachments')->nullable()");

    expect(file_get_contents($enumPath))
        ->toContain('case Instagram = \'instagram\'')
        ->toContain('case Youtube = \'youtube\'')
        ->toContain('case X = \'x\'')
        ->toContain('case Facebook = \'facebook\'');

    expect(file_get_contents($catalogPath))
        ->toContain('CorporateAgendaSocialPlatformCatalog')
        ->toContain('Instagram')
        ->toContain('YouTube')
        ->toContain('X (Twitter)');

    expect(file_get_contents($modelPath))
        ->toContain('CorporateAgendaSocialPlatform::class')
        ->toContain("'attachments' => 'array'");

    expect(file_get_contents($pagePath))
        ->toContain('public string $modalWorkspace = \'activities\'')
        ->toContain('saveSocialPublications')
        ->toContain('setModalWorkspace')
        ->toContain('social_platforms')
        ->toContain('CorporateAgendaSocialPublication::query()')
        ->toContain('socialPublicationUploadsByPlatform')
        ->toContain('socialPublicationBriefByPlatform')
        ->toContain('removeSocialPublicationAttachment')
        ->toContain('getSelectedDateSocialPublicationReferencePreviewsProperty');

    expect(file_get_contents($viewPath))
        ->toContain('setModalWorkspace(\'marketing\')')
        ->toContain('x-corporate-agenda-social-icon')
        ->toContain('social_badges')
        ->toContain('agenda-corporativa-marketing-form');

    expect(file_get_contents($marketingFormPath))
        ->toContain('Calendario publicitario')
        ->toContain('saveSocialPublications')
        ->toContain('socialPublicationForm.platforms')
        ->toContain('wire:model="socialPublicationUploadsByPlatform.')
        ->toContain('socialPublicationBriefByPlatform.')
        ->toContain('Archivos guardados')
        ->toContain('Nuevos archivos por guardar');

    expect(file_get_contents($marketingSidebarPath))
        ->toContain('selectedDateSocialPublicationReferencePreviews')
        ->toContain('Referencias visuales');

    expect(file_get_contents($iconPath))
        ->toContain("'instagram' => 'image/instagram.png'")
        ->toContain("'youtube' => 'image/youtube.png'")
        ->toContain("'x' => 'image/twitter.png'")
        ->toContain("'facebook' => 'image/communication.png'")
        ->toContain('asset($imagePath)')
        ->toContain("@case('facebook')");
});
