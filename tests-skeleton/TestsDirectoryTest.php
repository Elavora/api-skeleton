<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SkeletonTests\Support\Skeleton;

/**
 * Conteúdo da pasta `tests/` entregue ao projeto criado.
 *
 * `tests/` não repete os testes do `elavora/api-framework`: eles ficam no próprio framework.
 *
 * @see docs/manutencao-do-skeleton.md#o-que-fica-em-tests
 * @see docs/manutencao-do-skeleton.md#testes-que-repetiam-o-framework
 * @see docs/manutencao-do-skeleton.md#teste-do-framework-fica-no-framework
 */
final class TestsDirectoryTest extends TestCase
{
    /** Tabela "O que fica em `tests/`", em ordem alfabética. */
    private const TESTS_FILES = [
        'tests/ConventionRouteTest.php',
        'tests/HealthEndpointTest.php',
        'tests/bootstrap.php',
    ];

    /** Tabela "Testes que repetiam o framework". */
    private const REMOVED_TESTS = [
        'ExtensionConfigurationTest',
        'LogExtensionConfigurationTest',
        'RedisExtensionEnvironmentTest',
    ];

    /**
     * `tests/` tem exatamente os arquivos da tabela, nada além.
     *
     * @see docs/manutencao-do-skeleton.md#o-que-fica-em-tests
     */
    public function testTestsContainsExactlyTheDocumentedFiles(): void
    {
        $root = Skeleton::path('tests');
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = 'tests/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
        sort($files, SORT_STRING);
        $expected = self::TESTS_FILES;
        sort($expected, SORT_STRING);

        self::assertSame($expected, $files);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function removedTests(): iterable
    {
        foreach (self::REMOVED_TESTS as $name) {
            yield $name => [$name];
        }
    }

    /**
     * O teste que repetia o framework não existe mais em `tests/`.
     *
     * @param string $name nome da classe (e do arquivo) removida
     *
     * @see docs/manutencao-do-skeleton.md#testes-que-repetiam-o-framework
     */
    #[DataProvider('removedTests')]
    public function testFrameworkTestIsRemoved(string $name): void
    {
        self::assertFileDoesNotExist(Skeleton::path('tests/' . $name . '.php'));
    }

    /**
     * Nenhum arquivo entregue cita o teste removido. Ficam de fora `docs/manutencao-do-skeleton.md` e
     * `tests-skeleton/`, que são só do skeleton.
     *
     * @param string $name nome da classe (e do arquivo) removida
     *
     * @see docs/manutencao-do-skeleton.md#testes-que-repetiam-o-framework
     */
    #[DataProvider('removedTests')]
    public function testNoDeliveredFileCitesTheRemovedTest(string $name): void
    {
        foreach (Skeleton::deliveredTextFiles() as $file) {
            self::assertStringNotContainsString($name, Skeleton::read($file), $file);
        }
    }
}
