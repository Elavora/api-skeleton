<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProjectConfigurationTest extends TestCase
{
    public function testComposerLockIsVersionedForTheProject(): void
    {
        $lockPath = dirname(__DIR__) . '/composer.lock';

        self::assertFileExists($lockPath);
        $lock = json_decode((string) file_get_contents($lockPath), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($lock);
        self::assertArrayHasKey('content-hash', $lock);
        self::assertArrayHasKey('packages', $lock);
        self::assertArrayHasKey('packages-dev', $lock);
    }

    public function testDockerBuildInstallsTheVersionedLockBeforeCopyingTheApplication(): void
    {
        $dockerfile = (string) file_get_contents(dirname(__DIR__) . '/Dockerfile');
        $copyDependencies = strpos($dockerfile, 'COPY composer.json composer.lock ./');
        $install = strpos(
            $dockerfile,
            'RUN composer install --no-interaction --no-progress --prefer-dist --no-dev --optimize-autoloader'
        );
        $copyApplication = strpos($dockerfile, 'COPY . .');

        self::assertIsInt($copyDependencies);
        self::assertIsInt($install);
        self::assertIsInt($copyApplication);
        self::assertLessThan($install, $copyDependencies);
        self::assertLessThan($copyApplication, $install);
        self::assertStringNotContainsString('composer update', $dockerfile);
    }

    public function testComposeInjectsOptionalEnvironmentFile(): void
    {
        $compose = (string) file_get_contents(dirname(__DIR__) . '/docker-compose.yml');

        self::assertStringContainsString('path: ${ELAVORA_ENV_FILE:-.env}', $compose);
        self::assertStringContainsString('required: false', $compose);
    }
}
