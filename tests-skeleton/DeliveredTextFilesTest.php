<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "Arquivos que os testes tratam como entregues".
 * Os caches criados aqui são removidos no fim; um cache que já existia volta com o conteúdo original.
 */
final class DeliveredTextFilesTest extends TestCase
{
    private const ROOT_CACHE = '.phpunit.result.cache';
    private const SKELETON_TESTS_CACHE = 'tests-skeleton/.phpunit.result.cache';

    /** @var array<string, string|null> conteúdo original de cada cache, null quando não existia */
    private array $originals = [];

    protected function setUp(): void
    {
        foreach ([self::ROOT_CACHE, self::SKELETON_TESTS_CACHE] as $relative) {
            $path = Skeleton::path($relative);
            $this->originals[$relative] = is_file($path) ? (string) file_get_contents($path) : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originals as $relative => $content) {
            $path = Skeleton::path($relative);
            if ($content === null) {
                @unlink($path);
                continue;
            }
            file_put_contents($path, $content);
        }
        $this->originals = [];
    }

    /**
     * Com um `.phpunit.result.cache` na raiz que cita `Permission.php`, a lista de arquivos entregues não o inclui.
     *
     * @see docs/manutencao-do-skeleton.md#arquivos-que-os-testes-tratam-como-entregues
     */
    public function testRootPhpUnitResultCacheIsNotDelivered(): void
    {
        file_put_contents(Skeleton::path(self::ROOT_CACHE), 'Permission.php');

        $files = Skeleton::deliveredTextFiles();

        self::assertContains('README.md', $files);
        self::assertNotContains(self::ROOT_CACHE, $files);
    }

    /**
     * `tests-skeleton/.phpunit.result.cache` fica de fora, por estar em `tests-skeleton/`.
     *
     * @see docs/manutencao-do-skeleton.md#arquivos-que-os-testes-tratam-como-entregues
     */
    public function testSkeletonTestsPhpUnitResultCacheIsNotDelivered(): void
    {
        file_put_contents(Skeleton::path(self::SKELETON_TESTS_CACHE), 'Permission.php');

        self::assertNotContains(self::SKELETON_TESTS_CACHE, Skeleton::deliveredTextFiles());
    }
}
