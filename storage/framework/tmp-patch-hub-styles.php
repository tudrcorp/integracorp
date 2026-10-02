<?php

declare(strict_types=1);

$path = 'c:/laragon/www/integracorp/resources/views/integracorp/partials/hub-styles.blade.php';
$text = file_get_contents($path);

$start = strpos($text, '    .ic-module-card__panel-content {');
$end = strpos($text, '    /* Solo móvil: 2 columnas, imagen completa, scroll permitido, saludo bajo el logo */');

if ($start === false || $end === false) {
    fwrite(STDERR, "markers not found\n");
    exit(1);
}

$new = <<<'CSS'
    .ic-module-card__panel-content {
        /* rem fijo: el aire no depende del alto del card ni del zoom */
        --ic-module-title-air: 0.7rem;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0 0.65rem var(--ic-module-title-air);
        text-align: center;
        transform: translateY(0);
        transition: transform 0.42s cubic-bezier(0.33, 1.12, 0.48, 1);
    }

    .ic-module-card:hover .ic-module-card__panel-content,
    .ic-module-card:focus-visible .ic-module-card__panel-content {
        transform: translateY(-0.35rem);
    }

    .ic-module-card__reveal {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.4rem;
        width: 100%;
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        transform: translateY(0.55rem);
        transition:
            max-height 0.42s cubic-bezier(0.33, 1.12, 0.48, 1),
            opacity 0.34s ease 0.04s,
            transform 0.42s cubic-bezier(0.33, 1.12, 0.48, 1);
    }

    .ic-module-card:hover .ic-module-card__reveal,
    .ic-module-card:focus-visible .ic-module-card__reveal {
        max-height: 9rem;
        opacity: 1;
        transform: translateY(0);
    }

    .ic-module-card__badge {
        display: inline-block;
        padding: 0.22rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.58rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgba(15, 23, 42, 0.92);
        background: rgba(255, 255, 255, 0.9);
    }

    .ic-module-card__objective {
        margin: 0;
        max-width: 100%;
        font-size: 0.62rem;
        line-height: 1.4;
        color: rgba(241, 245, 249, 0.94);
        text-shadow: 0 1px 6px rgba(0, 0, 0, 0.45);
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
    }

    .ic-module-card__tags {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.25rem;
        margin: 0;
        padding: 0;
        list-style: none;
        max-width: 100%;
    }

    .ic-module-card__tag {
        padding: 0.18rem 0.4rem;
        border-radius: 9999px;
        font-size: 0.52rem;
        font-weight: 600;
        line-height: 1.2;
        color: rgba(248, 250, 252, 0.95);
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .ic-module-card__name {
        margin: 0;
        flex-shrink: 0;
        font-size: 0.95rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        line-height: 1.25;
        color: #fff;
        text-shadow:
            0 2px 10px rgba(0, 0, 0, 0.65),
            0 1px 3px rgba(0, 0, 0, 0.85);
        transition: margin-top 0.42s cubic-bezier(0.33, 1.12, 0.48, 1);
    }

    .ic-module-card:hover .ic-module-card__name,
    .ic-module-card:focus-visible .ic-module-card__name {
        margin-top: var(--ic-module-title-air);
    }

    @media (hover: none) {
        .ic-module-card__reveal {
            max-height: 0;
            opacity: 0;
            transform: translateY(0.55rem);
        }

        .ic-module-card__panel-bg {
            height: 0;
            opacity: 0;
        }
    }

CSS;

file_put_contents($path, substr($text, 0, $start).$new.substr($text, $end));
echo "patched\n";
