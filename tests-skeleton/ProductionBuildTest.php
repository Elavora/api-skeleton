<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "O que `tests-skeleton/` verifica" (build de produção).
 * O elavora/code-standards fica fora da instalação --no-dev e o build continua funcionando.
 */
#[Group('docker')]
final class ProductionBuildTest extends TestCase
{
    private ?string $image = null;

    protected function setUp(): void
    {
        $probe = Skeleton::run(['sh', '-c', 'command -v docker']);
        if ($probe['code'] !== 0 || trim($probe['stdout']) === '') {
            self::markTestSkipped('comando docker não existe neste ambiente');
        }
    }

    protected function tearDown(): void
    {
        if ($this->image !== null) {
            Skeleton::run(['docker', 'image', 'rm', '--force', $this->image]);
            $this->image = null;
        }
    }

    public function testProductionImageBuildsWithoutCodeStandards(): void
    {
        $this->image = 'elavora-skeleton-build-test:' . bin2hex(random_bytes(6));

        $build = Skeleton::run(['docker', 'build', '--tag', $this->image, Skeleton::root()]);
        self::assertSame(0, $build['code'], "docker build falhou:\n" . $build['stderr'] . $build['stdout']);

        $find = Skeleton::run([
            'docker', 'run', '--rm', '--entrypoint', 'sh', $this->image, '-c',
            'find / -xdev -type d -path "*/vendor/elavora/code-standards" 2>/dev/null; true',
        ]);
        self::assertSame(0, $find['code'], $find['stderr']);
        self::assertSame('', trim($find['stdout']), 'a imagem de produção não pode ter vendor/elavora/code-standards');
    }
}
