<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "Arquivos só do skeleton" e "`.gitattributes`".
 * A lista SKELETON_ONLY do script, o bloco do .gitattributes e a tabela da página batem.
 */
final class SkeletonOnlyListTest extends TestCase
{
    /** @param list<string> $paths @return list<string> */
    private static function normalize(array $paths): array
    {
        return array_map(static fn(string $p): string => rtrim(ltrim($p, '/'), '/'), $paths);
    }

    /** @return list<string> */
    private static function scriptList(): array
    {
        $script = Skeleton::read(Skeleton::SCRIPT);
        self::assertMatchesRegularExpression('~SKELETON_ONLY\s*=\s*\[~', $script, 'constante SKELETON_ONLY ausente');
        preg_match('~SKELETON_ONLY\s*=\s*\[(.*?)\]\s*;~s', $script, $m);
        preg_match_all('~([\'"])(.*?)\1~', $m[1] ?? '', $items);

        return $items[2];
    }

    /** @return list<string> */
    private static function documentedList(): array
    {
        $doc = Skeleton::read('docs/manutencao-do-skeleton.md');
        $section = preg_split('~^### Arquivos só do skeleton\s*$~m', $doc)[1] ?? '';
        $section = preg_split('~^#{2,3} ~m', $section)[0];
        preg_match_all('~^\| `([^`]+)` \|~m', $section, $m);

        return $m[1];
    }

    public function testDocumentedTableMatchesTheExpectedList(): void
    {
        self::assertSame(Skeleton::SKELETON_ONLY, self::normalize(self::documentedList()));
    }

    public function testScriptConstantMatchesTheDocumentedTableInOrder(): void
    {
        self::assertSame(self::normalize(self::documentedList()), self::normalize(self::scriptList()));
    }

    public function testGitattributesBlockExportIgnoresEverythingButTheScript(): void
    {
        $content = str_replace("\r\n", "\n", Skeleton::read('.gitattributes'));
        $lines = explode("\n", rtrim($content, "\n"));

        $start = array_search(Skeleton::ATTRIBUTES_START, $lines, true);
        $end = array_search(Skeleton::ATTRIBUTES_END, $lines, true);
        self::assertIsInt($start, 'marcador # skeleton:inicio ausente');
        self::assertIsInt($end, 'marcador # skeleton:fim ausente');
        self::assertSame(count($lines) - 1, $end, 'o bloco fica no fim do arquivo');

        $expected = [];
        foreach (Skeleton::SKELETON_ONLY as $path) {
            if ($path !== Skeleton::SCRIPT) {
                $expected[] = '/' . $path . ' export-ignore';
            }
        }
        self::assertSame($expected, array_slice($lines, $start + 1, $end - $start - 1));
        self::assertStringNotContainsString('skeleton-post-create.php', $content);
    }

    public function testGitattributesKeepsTheOriginalRules(): void
    {
        // "O resto do .gitattributes não muda": tudo antes do bloco é o arquivo original.
        $original = implode("\n", [
            '* text=auto eol=lf',
            '',
            '*.bat text eol=crlf',
            '*.cmd text eol=crlf',
            '*.gif binary',
            '*.ico binary',
            '*.jpeg binary',
            '*.jpg binary',
            '*.pdf binary',
            '*.png binary',
            '*.webp binary',
            '',
            '*.sh text eol=lf',
            '*.yml text eol=lf',
            '*.yaml text eol=lf',
        ]);
        $content = str_replace("\r\n", "\n", Skeleton::read('.gitattributes'));
        $before = explode("\n" . Skeleton::ATTRIBUTES_START . "\n", "\n" . $content, 2);

        self::assertCount(2, $before, 'marcador # skeleton:inicio ausente');
        self::assertSame($original, trim($before[0], "\n"));
    }

    public function testEveryDocumentedPathExistsInTheSkeleton(): void
    {
        foreach (Skeleton::SKELETON_ONLY as $path) {
            self::assertFileExists(Skeleton::path($path));
        }
        self::assertDirectoryExists(Skeleton::path('tests-skeleton'));
    }
}
