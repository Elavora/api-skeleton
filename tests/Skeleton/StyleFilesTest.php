<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "Regras de estilo" e "`phpunit.xml`".
 * O conteúdo do bloco do .editorconfig não é comparado: um PR do Dependabot pode mudá-lo.
 */
final class StyleFilesTest extends TestCase
{
    private const PHPUNIT_CACHE = '.phpunit.result.cache';

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

    /**
     * O .gitignore entregue tem as três linhas, cada uma com o texto exato.
     *
     * @see docs/manutencao-do-skeleton.md#linhas-do-gitignore
     */
    public function testGitignoreHasCentralScriptsFolderPersonalOverrideAndPhpUnitCache(): void
    {
        $lines = self::gitignoreLines(Skeleton::read('.gitignore'));

        self::assertContains('/.elavora/', $lines);
        self::assertContains('/.php-cs-fixer.php', $lines);
        self::assertContains(self::PHPUNIT_CACHE, $lines);
    }

    /**
     * O .gitignore entregue não cita `tests/Skeleton/`, pasta que só existe no skeleton.
     *
     * @see docs/manutencao-do-skeleton.md#linhas-do-gitignore
     */
    public function testGitignoreDoesNotCiteTheSkeletonTestsFolder(): void
    {
        self::assertTrue(self::gitignoreKeepsToDeliveredPaths(Skeleton::read('.gitignore')));
    }

    /**
     * Exemplos da doc sobre citar `tests/Skeleton/` no .gitignore entregue.
     *
     * @see docs/manutencao-do-skeleton.md#linhas-do-gitignore
     */
    #[DataProvider('gitignoreFolderExamples')]
    public function testGitignoreFolderRuleFollowsTheDocExamples(string $gitignore, bool $expected): void
    {
        self::assertSame($expected, self::gitignoreKeepsToDeliveredPaths($gitignore));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function gitignoreFolderExamples(): iterable
    {
        yield 'passa: linha sem barra' => [".phpunit.result.cache\n", true];
        yield 'falha: linha que cita tests/Skeleton/' => ["/tests/Skeleton/.phpunit.result.cache\n", false];
    }

    /**
     * Com o .gitignore entregue, o Git ignora o cache do PHPUnit na raiz e em `tests/Skeleton/`.
     *
     * @see docs/manutencao-do-skeleton.md#linhas-do-gitignore
     */
    public function testDeliveredGitignoreIgnoresPhpUnitCacheInBothPlaces(): void
    {
        $codes = self::checkIgnoreCodes(Skeleton::read('.gitignore'));

        self::assertSame(0, $codes[self::PHPUNIT_CACHE], 'git check-ignore -q .phpunit.result.cache');
        self::assertSame(
            0,
            $codes['tests/Skeleton/' . self::PHPUNIT_CACHE],
            'git check-ignore -q tests/Skeleton/.phpunit.result.cache',
        );
    }

    /**
     * Passa: a linha sem barra ignora os dois caminhos. Falha: `/.phpunit.result.cache` não ignora o caminho de
     * `tests/Skeleton/`.
     *
     * @see docs/manutencao-do-skeleton.md#linhas-do-gitignore
     */
    #[DataProvider('checkIgnoreExamples')]
    public function testCheckIgnoreFollowsTheDocExamples(string $gitignore, bool $ignoresBoth): void
    {
        $codes = self::checkIgnoreCodes($gitignore);

        $both = $codes[self::PHPUNIT_CACHE] === 0 && $codes['tests/Skeleton/' . self::PHPUNIT_CACHE] === 0;

        self::assertSame($ignoresBoth, $both);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function checkIgnoreExamples(): iterable
    {
        yield 'passa: linha sem barra' => [".phpunit.result.cache\n", true];
        yield 'falha: só /.phpunit.result.cache' => ["/.phpunit.result.cache\n", false];
    }

    /** @return list<string> */
    private static function gitignoreLines(string $gitignore): array
    {
        return array_map('trim', preg_split('~\R~', $gitignore) ?: []);
    }

    /** Nenhuma linha cita `tests/Skeleton`. */
    private static function gitignoreKeepsToDeliveredPaths(string $gitignore): bool
    {
        foreach (self::gitignoreLines($gitignore) as $line) {
            if (str_contains($line, 'tests/Skeleton')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Código de saída de `git check-ignore -q` para cada caminho do cache, num repositório Git temporário que só
     * tem o .gitignore dado. Pula o teste quando o comando git não existe.
     *
     * @return array<string, int>
     */
    private static function checkIgnoreCodes(string $gitignore): array
    {
        $probe = Skeleton::run(['sh', '-c', 'command -v git']);
        if ($probe['code'] !== 0 || trim($probe['stdout']) === '') {
            self::markTestSkipped('comando git não existe neste ambiente');
        }

        $dir = Skeleton::tempDir('skeleton-gitignore');
        try {
            $init = Skeleton::run(['git', 'init', '--quiet', $dir]);
            self::assertSame(0, $init['code'], 'git init: ' . $init['stderr']);
            file_put_contents($dir . '/.gitignore', $gitignore);

            $codes = [];
            foreach ([self::PHPUNIT_CACHE, 'tests/Skeleton/' . self::PHPUNIT_CACHE] as $path) {
                $result = Skeleton::run(['git', 'check-ignore', '-q', $path], $dir);
                self::assertContains($result['code'], [0, 1], "git check-ignore {$path}: " . $result['stderr']);
                $codes[$path] = $result['code'];
            }

            return $codes;
        } finally {
            Skeleton::removeTree($dir);
        }
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
