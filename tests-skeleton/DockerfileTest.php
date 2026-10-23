<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "`Dockerfile`" (build sem Git).
 * O composer.lock é conferido só pelo formato do dist, não pelas versões.
 */
final class DockerfileTest extends TestCase
{
    private const DEPENDENCIES_STAGE = <<<'DOCKERFILE'
        FROM runtime AS dependencies

        COPY --from=composer /usr/bin/composer /usr/bin/composer
        COPY composer.json composer.lock ./

        # Instala dependencias de producao a partir dos zips do Packagist (--prefer-dist).
        # Pacotes de desenvolvimento ficam fora da imagem final.
        RUN composer install --no-interaction --no-progress --prefer-dist --no-dev --optimize-autoloader
        DOCKERFILE;

    private static function dockerfile(): string
    {
        return str_replace("\r\n", "\n", Skeleton::read('Dockerfile'));
    }

    /** Dockerfile sem a palavra git (em qualquer caixa) e sem .composer-install-deps. */
    private static function dockerfileNeedsNoGit(string $dockerfile): bool
    {
        return preg_match('~git~i', $dockerfile) !== 1 && !str_contains($dockerfile, '.composer-install-deps');
    }

    /** Trecho do estágio dependencies, da linha FROM até antes do próximo FROM. */
    private static function dependenciesStage(string $dockerfile): ?string
    {
        if (preg_match('~^FROM runtime AS dependencies\s*$.*?(?=^FROM\s|\z)~ms', $dockerfile, $m) !== 1) {
            return null;
        }

        return trim($m[0]);
    }

    /**
     * Pacotes de `packages` sem dist do tipo zip.
     *
     * @param array<string, mixed> $lock
     * @return list<string>
     */
    private static function packagesWithoutZipDist(array $lock): array
    {
        $invalid = [];
        foreach ($lock['packages'] ?? [] as $index => $package) {
            $type = is_array($package) ? ($package['dist']['type'] ?? null) : null;
            if ($type !== 'zip') {
                $invalid[] = is_array($package) ? (string) ($package['name'] ?? "#{$index}") : "#{$index}";
            }
        }

        return $invalid;
    }

    public function testDockerfileHasNoGit(): void
    {
        self::assertTrue(
            self::dockerfileNeedsNoGit(self::dockerfile()),
            'O Dockerfile não pode citar git (em nenhuma caixa) nem .composer-install-deps.',
        );
    }

    public function testDependenciesStageIsExactlyTheDocumented(): void
    {
        self::assertSame(self::DEPENDENCIES_STAGE, self::dependenciesStage(self::dockerfile()));
    }

    public function testEveryProductionPackageInTheLockHasAZipDist(): void
    {
        $lock = json_decode(Skeleton::read('composer.lock'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($lock);
        self::assertNotEmpty($lock['packages'] ?? [], 'composer.lock sem packages');

        self::assertSame([], self::packagesWithoutZipDist($lock), 'pacotes de produção sem dist zip');
    }

    // ------------------------------------------------------------------ exemplos da documentação

    /** @return iterable<string, array{string, bool}> */
    public static function dockerfileExamples(): iterable
    {
        yield 'estágio dependencies da doc' => [self::DEPENDENCIES_STAGE, true];
        yield 'apk add com .composer-install-deps' => [
            "FROM runtime AS dependencies\nRUN apk add --no-cache --virtual .composer-install-deps git\n",
            false,
        ];
        yield 'apk add git em outro estágio' => ["FROM php:8.3-cli-alpine AS runtime\nRUN apk add --no-cache git\n", false];
        yield 'Git com maiúscula' => ["# Instala o Git\nFROM runtime AS dependencies\n", false];
        yield 'GIT em caixa alta' => ["ENV GIT_TERMINAL_PROMPT=0\n", false];
        yield 'só o apk del' => ["RUN composer install && apk del .composer-install-deps\n", false];
    }

    #[DataProvider('dockerfileExamples')]
    public function testDockerfileRuleFollowsTheDocumentedExamples(string $dockerfile, bool $valid): void
    {
        self::assertSame($valid, self::dockerfileNeedsNoGit($dockerfile));
    }

    /** @return iterable<string, array{array<string, mixed>, list<string>}> */
    public static function lockExamples(): iterable
    {
        $zip = ['type' => 'zip', 'url' => 'https://example.test/a.zip', 'reference' => 'abc', 'shasum' => ''];
        $source = ['type' => 'git', 'url' => 'https://example.test/b.git', 'reference' => 'def'];

        yield 'dist zip passa' => [['packages' => [['name' => 'acme/a', 'dist' => $zip, 'source' => $source]]], []];
        yield 'outra versão com dist zip passa' => [
            ['packages' => [['name' => 'acme/a', 'version' => '9.9.9', 'dist' => $zip]]],
            [],
        ];
        yield 'só source git falha' => [['packages' => [['name' => 'acme/b', 'source' => $source]]], ['acme/b']];
        yield 'dist de outro tipo falha' => [
            ['packages' => [['name' => 'acme/c', 'dist' => ['type' => 'tar'] + $zip]]],
            ['acme/c'],
        ];
        yield 'packages-dev não entra na regra' => [
            ['packages' => [['name' => 'acme/a', 'dist' => $zip]], 'packages-dev' => [['name' => 'acme/d', 'source' => $source]]],
            [],
        ];
    }

    /**
     * @param array<string, mixed> $lock
     * @param list<string> $invalid
     */
    #[DataProvider('lockExamples')]
    public function testLockRuleChecksOnlyTheDistFormat(array $lock, array $invalid): void
    {
        self::assertSame($invalid, self::packagesWithoutZipDist($lock));
    }
}
