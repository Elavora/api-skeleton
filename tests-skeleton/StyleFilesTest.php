<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "Regras de estilo" e "`phpunit.xml`".
 * O conteúdo do bloco do .editorconfig não é comparado: um PR do Dependabot pode mudá-lo.
 */
final class StyleFilesTest extends TestCase
{
    private const EDITORCONFIG_START = '# >>> elavora/code-standards >>>';
    private const EDITORCONFIG_END = '# <<< elavora/code-standards <<<';

    public function testPhpCsFixerStubIsExactlyThePackageStub(): void
    {
        $expected = "<?php\n\nrequire_once __DIR__ . '/vendor/elavora/code-standards/src/PhpCsFixer.php';\n\n"
            . "return Elavora\\CodeStandards\\PhpCsFixer::config(__DIR__);\n";
        $actual = str_replace("\r\n", "\n", Skeleton::read('.php-cs-fixer.dist.php'));

        self::assertSame(rtrim($expected), rtrim($actual));
    }

    public function testEditorconfigHasExactlyOneBlockAndNothingOutside(): void
    {
        $lines = preg_split('~\R~', Skeleton::read('.editorconfig')) ?: [];
        $trimmed = array_map('trim', $lines);

        self::assertSame(1, count(array_keys($trimmed, self::EDITORCONFIG_START, true)), 'um marcador de início');
        self::assertSame(1, count(array_keys($trimmed, self::EDITORCONFIG_END, true)), 'um marcador de fim');

        $start = array_search(self::EDITORCONFIG_START, $trimmed, true);
        $end = array_search(self::EDITORCONFIG_END, $trimmed, true);
        self::assertIsInt($start);
        self::assertIsInt($end);
        self::assertLessThan($end, $start);
        self::assertGreaterThan($start + 1, $end, 'o bloco não é vazio');

        $outside = array_merge(array_slice($trimmed, 0, $start), array_slice($trimmed, $end + 1));
        self::assertSame([], array_values(array_filter($outside, static fn(string $l): bool => $l !== '')));
    }

    public function testGitignoreHasCentralScriptsFolderAndPersonalOverride(): void
    {
        $lines = array_map('trim', preg_split('~\R~', Skeleton::read('.gitignore')) ?: []);

        self::assertContains('/.elavora/', $lines);
        self::assertContains('/.php-cs-fixer.php', $lines);
    }

    public function testPhpUnitSourceIncludesOnlyApplicationDirectories(): void
    {
        $xml = simplexml_load_string(Skeleton::read('phpunit.xml'));
        self::assertNotFalse($xml);

        $values = static fn(array $nodes): array => array_map(
            static fn($node): string => trim((string) $node),
            $nodes,
        );

        self::assertSame(['app'], $values($xml->xpath('/phpunit/source/include/directory') ?: []));
        self::assertSame(['app/Worker'], $values($xml->xpath('/phpunit/source/exclude/directory') ?: []));
        self::assertCount(1, $xml->xpath('/phpunit/source/include/*') ?: [], 'só um item no <include>');
        self::assertCount(1, $xml->xpath('/phpunit/source/exclude/*') ?: [], 'só um item no <exclude>');
        self::assertCount(2, $xml->xpath('/phpunit/source/*') ?: [], '<source> tem só <include> e <exclude>');
    }
}
