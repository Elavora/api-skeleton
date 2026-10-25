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
    /**
     * A única referência válida à imagem é `ghcr.io/elavora/php-dev:8.3-latest`. A documentação pode citar também
     * a imagem sem tag. Nenhuma outra versão com `-latest` é aceita, nem na documentação: ela não tem mais o passo a
     * passo de troca da versão do PHP.
     *
     * @see docs/manutencao-do-skeleton.md#versões-e-referências
     * @see docs/manutencao-do-skeleton.md#o-que-a-doc-entregue-não-diz
     */
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

    /**
     * A documentação entregue não cita `8.3-0.`, SHA de 40 hex nem `manutencao-do-skeleton.md`.
     * O guia de migração foi apagado, então a citação a ele não é mais conferida.
     *
     * @see docs/manutencao-do-skeleton.md#o-que-tests-skeleton-verifica
     * @see docs/manutencao-do-skeleton.md#guia-de-migração-removido
     */
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
