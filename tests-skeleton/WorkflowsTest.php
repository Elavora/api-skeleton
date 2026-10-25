<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "Workflows que não existem mais" e "Workflows só do skeleton".
 * SHAs e versões do Elavora/.github conferidos só pelo formato.
 */
final class WorkflowsTest extends TestCase
{
    private const SKELETON_WORKFLOWS = [
        'skeleton-release.yml',
        'skeleton-sync-labels.yml',
        'skeleton-tests.yml',
    ];

    private static function workflow(string $name): string
    {
        return str_replace("\r\n", "\n", Skeleton::read('.github/workflows/' . $name));
    }

    /** @return list<string> */
    private static function allWorkflows(): array
    {
        $files = glob(Skeleton::path('.github/workflows') . '/*.{yml,yaml}', GLOB_BRACE) ?: [];
        $names = array_map('basename', $files);
        sort($names);

        return $names;
    }

    /** @return iterable<string, array{string}> */
    public static function removedWorkflows(): iterable
    {
        foreach (['codeql.yml', 'dependency-review.yml', 'packagist-sync.yml', 'release.yml', 'sync-labels.yml'] as $file) {
            yield $file => [$file];
        }
    }

    #[DataProvider('removedWorkflows')]
    public function testRemovedWorkflowDoesNotExist(string $file): void
    {
        self::assertFileDoesNotExist(Skeleton::path('.github/workflows/' . $file));
    }

    public function testWorkflowsAreQualityPlusTheSkeletonOnlyOnes(): void
    {
        self::assertSame(
            ['quality.yml', 'skeleton-release.yml', 'skeleton-sync-labels.yml', 'skeleton-tests.yml'],
            self::allWorkflows(),
        );
    }

    public function testNoPullRequestTemplateIsShipped(): void
    {
        foreach (['pull_request_template.md', 'PULL_REQUEST_TEMPLATE.md', 'PULL_REQUEST_TEMPLATE'] as $name) {
            self::assertFileDoesNotExist(Skeleton::path('.github/' . $name));
        }
    }

    public function testNoWorkflowCitesOldShasNorAutomationRef(): void
    {
        foreach (self::allWorkflows() as $name) {
            $yaml = self::workflow($name);
            self::assertStringNotContainsString('1e0f42a92a4aafc35522709c30bc4e83acab82f1', $yaml, $name);
            self::assertStringNotContainsString('1e0f42a', $yaml, $name);
            self::assertStringNotContainsString('6884d27a5dc5930dceee3fe39f0b112faf5c3034', $yaml, $name);
            self::assertStringNotContainsString('automation_ref', $yaml, $name);
        }
    }

    public function testEveryElavoraGithubReferenceIsAShaWithVersionComment(): void
    {
        $found = 0;
        foreach (self::allWorkflows() as $name) {
            foreach (Skeleton::usesLines(self::workflow($name)) as $line) {
                if (!str_contains($line, 'Elavora/.github')) {
                    continue;
                }
                $found++;
                self::assertMatchesRegularExpression('~Elavora/\.github/\.github/workflows/([\w.-]+\.ya?ml)@~', $line);
                preg_match('~Elavora/\.github/\.github/workflows/([\w.-]+\.ya?ml)@~', $line, $m);
                self::assertTrue(Skeleton::isElavoraCallerLine($line, $m[1]), "{$name}: {$line}");
            }
        }
        self::assertGreaterThanOrEqual(3, $found, 'quality, release e sync-labels chamam o Elavora/.github');
    }

