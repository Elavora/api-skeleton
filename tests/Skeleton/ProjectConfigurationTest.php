<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * `composer.lock`, ordem do build no `Dockerfile` e `env_file` opcional do `docker-compose.yml` entregues pelo
 * skeleton.
 *
 * Saiu de `tests/` porque confere arquivos do skeleton, e não a aplicação.
 *
 * @see docs/manutencao-do-skeleton.md#testes-movidos-para-testsskeleton
 * @see docs/manutencao-do-skeleton.md#teste-de-arquivo-do-skeleton-fica-em-testsskeleton
 */
final class ProjectConfigurationTest extends TestCase
{
    /**
     * O `composer.lock` existe, é JSON válido e tem `content-hash`, `packages` e `packages-dev`.
     *
     * @see docs/manutencao-do-skeleton.md#testes-movidos-para-testsskeleton
     */
    public function testComposerLockIsVersionedForTheProject(): void
    {
        self::assertFileExists(Skeleton::path('composer.lock'));
        $lock = json_decode(Skeleton::read('composer.lock'), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($lock);
        self::assertArrayHasKey('content-hash', $lock);
        self::assertArrayHasKey('packages', $lock);
        self::assertArrayHasKey('packages-dev', $lock);
    }

    /**
     * No `Dockerfile`, a cópia do lock vem antes do `composer install`, que vem antes do `COPY . .`, e não há
     * `composer update`.
     *
     * @see docs/manutencao-do-skeleton.md#testes-movidos-para-testsskeleton
     */
    public function testDockerBuildInstallsTheVersionedLockBeforeCopyingTheApplication(): void
    {
        $dockerfile = Skeleton::read('Dockerfile');
        $copyDependencies = strpos($dockerfile, 'COPY composer.json composer.lock ./');
        $install = strpos(
            $dockerfile,
            'RUN composer install --no-interaction --no-progress --prefer-dist --no-dev --optimize-autoloader',
        );
        $copyApplication = strpos($dockerfile, 'COPY . .');

        self::assertIsInt($copyDependencies);
        self::assertIsInt($install);
        self::assertIsInt($copyApplication);
        self::assertLessThan($install, $copyDependencies);
        self::assertLessThan($copyApplication, $install);
        self::assertStringNotContainsString('composer update', $dockerfile);
    }

    /**
     * O `docker-compose.yml` injeta o `.env` opcional pelo `env_file`.
     *
     * @see docs/manutencao-do-skeleton.md#testes-movidos-para-testsskeleton
     */
    public function testComposeInjectsOptionalEnvironmentFile(): void
    {
        $compose = Skeleton::read('docker-compose.yml');

        self::assertStringContainsString('path: ${ELAVORA_ENV_FILE:-.env}', $compose);
        self::assertStringContainsString('required: false', $compose);
    }
}
