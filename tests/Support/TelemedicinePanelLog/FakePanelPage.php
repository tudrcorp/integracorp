<?php

declare(strict_types=1);

namespace Tests\Support\TelemedicinePanelLog;

use Filament\Pages\Page;

/**
 * Página de Filament mínima para probar que la bitácora anota las pantallas
 * abiertas. Ver {@see FakeConsultationForm} sobre por qué no es anónima.
 */
class FakePanelPage extends Page
{
    public static function make(): self
    {
        $page = new self;
        $page->setId('tpe-page-'.uniqid());

        return $page;
    }
}
