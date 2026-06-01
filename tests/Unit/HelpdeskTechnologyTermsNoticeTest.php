<?php

declare(strict_types=1);

use App\Support\HelpdeskTechnologyTermsNotice;

it('expone el aviso de tecnologia y sistemas', function (): void {
    $html = HelpdeskTechnologyTermsNotice::bodyHtml()->toHtml();

    expect($html)
        ->toContain('Departamento de Tecnología y Sistemas')
        ->toContain('24 horas')
        ->toContain('Agradecemos su paciencia');
});

it('integra pestaña de compromiso con checkbox obligatorio en el schema', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php');

    expect($src)
        ->toContain("Tab::make('Compromiso de atención')")
        ->toContain('HelpdeskTechnologyTermsNotice::ACCEPTANCE_FIELD')
        ->toContain('->accepted()')
        ->toContain('->dehydrated(false)')
        ->toContain("->hiddenOn('edit')");
});
