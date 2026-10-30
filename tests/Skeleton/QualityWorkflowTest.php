<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/** docs/manutencao-do-skeleton.md: "`.github/workflows/quality.yml`". */
final class QualityWorkflowTest extends TestCase
{
    private const FILE = '.github/workflows/quality.yml';

    private static function yaml(): string
    {
        return str_replace("\r\n", "\n", Skeleton::read(self::FILE));
    }

    public function testNameTriggersAndWorkflowPermissions(): void
    {
        $top = Skeleton::topLevel(self::yaml());

        self::assertMatchesRegularExpression('~^name:\s*Qualidade\s*$~m', $top);
        self::assertMatchesRegularExpression('~^  pull_request:\s*\n    branches:\s*\[\s*main\s*\]\s*$~m', $top);
        self::assertMatchesRegularExpression('~^  push:\s*\n    branches:\s*\[\s*main\s*\]\s*$~m', $top);
        self::assertMatchesRegularExpression('~^permissions:\s*\n  contents:\s*read\s*$~m', $top);
    }

    public function testQualityJobCallsTheCentralPipelineBySha(): void
    {
        $job = Skeleton::jobBlock(self::yaml(), 'quality');

        self::assertNotNull($job, 'job quality ausente');
        $uses = Skeleton::usesLines($job);
        self::assertCount(1, $uses);
        self::assertTrue(
            Skeleton::isElavoraCallerLine($uses[0], 'php-quality.yml'),
            "uses fora do formato SHA + comentário de versão: {$uses[0]}",
        );
        self::assertMatchesRegularExpression('~^\s+contents:\s*write\s*$~m', $job);
        self::assertMatchesRegularExpression('~^\s+pull-requests:\s*write\s*$~m', $job);
        self::assertMatchesRegularExpression('~^\s+id-token:\s*write\s*$~m', $job);
        self::assertDoesNotMatchRegularExpression('~^\s+with:~m', $job, 'O job quality não tem with.');
        self::assertDoesNotMatchRegularExpression('~^\s+secrets:~m', $job, 'O job quality não tem secrets.');
    }

    public function testImageJob(): void
    {
        $job = Skeleton::jobBlock(self::yaml(), 'imagem');

        self::assertNotNull($job, 'job imagem ausente');
        self::assertMatchesRegularExpression('~^    name:\s*(["\']?)Imagem Docker\1\s*$~m', $job);
        self::assertMatchesRegularExpression('~^    runs-on:\s*ubuntu-latest\s*$~m', $job);
        self::assertMatchesRegularExpression('~^    permissions:\s*\n      contents:\s*read\s*$~m', $job);
        self::assertDoesNotMatchRegularExpression('~^    needs:~m', $job, 'O job imagem não depende do job quality.');
        self::assertStringContainsString('docker compose up --build --detach', $job);
        self::assertStringContainsString('/health/show', $job);
        self::assertStringContainsString('{"status":"ok"}', $job);
        self::assertStringContainsString('20', $job, 'até 20 tentativas');
        self::assertStringContainsString('sleep 1', $job);
        self::assertStringContainsString('docker compose down --volumes --remove-orphans', $job);
        self::assertStringNotContainsString('composer update', $job);
    }

    public function testImageJobAlwaysTearsDownTheCompose(): void
    {
        $job = (string) Skeleton::jobBlock(self::yaml(), 'imagem');
        $steps = preg_split('~^      - ~m', $job) ?: [];
        $down = array_values(array_filter(
            $steps,
            static fn(string $step): bool => str_contains($step, 'docker compose down --volumes --remove-orphans'),
        ));

        self::assertCount(1, $down, 'um passo derruba o Compose');
        self::assertMatchesRegularExpression(
            '~^\s*(?:-\s+)?if:\s*(?:\$\{\{\s*)?always\(\)(?:\s*\}\})?\s*$~m',
            $down[0],
            'o passo de down roda sempre (if: always())',
        );
        self::assertSame($down[0], end($steps), 'o down é o último passo');
    }

    public function testEveryUsesIsPinnedBySha(): void
    {
        $uses = Skeleton::usesValues(self::yaml());

        self::assertNotEmpty($uses);
        self::assertContains(true, array_map(
            static fn(string $value): bool => str_starts_with($value, 'actions/checkout@'),
            $uses,
        ), 'o job imagem faz checkout');
        foreach ($uses as $value) {
            if (str_starts_with($value, 'Elavora/.github/')) {
                self::assertTrue(Skeleton::isElavoraCallerLine('uses: ' . $value, 'php-quality.yml'), $value);
                continue;
            }
            self::assertTrue(Skeleton::isPinnedActionRef($value), "action sem SHA completo e comentário: {$value}");
        }
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenTexts(): iterable
    {
        foreach (['1e0f42a', '@main', 'automation_ref', 'fix/estabiliza-pacote', 'dev-', 'PACKAGIST_WEBHOOK_URL', 'secrets: inherit', 'php-dev'] as $text) {
            yield $text => [$text];
        }
    }

    #[DataProvider('forbiddenTexts')]
    public function testForbiddenText(string $text): void
    {
        self::assertStringNotContainsString($text, self::yaml());
    }

    public function testNoImageInput(): void
    {
        self::assertDoesNotMatchRegularExpression('~^\s*image\s*:~m', self::yaml());
    }
}