    /** @return iterable<string, array{string}> */
    public static function skeletonWorkflows(): iterable
    {
        foreach (self::SKELETON_WORKFLOWS as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('skeletonWorkflows')]
    public function testEveryJobIsRestrictedToTheSkeletonRepository(string $name): void
    {
        $yaml = self::workflow($name);
        $jobs = Skeleton::jobNames($yaml);

        self::assertNotEmpty($jobs, $name);
        foreach ($jobs as $job) {
            $block = (string) Skeleton::jobBlock($yaml, $job);
            self::assertMatchesRegularExpression(
                '~^    if:.*github\.repository == \'Elavora/api-skeleton\'~m',
                $block,
                "{$name}: job {$job} sem a condição de repositório",
            );
        }
    }

    /** @return iterable<string, array{string}> workflows só do skeleton que chamam o Elavora/.github */
    public static function centralCallerWorkflows(): iterable
    {
        foreach (['skeleton-release.yml', 'skeleton-sync-labels.yml'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('centralCallerWorkflows')]
    public function testCentralCallersHaveIdTokenWrite(string $name): void
    {
        $yaml = self::workflow($name);
        $callers = 0;
        foreach (Skeleton::jobNames($yaml) as $job) {
            $block = (string) Skeleton::jobBlock($yaml, $job);
            if (!str_contains($block, 'Elavora/.github/')) {
                continue;
            }
            $callers++;
            self::assertMatchesRegularExpression('~^\s+id-token:\s*write\s*$~m', $block, "{$name}: {$job}");
        }
        self::assertGreaterThan(0, $callers, "{$name} chama o Elavora/.github");
    }

    public function testReleaseWorkflow(): void
    {
        $yaml = self::workflow('skeleton-release.yml');
        $top = Skeleton::topLevel($yaml);

        self::assertMatchesRegularExpression('~^name:\s*Release\s*$~m', $top);
        self::assertMatchesRegularExpression('~^  pull_request:\s*$~m', $top);
        self::assertMatchesRegularExpression('~^    types:\s*\[\s*closed\s*\]\s*$~m', $top);
        self::assertMatchesRegularExpression('~^    branches:\s*\[\s*main\s*\]\s*$~m', $top);
        self::assertMatchesRegularExpression('~^permissions:\s*\n  contents:\s*read\s*$~m', $top);

        $jobs = Skeleton::jobNames($yaml);
        self::assertCount(1, $jobs);
        $job = (string) Skeleton::jobBlock($yaml, $jobs[0]);
        self::assertMatchesRegularExpression('~^\s+contents:\s*write\s*$~m', $job);
        self::assertMatchesRegularExpression('~^\s+pull-requests:\s*read\s*$~m', $job);
        self::assertMatchesRegularExpression('~^\s+id-token:\s*write\s*$~m', $job);
        self::assertMatchesRegularExpression(
            '~^    if:\s*(?:\$\{\{\s*)?github\.event\.pull_request\.merged == true && github\.repository == \'Elavora/api-skeleton\'(?:\s*\}\})?\s*$~m',
            $job,
        );
        self::assertNull(Skeleton::scalar($job, 'packagist'), 'packagist fica no padrão (true)');
        $uses = Skeleton::usesLines($job);
        self::assertCount(1, $uses);
        self::assertTrue(Skeleton::isElavoraCallerLine($uses[0], 'release.yml'), $uses[0]);
        self::assertSame('elavora/api-skeleton', Skeleton::scalar($job, 'package_name'));
        self::assertMatchesRegularExpression(
            '~^\s+PACKAGIST_WEBHOOK_URL:\s*\$\{\{\s*secrets\.PACKAGIST_WEBHOOK_URL\s*\}\}\s*$~m',
            $job,
        );
        self::assertStringNotContainsString('secrets: inherit', $yaml);
    }

    public function testSyncLabelsWorkflow(): void
    {
        $yaml = self::workflow('skeleton-sync-labels.yml');
        $top = Skeleton::topLevel($yaml);

        self::assertMatchesRegularExpression('~^name:\s*Sincronizar labels\s*$~m', $top);
        self::assertMatchesRegularExpression('~^  schedule:\s*$~m', $top);
        self::assertMatchesRegularExpression('~cron:\s*\'17 3 \* \* 1\'~', $top);
        self::assertMatchesRegularExpression('~^  workflow_dispatch:~m', $top);
        self::assertMatchesRegularExpression('~^permissions:\s*\n  contents:\s*read\s*$~m', $top);

        $jobs = Skeleton::jobNames($yaml);
        self::assertCount(1, $jobs);
        $job = (string) Skeleton::jobBlock($yaml, $jobs[0]);
        self::assertMatchesRegularExpression('~^\s+contents:\s*read\s*$~m', $job);
        self::assertMatchesRegularExpression('~^\s+issues:\s*write\s*$~m', $job);
        self::assertMatchesRegularExpression('~^\s+id-token:\s*write\s*$~m', $job);
        $uses = Skeleton::usesLines($job);
        self::assertCount(1, $uses);
        self::assertTrue(Skeleton::isElavoraCallerLine($uses[0], 'sync-labels.yml'), $uses[0]);
    }

    /**
     * Gatilhos, permissões e os três jobs do `skeleton-tests.yml`.
     *
     * @see docs/manutencao-do-skeleton.md#workflows-só-do-skeleton
     * @see docs/manutencao-do-skeleton.md#testes-da-entrega
     * @see docs/manutencao-do-skeleton.md#matriz-de-perfis-fora-do-ci
     */
    public function testSkeletonTestsWorkflow(): void
    {
        $yaml = self::workflow('skeleton-tests.yml');
        $top = Skeleton::topLevel($yaml);

        self::assertMatchesRegularExpression('~^name:\s*Testes do skeleton\s*$~m', $top);
        self::assertMatchesRegularExpression('~^  pull_request:\s*\n    branches:\s*\[\s*main\s*\]\s*$~m', $top);
        self::assertMatchesRegularExpression('~^  push:\s*\n    branches:\s*\[\s*main\s*\]\s*$~m', $top);
        self::assertMatchesRegularExpression('~^  workflow_dispatch:~m', $top);
        self::assertMatchesRegularExpression('~^permissions:\s*\n  contents:\s*read\s*$~m', $top);

        self::assertEqualsCanonicalizing(
            ['entrega', 'build-producao', 'actionlint'],
            Skeleton::jobNames($yaml),
            'skeleton-tests.yml tem exatamente os jobs entrega, build-producao e actionlint',
        );

        $entrega = (string) Skeleton::jobBlock($yaml, 'entrega');
        self::assertStringContainsString(Skeleton::DEV_IMAGE, $entrega);
        self::assertMatchesRegularExpression('~COMPOSER_ALLOW_SUPERUSER:\s*([\'"])1\1~', $entrega);
        self::assertStringContainsString('composer install --no-interaction --no-progress --prefer-dist', $entrega);
        self::assertStringContainsString('vendor/bin/phpunit -c tests-skeleton/phpunit.xml --exclude-group docker', $entrega);

        $build = (string) Skeleton::jobBlock($yaml, 'build-producao');
        self::assertMatchesRegularExpression('~^    runs-on:\s*ubuntu-latest\s*$~m', $build);
        self::assertStringNotContainsString('php-dev', $build, 'build-producao roda direto no runner, que tem Docker');
        self::assertStringContainsString('composer install --no-interaction --no-progress --prefer-dist', $build);
        self::assertStringContainsString('vendor/bin/phpunit -c tests-skeleton/phpunit.xml --group docker', $build);

        $actionlint = (string) Skeleton::jobBlock($yaml, 'actionlint');
        self::assertStringContainsString('actionlint', $actionlint);
    }

    /**
     * Sem o job `perfis` (check `Perfil (base)`) e sem matriz enquanto as extensões não aceitam o framework 2.
     *
     * @see docs/manutencao-do-skeleton.md#matriz-de-perfis-fora-do-ci
     * @see docs/manutencao-do-skeleton.md#riscos-e-pendências
     * @see docs/manutencao-do-skeleton.md#workflows-só-do-skeleton
     */
    public function testSkeletonTestsWorkflowHasNoProfileJobNorMatrix(): void
    {
        $yaml = self::workflow('skeleton-tests.yml');

        self::assertNotContains('perfis', Skeleton::jobNames($yaml), 'o job perfis foi removido');
        self::assertNull(Skeleton::jobBlock($yaml, 'perfis'), 'o job perfis foi removido');
        self::assertDoesNotMatchRegularExpression(
            '~^\s*name:\s*[\'"]?Perfil \(~m',
            $yaml,
            'nenhum job com name: Perfil (...)',
        );
        self::assertDoesNotMatchRegularExpression('~^\s*strategy:~m', $yaml, 'sem strategy');
        self::assertStringNotContainsString('matrix', $yaml, 'sem matriz');
    }
}
