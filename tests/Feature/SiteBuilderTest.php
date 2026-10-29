<?php

declare(strict_types=1);

use HarpiaRadar\Site\PageBuilder;
use HarpiaRadar\Site\SiteBuilder;

function buildSite(): string
{
    $output = sys_get_temp_dir() . '/radar-site-' . bin2hex(random_bytes(4));
    $page = (new PageBuilder())->build(siteCatalog(), siteSnapshot(), healthConfig(), 'https://github.com/IEwerthonDev/harpia-radar');
    (new SiteBuilder(projectRoot('templates')))->build($page, fixturePath('snapshot/site.json'), $output);

    return $output;
}

function siteHtml(): string
{
    return (string) file_get_contents(buildSite() . '/index.html');
}

it('writes the page, the stylesheet, the logo and the open data', function (): void {
    $output = buildSite();

    expect($output . '/index.html')->toBeFile()
        ->and($output . '/style.css')->toBeFile()
        ->and(file_get_contents($output . '/logo.png'))->toBe(file_get_contents(projectRoot('templates/logo.png')))
        ->and(file_get_contents($output . '/snapshot.json'))->toBe(file_get_contents(fixturePath('snapshot/site.json')));
});

it('renders categories, badges, links and the collection date', function (): void {
    expect(siteHtml())
        ->toContain('<h2 id="categoria-fiscal">Fiscal e notas</h2>')
        ->toContain('href="https://github.com/nfephp-org/sped-nfe"')
        ->toContain('Precisa de mãos')
        ->toContain('Ativo, sem release recente')
        ->toContain('Última alteração em 19/05/2024')
        ->toContain('<time datetime="2026-10-28T06:00:00Z">28/10/2026</time>');
});

it('works without JavaScript or third-party resources', function (): void {
    $html = siteHtml();

    expect($html)->not->toContain('<script')
        ->not->toMatch('~(src|href)="//~')
        ->not->toMatch('~<link[^>]+href="https?://~')
        ->not->toContain('<iframe');
});

it('stays under 200 KB', function (): void {
    $output = buildSite();

    expect(filesize($output . '/index.html') + filesize($output . '/style.css'))->toBeLessThan(200 * 1024);
});

it('never shows author identities', function (): void {
    expect(siteHtml())->not->toContain('author-');
});

it('has an accessible structure', function (): void {
    $document = Dom\HTMLDocument::createFromString(siteHtml(), LIBXML_NOERROR);

    expect($document->documentElement?->getAttribute('lang'))->toBe('pt-BR')
        ->and($document->querySelectorAll('main'))->toHaveCount(1)
        ->and($document->querySelectorAll('h1'))->toHaveCount(1)
        ->and($document->querySelector('a.skip-link')?->getAttribute('href'))->toBe('#conteudo')
        ->and($document->getElementById('conteudo'))->not->toBeNull();

    $ids = array_map(static fn (Dom\Element $e): string => (string) $e->getAttribute('id'), iterator_to_array($document->querySelectorAll('[id]')));
    expect(array_unique($ids))->toHaveCount(count($ids));

    $level = 1;
    foreach ($document->querySelectorAll('h1, h2, h3, h4') as $heading) {
        $next = (int) substr($heading->tagName, 1);
        expect($next)->toBeLessThanOrEqual($level + 1);
        $level = $next;
    }

    foreach ($document->querySelectorAll('a') as $link) {
        expect(trim((string) $link->textContent))->not->toBe('');
    }

    foreach ($document->querySelectorAll('a[href^="#"]') as $link) {
        expect($document->getElementById(substr((string) $link->getAttribute('href'), 1)))->not->toBeNull();
    }
});

it('hides decorative icons from screen readers', function (): void {
    $document = Dom\HTMLDocument::createFromString(siteHtml(), LIBXML_NOERROR);

    foreach ($document->querySelectorAll('.badge-icon') as $icon) {
        expect($icon->getAttribute('aria-hidden'))->toBe('true');
    }

    expect($document->querySelectorAll('.badge-icon')->length)->toBeGreaterThan(0);
});
