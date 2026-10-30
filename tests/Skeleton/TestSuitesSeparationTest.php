<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "Como `tests/Skeleton/` convive com `tests/`".
 * A suíte da aplicação (raiz) não executa `tests/Skeleton/`; a do skeleton roda só o namespace `SkeletonTests`.
 *
 * @see docs/manutencao-do-skeleton.md#como-testsskeleton-convive-com-tests
 */
final class TestSuitesSeparationTest extends TestCase
{
    /**
     * Classes listadas por `phpunit --list-tests` (linhas ` - Classe::metodo`).
     *
     * @return list<string>
     */
    private static function listedClasses(string $config): array
    {
        $result = Skeleton::run(
            [PHP_BINARY, Skeleton::path('vendor/bin/phpunit'), '-c', $config, '--list-tests', '--do-not-cache-result'],
            Skeleton::root(),
        );
        self::assertSame(0, $result['code'], $result['stdout'] . $result['stderr']);
        preg_match_all('~^ - ([^:\s]+)::~m', $result['stdout'], $matches);

        return array_values(array_unique($matches[1]));
    }

    /** `<testsuite>` do `phpunit.xml` da raiz, exatamente como na doc (ignorando a indentação). */
    public function testRootTestsuiteHasTheMarkedExclude(): void
    {
        $lines = array_map('trim', preg_split('~\R~', Skeleton::read('phpunit.xml')) ?: []);
        $start = array_search('<testsuite name="Elavora API Skeleton">', $lines, true);
        self::assertIsInt($start, '<testsuite> ausente');

        self::assertSame(
            [
                '<testsuite name="Elavora API Skeleton">',
                '<directory>tests</directory>',
                '<!-- skeleton:inicio -->',
                '<exclude>tests/Skeleton</exclude>',
                '<!-- skeleton:fim -->',
                '</testsuite>',
            ],
            array_slice($lines, $start, 6),
        );
    }

    public function testRootPhpUnitXmlStillUsesTheApplicationBootstrap(): void
    {
        $xml = simplexml_load_string(Skeleton::read('phpunit.xml'));
        self::assertNotFalse($xml);

        self::assertSame('tests/bootstrap.php', (string) $xml['bootstrap']);
        self::assertSame(
            ['tests'],
            array_map(static fn($n): string => trim((string) $n), $xml->xpath('/phpunit/testsuites/testsuite/directory') ?: []),
        );
        self::assertSame(
            ['tests/Skeleton'],
            array_map(static fn($n): string => trim((string) $n), $xml->xpath('/phpunit/testsuites/testsuite/exclude') ?: []),
        );
    }

    public function testSkeletonPhpUnitXmlIsRelativeToItsOwnFolder(): void
    {
        self::assertFileExists(Skeleton::path('tests/Skeleton/phpunit.xml'));
        $xml = simplexml_load_string(Skeleton::read('tests/Skeleton/phpunit.xml'));
        self::assertNotFalse($xml);

        self::assertSame('bootstrap.php', (string) $xml['bootstrap']);
        self::assertSame(
            ['.'],
            array_map(static fn($n): string => trim((string) $n), $xml->xpath('/phpunit/testsuites/testsuite/directory') ?: []),
        );
    }

    public function testSkeletonBootstrapLoadsTheRootAutoloadTwoFoldersUp(): void
    {
        $bootstrap = Skeleton::read('tests/Skeleton/bootstrap.php');

        self::assertStringContainsString("dirname(__DIR__, 2) . '/vendor/autoload.php'", $bootstrap);
        self::assertMatchesRegularExpression("~require\\s+__DIR__\\s*\\.\\s*'/Support/Skeleton\\.php'~", $bootstrap);
        self::assertFileExists(Skeleton::path('tests/Skeleton/Support/Skeleton.php'));
    }

    public function testApplicationBootstrapDoesNotKnowTheSkeletonSuite(): void
    {
        self::assertStringNotContainsString('Skeleton', Skeleton::read('tests/bootstrap.php'));
    }

    public function testComposerAutoloadDevDoesNotChange(): void
    {
        $composer = json_decode(Skeleton::read('composer.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['Tests\\' => 'tests/'], $composer['autoload-dev']['psr-4'] ?? null);
        self::assertStringNotContainsString('SkeletonTests', Skeleton::read('composer.json'));
    }

    public function testApplicationSuiteDoesNotListAnySkeletonTestsClass(): void
    {
        $classes = self::listedClasses('phpunit.xml');

        self::assertNotEmpty($classes);
        foreach ($classes as $class) {
            self::assertStringStartsNotWith('SkeletonTests\\', $class);
        }
        // Os testes da aplicacao em tests/ nao tem namespace (classes globais).
        self::assertContains('HealthEndpointTest', array_map(
            static fn(string $class): string => ltrim($class, '\\'),
            $classes,
        ));
    }

    public function testSkeletonSuiteListsOnlySkeletonTestsClasses(): void
    {
        $classes = self::listedClasses('tests/Skeleton/phpunit.xml');

        self::assertNotEmpty($classes);
        foreach ($classes as $class) {
            self::assertStringStartsWith('SkeletonTests\\', $class);
        }
        self::assertContains('SkeletonTests\\TestSuitesSeparationTest', $classes);
    }
}
