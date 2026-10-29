<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "`composer.json`" e "`composer.json` do skeleton".
 * Restrições conferidas só pelo major. O composer.lock não é lido.
 */
final class ComposerJsonTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function composer(): array
    {
        $data = json_decode(Skeleton::read('composer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    public function testFrameworkComesFromPackagistWithMajorTwo(): void
    {
        $composer = self::composer();

        self::assertArrayHasKey('elavora/api-framework', $composer['require'] ?? []);
        $constraint = $composer['require']['elavora/api-framework'];
        self::assertIsString($constraint);
        self::assertTrue(
            Skeleton::hasMajor($constraint, 2),
            "elavora/api-framework deve começar com ^2. e não usar dev- (recebido: {$constraint})",
        );
        self::assertArrayNotHasKey('repositories', $composer, 'O framework vem do Packagist: sem repositório VCS.');
    }

    public function testNoDevBranchConstraintAnywhere(): void
    {
        $composer = self::composer();
        foreach (['require', 'require-dev'] as $section) {
            foreach ($composer[$section] ?? [] as $package => $constraint) {
                self::assertStringNotContainsString('dev-', (string) $constraint, "{$section}.{$package}");
            }
        }
    }

    public function testCodeStandardsIsADevelopmentDependencyWithMajorOne(): void
    {
        $composer = self::composer();

        self::assertArrayHasKey('elavora/code-standards', $composer['require-dev'] ?? []);
        self::assertArrayNotHasKey('elavora/code-standards', $composer['require'] ?? []);
        $constraint = (string) $composer['require-dev']['elavora/code-standards'];
        self::assertTrue(
            Skeleton::hasMajorWithMinorAtLeast($constraint, 1, 1),
            "elavora/code-standards deve começar com ^1. e minor 1 ou maior, que tem o lint (recebido: {$constraint})",
        );
    }

    public function testLintScriptIsTheCodeStandardsLint(): void
    {
        $scripts = self::composer()['scripts'] ?? [];

        self::assertSame('elavora-standards lint', $scripts['lint'] ?? null);
        self::assertContains('@lint', (array) ($scripts['check'] ?? []), 'o check continua chamando @lint');
    }

    public function testLocalLintScriptIsRemoved(): void
    {
        self::assertFileDoesNotExist(Skeleton::path('tools/lint.php'));
    }

    public function testPhpCsFixerIsNotADependency(): void
    {
        $composer = self::composer();

        self::assertArrayNotHasKey('friendsofphp/php-cs-fixer', $composer['require'] ?? []);
        self::assertArrayNotHasKey('friendsofphp/php-cs-fixer', $composer['require-dev'] ?? []);
    }

    public function testInstallAndUpdateSyncTheStyleRules(): void
    {
        $scripts = self::composer()['scripts'] ?? [];

        self::assertSame(['Elavora\\CodeStandards\\Composer::sync'], $scripts['post-install-cmd'] ?? null);
        self::assertSame(['Elavora\\CodeStandards\\Composer::sync'], $scripts['post-update-cmd'] ?? null);
    }

    public function testPostCreateProjectRunsTheCleanupScript(): void
    {
        $scripts = self::composer()['scripts'] ?? [];

        self::assertSame(['@php tools/skeleton-post-create.php'], $scripts['post-create-project-cmd'] ?? null);
    }

    public function testTestScriptCallsPhpUnitDirectly(): void
    {
        $test = self::composer()['scripts']['test'] ?? null;

        self::assertIsString($test, 'O script test chama o PHPUnit direto, para receber argumentos extras.');
        self::assertStringContainsString('vendor/bin/phpunit', $test);
    }

    public function testPhpVersionIsTheSameEverywhere(): void
    {
        self::assertSame('>=8.3', self::composer()['require']['php'] ?? null);
        self::assertMatchesRegularExpression('~^FROM php:8\.3-cli-alpine\b~m', Skeleton::read('Dockerfile'));
    }
}
