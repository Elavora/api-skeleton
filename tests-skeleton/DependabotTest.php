<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/** docs/manutencao-do-skeleton.md: "`.github/dependabot.yml`". */
final class DependabotTest extends TestCase
{
    /** @return array<string, string> ecossistema => bloco do item, na ordem do arquivo */
    private static function items(): array
    {
        $yaml = str_replace("\r\n", "\n", Skeleton::read('.github/dependabot.yml'));
        $parts = preg_split('~^(?=\s*-\s+package-ecosystem:)~m', $yaml) ?: [];
        array_shift($parts);
        $items = [];
        foreach ($parts as $part) {
            $ecosystem = (string) Skeleton::scalar($part, 'package-ecosystem');
            $items[$ecosystem] = $part;
        }

        return $items;
    }

    public function testVersionTwo(): void
    {
        self::assertMatchesRegularExpression('~^version:\s*2\s*$~m', Skeleton::read('.github/dependabot.yml'));
    }

    public function testOnlyComposerAndGithubActionsInThisOrder(): void
    {
        self::assertSame(['composer', 'github-actions'], array_keys(self::items()));
    }

    public function testNoDockerNorDevcontainersEcosystem(): void
    {
        $yaml = Skeleton::read('.github/dependabot.yml');

        self::assertDoesNotMatchRegularExpression('~package-ecosystem:\s*["\']?docker~', $yaml);
        self::assertDoesNotMatchRegularExpression('~package-ecosystem:\s*["\']?devcontainers~', $yaml);
    }

    public function testEachItemScheduleLimitAndLabels(): void
    {
        foreach (self::items() as $ecosystem => $item) {
            self::assertSame('/', Skeleton::scalar($item, 'directory'), $ecosystem);
            self::assertSame('weekly', Skeleton::scalar($item, 'interval'), $ecosystem);
            self::assertSame('monday', Skeleton::scalar($item, 'day'), $ecosystem);
            self::assertSame('09:00', Skeleton::scalar($item, 'time'), $ecosystem);
            self::assertSame('America/Sao_Paulo', Skeleton::scalar($item, 'timezone'), $ecosystem);
            self::assertSame('5', Skeleton::scalar($item, 'open-pull-requests-limit'), $ecosystem);
            self::assertSame(['dependencies'], Skeleton::listValue($item, 'labels'), $ecosystem);
        }
    }

    public function testComposerCommitMessage(): void
    {
        $item = self::items()['composer'] ?? '';

        self::assertSame('chore', Skeleton::scalar($item, 'prefix'));
        self::assertSame('chore', Skeleton::scalar($item, 'prefix-development'));
        self::assertSame('scope', Skeleton::scalar($item, 'include'));
    }

    public function testGithubActionsCommitMessage(): void
    {
        $item = self::items()['github-actions'] ?? '';

        self::assertSame('ci', Skeleton::scalar($item, 'prefix'));
        self::assertSame('scope', Skeleton::scalar($item, 'include'));
    }
}
