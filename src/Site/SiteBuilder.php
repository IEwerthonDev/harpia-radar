<?php

declare(strict_types=1);

namespace HarpiaRadar\Site;

use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renderiza os templates Twig e grava a página estática.
 */
final readonly class SiteBuilder
{
    private Environment $twig;

    public function __construct(private string $templatesDir)
    {
        $this->twig = new Environment(new FilesystemLoader($templatesDir), [
            'autoescape' => 'html',
            'strict_variables' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $page dados montados pelo PageBuilder
     *
     * @return int tamanho da página em bytes (HTML + CSS)
     */
    public function build(array $page, string $snapshotPath, string $outputDir): int
    {
        if (!is_dir($outputDir) && !mkdir($outputDir, recursive: true) && !is_dir($outputDir)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório %s.', $outputDir));
        }

        $html = $this->twig->render('index.html.twig', $page);
        $css = (string) file_get_contents($this->templatesDir . '/style.css');

        self::write($outputDir . '/index.html', $html);
        self::write($outputDir . '/style.css', $css);
        self::write($outputDir . '/logo.png', (string) file_get_contents($this->templatesDir . '/logo.png'));
        self::write($outputDir . '/snapshot.json', (string) file_get_contents($snapshotPath));

        return strlen($html) + strlen($css);
    }

    private static function write(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Não foi possível gravar %s.', $path));
        }
    }
}
