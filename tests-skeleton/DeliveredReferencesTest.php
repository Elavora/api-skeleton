<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "Versões e referências" e "O que `tests-skeleton/` verifica"
 * (imagem de desenvolvimento e documentação entregue).
 */
final class DeliveredReferencesTest extends TestCase
{
    public function testDeliveredFilesOnlyUseTheTrackingDevelopmentImage(): void
    {
        $files = Skeleton::deliveredTextFiles();
        self::assertContains('.devcontainer/devcontainer.json', $files);

        foreach ($files as $file) {
            $content = Skeleton::read($file);
            foreach (Skeleton::imageReferences($content) as $reference) {
                if (str_ends_with($file, '.md') && $reference === 'ghcr.io/elavora/php-dev') {
                    continue; // a documentação pode citar a imagem sem tag
                }
                if (str_ends_with($file, '.md') && preg_match('~^ghcr\.io/elavora/php-dev:\d+\.\d+-latest$~', $reference) === 1) {
                    continue; // ex.: a troca para 8.4-latest em "Versão do PHP"
                }
                self::assertTrue(
                    Skeleton::isValidImageReference($reference),
                    "{$file}: referência inválida à imagem de desenvolvimento: {$reference}",
                );
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function deliveredDocs(): iterable
    {
        foreach (['README.md', 'docs/USO.md', 'docs/ambiente-e-ci.md'] as $file) {
            yield $file => [$file];
        }
    }

    #[DataProvider('deliveredDocs')]
    public function testDeliveredDocumentationCitesNoFixedReference(string $file): void
    {
        $content = Skeleton::read($file);
        if ($file === 'README.md') {
            $content = Skeleton::withoutMarkedBlock($content, Skeleton::README_START, Skeleton::README_END);
        }

        self::assertStringNotContainsString('8.3-0.', $content);
        self::assertDoesNotMatchRegularExpression('~(?<![0-9a-fA-F])[0-9a-fA-F]{40}(?![0-9a-fA-F])~', $content);
        self::assertStringNotContainsString('manutencao-do-skeleton.md', $content);
        self::assertStringNotContainsString('MIGRACAO-BOOTSTRAP', $content);
    }

    public function testNoDeliveredFileCitesTheMigrationGuide(): void
    {
        $files = Skeleton::deliveredTextFiles();
        self::assertNotContains('docs/MIGRACAO-BOOTSTRAP.md', $files);

        foreach ($files as $file) {
            $content = Skeleton::read($file);
            if ($file === 'README.md') {
                $content = Skeleton::withoutMarkedBlock($content, Skeleton::README_START, Skeleton::README_END);
            }
            if ($file === '.gitattributes') {
                $content = Skeleton::withoutMarkedBlock($content, Skeleton::ATTRIBUTES_START, Skeleton::ATTRIBUTES_END);
            }
            self::assertStringNotContainsString('MIGRACAO-BOOTSTRAP', $content, $file);
        }
    }

    public function testReadmeHasExactlyOneSkeletonBlock(): void
    {
        $lines = array_map('trim', preg_split('~\R~', Skeleton::read('README.md')) ?: []);

        self::assertSame(1, count(array_keys($lines, Skeleton::README_START, true)));
        self::assertSame(1, count(array_keys($lines, Skeleton::README_END, true)));
        self::assertLessThan(
            array_search(Skeleton::README_END, $lines, true),
            array_search(Skeleton::README_START, $lines, true),
        );
    }
}
