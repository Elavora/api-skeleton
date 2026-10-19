<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "`tools/skeleton-post-create.php`".
 * O script roda sempre numa cópia, em pasta temporária, nunca no repositório.
 */
final class PostCreateScriptTest extends TestCase
{
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /** @var list<string> */
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $dir) {
            Skeleton::removeTree($dir);
        }
        $this->temporary = [];
    }

    private function tempDir(string $prefix): string
    {
        $dir = Skeleton::tempDir($prefix);
        $this->temporary[] = $dir;

        return $dir;
    }

    /** @return array{code: int, stdout: string, stderr: string} */
    private function runScript(string $root, ?string $cwd = null): array
    {
        self::assertFileExists($root . '/' . Skeleton::SCRIPT);

        return Skeleton::run([PHP_BINARY, $root . '/' . Skeleton::SCRIPT], $cwd ?? $this->tempDir('cwd'));
    }

    /** Projeto mínimo com o script do repositório e os arquivos dados. @param array<string, string> $files */
    private function fixture(array $files): string
    {
        $root = $this->tempDir('fixture');
        mkdir($root . '/tools', 0777, true);
        self::assertFileExists(Skeleton::path(Skeleton::SCRIPT), 'script de limpeza ausente no repositório');
        copy(Skeleton::path(Skeleton::SCRIPT), $root . '/' . Skeleton::SCRIPT);
        foreach ($files as $path => $content) {
            if (!is_dir(dirname($root . '/' . $path))) {
                mkdir(dirname($root . '/' . $path), 0777, true);
            }
            file_put_contents($root . '/' . $path, $content);
        }

        return $root;
    }

    private static function composerWithPostCreate(): string
    {
        return json_encode([
            'name' => 'acme/api',
            'scripts' => [
                'test' => '@php vendor/bin/phpunit -c phpunit.xml',
                'post-create-project-cmd' => ['@php tools/skeleton-post-create.php'],
            ],
        ], self::JSON_FLAGS) . "\n";
    }

    private static function readmeWithBlock(string $eol = "\n"): string
    {
        return implode($eol, [
            '## Documentação',
            '',
            '- [Guia de uso](docs/USO.md)',
            '',
            Skeleton::README_START,
            '## Só no repositório do skeleton',
            '',
            '- [Manutenção do skeleton](docs/manutencao-do-skeleton.md)',
            Skeleton::README_END,
            '',
        ]);
    }

    private static function attributesWithBlock(): string
    {
        return "* text=auto eol=lf\n\n" . Skeleton::ATTRIBUTES_START . "\n/tests-skeleton export-ignore\n"
            . Skeleton::ATTRIBUTES_END . "\n";
    }

    /** @return array<string, string> */
    private static function defaultFiles(): array
    {
        return [
            'README.md' => self::readmeWithBlock(),
            '.gitattributes' => self::attributesWithBlock(),
            'composer.json' => self::composerWithPostCreate(),
            'tools/lint.php' => "<?php\n",
        ];
    }

    // ------------------------------------------------------------------ cópia completa do repositório

    public function testCleansACopyOfTheRepository(): void
    {
        $root = $this->tempDir('repo');
        Skeleton::copyRepository($root);
        $before = Skeleton::snapshot($root);
        $readme = (string) file_get_contents($root . '/README.md');
        $attributes = (string) file_get_contents($root . '/.gitattributes');
        $composer = json_decode((string) file_get_contents($root . '/composer.json'), false, flags: JSON_THROW_ON_ERROR);

        // Diretório atual com marcadores próprios: o script usa dirname(__DIR__), não o cwd.
        $decoy = $this->tempDir('decoy');
        file_put_contents($decoy . '/README.md', self::readmeWithBlock());
        file_put_contents($decoy . '/composer.json', self::composerWithPostCreate());
        $decoyBefore = Skeleton::snapshot($decoy);

        $result = $this->runScript($root, $decoy);

        self::assertSame(0, $result['code'], $result['stderr']);
        foreach (Skeleton::SKELETON_ONLY as $path) {
            self::assertFileDoesNotExist($root . '/' . $path, "{$path} deveria ter sido removido");
        }
        self::assertFileExists($root . '/tools/lint.php');
        self::assertSame(['quality.yml'], array_map('basename', glob($root . '/.github/workflows/*') ?: []));

        $readmeAfter = (string) file_get_contents($root . '/README.md');
        self::assertStringNotContainsString('skeleton:inicio', $readmeAfter);
        self::assertStringNotContainsString('skeleton:fim', $readmeAfter);
        self::assertSame(Skeleton::withoutMarkedBlock($readme, Skeleton::README_START, Skeleton::README_END), $readmeAfter);

        $attributesAfter = (string) file_get_contents($root . '/.gitattributes');
        self::assertStringNotContainsString('skeleton:', $attributesAfter);
        self::assertSame(
            Skeleton::withoutMarkedBlock($attributes, Skeleton::ATTRIBUTES_START, Skeleton::ATTRIBUTES_END),
            $attributesAfter
        );

        self::assertIsObject($composer);
        self::assertIsObject($composer->scripts ?? null);
        unset($composer->scripts->{'post-create-project-cmd'});
        self::assertSame(
            json_encode($composer, self::JSON_FLAGS) . "\n",
            (string) file_get_contents($root . '/composer.json')
        );

        $after = Skeleton::snapshot($root);
        foreach ($before as $path => $hash) {
            if (Skeleton::isSkeletonOnly($path) || in_array($path, ['README.md', '.gitattributes', 'composer.json'], true)) {
                continue;
            }
            self::assertSame($hash, $after[$path] ?? null, "arquivo da entrega alterado ou removido: {$path}");
        }
        foreach (array_keys($after) as $path) {
            self::assertArrayHasKey($path, $before, "arquivo novo criado pelo script: {$path}");
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $result['stdout']))));
        self::assertCount(9, $lines, "uma linha por item removido ou alterado:\n" . $result['stdout']);

        self::assertSame($decoyBefore, Skeleton::snapshot($decoy), 'o script não pode mexer no diretório atual');
    }

    public function testRunningACopyAgainOnTheCleanProjectChangesNothing(): void
    {
        $root = $this->tempDir('repo');
        Skeleton::copyRepository($root);
        $script = (string) file_get_contents($root . '/' . Skeleton::SCRIPT);
        self::assertSame(0, $this->runScript($root)['code']);
        $clean = Skeleton::snapshot($root);

        file_put_contents($root . '/' . Skeleton::SCRIPT, $script);
        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame($clean, Skeleton::snapshot($root));
    }

    // ------------------------------------------------------------------ casos de borda em projeto mínimo

    public function testAlreadyMissingPathsAreIgnored(): void
    {
        $root = $this->fixture(self::defaultFiles() + [
            'docs/manutencao-do-skeleton.md' => "# Manutenção\n",
            'tests-skeleton/ExampleTest.php' => "<?php\n",
            'tests-skeleton/Support/Helper.php' => "<?php\n",
        ]);

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        foreach (Skeleton::SKELETON_ONLY as $path) {
            self::assertFileDoesNotExist($root . '/' . $path);
        }
        self::assertFileExists($root . '/tools/lint.php');
    }

    public function testRemovesMarkedBlocksAsDocumented(): void
    {
        $root = $this->fixture(self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame(
            "## Documentação\n\n- [Guia de uso](docs/USO.md)\n\n",
            (string) file_get_contents($root . '/README.md')
        );
        self::assertSame("* text=auto eol=lf\n\n", (string) file_get_contents($root . '/.gitattributes'));
    }

    public function testPreservesCrlfAndAcceptsMarkersWithSurroundingSpaces(): void
    {
        $readme = "# Projeto\r\n\r\n   " . Skeleton::README_START . "  \r\nsó do skeleton\r\n\t"
            . Skeleton::README_END . " \r\nfim\r\n";
        $root = $this->fixture(['README.md' => $readme] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame("# Projeto\r\n\r\nfim\r\n", (string) file_get_contents($root . '/README.md'));
    }

    /** @return iterable<string, array{string}> */
    public static function brokenReadmeMarkers(): iterable
    {
        $start = Skeleton::README_START;
        $end = Skeleton::README_END;
        yield 'só inicio' => ["# Projeto\n{$start}\nlinha\n"];
        yield 'só fim' => ["# Projeto\nlinha\n{$end}\n"];
        yield 'fim antes de inicio' => ["# Projeto\n{$end}\nlinha\n{$start}\n"];
        yield 'mais de um par' => ["# Projeto\n{$start}\na\n{$end}\n{$start}\nb\n{$end}\n"];
    }

    #[DataProvider('brokenReadmeMarkers')]
    public function testBrokenReadmeMarkersKeepTheFileWarnAndContinue(string $readme): void
    {
        $root = $this->fixture(['README.md' => $readme] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame($readme, (string) file_get_contents($root . '/README.md'));
        self::assertStringContainsString('README.md', $result['stderr']);
        // o script continua: os passos seguintes rodam
        self::assertSame("* text=auto eol=lf\n\n", (string) file_get_contents($root . '/.gitattributes'));
        self::assertStringNotContainsString('post-create-project-cmd', (string) file_get_contents($root . '/composer.json'));
        self::assertFileDoesNotExist($root . '/' . Skeleton::SCRIPT);
    }

    public function testBrokenGitattributesMarkersKeepTheFileAndWarn(): void
    {
        $attributes = "* text=auto eol=lf\n" . Skeleton::ATTRIBUTES_START . "\n/tests-skeleton export-ignore\n";
        $root = $this->fixture(['.gitattributes' => $attributes] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame($attributes, (string) file_get_contents($root . '/.gitattributes'));
        self::assertStringContainsString('.gitattributes', $result['stderr']);
    }

    public function testFilesWithoutMarkersOrMissingStayUntouchedSilently(): void
    {
        $readme = "# Projeto sem marcadores\r\n\r\ntexto\n";
        $files = self::defaultFiles();
        unset($files['.gitattributes']);
        $root = $this->fixture(['README.md' => $readme] + $files);

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame($readme, (string) file_get_contents($root . '/README.md'));
        self::assertFileDoesNotExist($root . '/.gitattributes');
        self::assertStringNotContainsString('README.md', $result['stderr']);
        self::assertStringNotContainsString('.gitattributes', $result['stderr']);
    }

    public function testComposerJsonKeepsKeyOrderAndFormat(): void
    {
        $original = <<<'JSON'
            {
                "name": "acme/api",
                "description": "Projeto com acentuação e barra/normal",
                "require": {"php": ">=8.3"},
                "scripts": {
                    "lint": "@php tools/lint.php",
                    "post-create-project-cmd": ["@php tools/skeleton-post-create.php"],
                    "test": "@php vendor/bin/phpunit -c phpunit.xml"
                },
                "config": {"allow-plugins": {}, "sort-packages": true},
                "minimum-stability": "stable"
            }
            JSON;
        $root = $this->fixture(['composer.json' => $original] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        $expected = json_decode($original, false, flags: JSON_THROW_ON_ERROR);
        unset($expected->scripts->{'post-create-project-cmd'});
        $actual = (string) file_get_contents($root . '/composer.json');
        self::assertSame(json_encode($expected, self::JSON_FLAGS) . "\n", $actual);
        self::assertStringContainsString('acentuação', $actual);
        self::assertStringContainsString('barra/normal', $actual);
        self::assertStringNotContainsString("\n\t", $actual);
    }

    public function testEmptyScriptsKeyIsRemoved(): void
    {
        $original = json_encode([
            'name' => 'acme/api',
            'scripts' => ['post-create-project-cmd' => ['@php tools/skeleton-post-create.php']],
            'license' => 'MIT',
        ], self::JSON_FLAGS) . "\n";
        $root = $this->fixture(['composer.json' => $original] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame(
            json_encode(['name' => 'acme/api', 'license' => 'MIT'], self::JSON_FLAGS) . "\n",
            (string) file_get_contents($root . '/composer.json')
        );
    }

    public function testInvalidComposerJsonFailsWithoutChangingIt(): void
    {
        $broken = "{\n    \"name\": \"acme/api\",\n";
        $root = $this->fixture(['composer.json' => $broken] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(1, $result['code']);
        self::assertNotSame('', trim($result['stderr']));
        self::assertSame($broken, (string) file_get_contents($root . '/composer.json'));
    }

    public function testMissingComposerJsonFails(): void
    {
        $files = self::defaultFiles();
        unset($files['composer.json']);
        $root = $this->fixture($files);

        $result = $this->runScript($root);

        self::assertSame(1, $result['code']);
        self::assertNotSame('', trim($result['stderr']));
        self::assertFileDoesNotExist($root . '/composer.json');
    }

    public function testRemovesItselfAndTheToolsFolderWhenEmpty(): void
    {
        $files = self::defaultFiles();
        unset($files['tools/lint.php']);
        $root = $this->fixture($files);

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertFileDoesNotExist($root . '/' . Skeleton::SCRIPT);
        self::assertDirectoryDoesNotExist($root . '/tools');
    }

    public function testKeepsTheToolsFolderWhenItHasOtherFiles(): void
    {
        $root = $this->fixture(self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertFileDoesNotExist($root . '/' . Skeleton::SCRIPT);
        self::assertFileExists($root . '/tools/lint.php');
    }
}
