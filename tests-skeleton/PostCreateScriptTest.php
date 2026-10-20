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

    /** content-hash pelo algoritmo da doc (Locker::getContentHash do Composer 2). */
    private static function contentHash(string $composerJson): string
    {
        $composer = json_decode($composerJson, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $keys = ['name', 'version', 'require', 'require-dev', 'conflict', 'replace', 'provide',
            'minimum-stability', 'prefer-stable', 'repositories', 'extra'];
        $data = array_intersect_key($composer, array_flip($keys));
        if (isset($composer['config']['platform'])) {
            $data['config'] = ['platform' => $composer['config']['platform']];
        }
        ksort($data);

        return md5((string) json_encode($data));
    }

    private static function lockHash(string $lock): string
    {
        $data = json_decode($lock, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsString($data['content-hash'] ?? null);

        return $data['content-hash'];
    }

    private static function replaceHash(string $lock, string $old, string $new): string
    {
        $count = 0;
        $result = preg_replace(
            '~("content-hash"\s*:\s*")' . preg_quote($old, '~') . '"~',
            '${1}' . $new . '"',
            $lock,
            1,
            $count
        );
        self::assertSame(1, $count, 'content-hash não encontrado no lock');

        return (string) $result;
    }

    /** Lock mínimo, com formatação própria (tab, CRLF) para conferir que só o hash muda. */
    private static function lockWithHash(string $hash): string
    {
        return "{\r\n\t\"_readme\": [\"não edite\"],\r\n\t\"content-hash\": \"{$hash}\",\r\n"
            . "\t\"packages\": [],\r\n\t\"packages-dev\": []\r\n}\r\n";
    }

    /** @return list<string> */
    private static function outputLines(string $stdout): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $stdout)), static fn (string $l): bool => $l !== ''));
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
        self::assertFileExists($root . '/composer.lock');
        $lock = (string) file_get_contents($root . '/composer.lock');

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
        self::assertDirectoryDoesNotExist($root . '/tools', 'sem o lint.php, tools/ fica vazia e sai');
        self::assertSame(['quality.yml'], array_map('basename', glob($root . '/.github/workflows/*') ?: []));

        $readmeAfter = (string) file_get_contents($root . '/README.md');
        self::assertStringNotContainsString('skeleton:inicio', $readmeAfter);
        self::assertStringNotContainsString('skeleton:fim', $readmeAfter);
        self::assertSame(Skeleton::withoutMarkedBlock($readme, Skeleton::README_START, Skeleton::README_END), $readmeAfter);

        $attributesAfter = (string) file_get_contents($root . '/.gitattributes');
        self::assertStringNotContainsString('skeleton:', $attributesAfter);
        self::assertSame(
            Skeleton::withoutMarkedBlock($attributes, Skeleton::ATTRIBUTES_START, Skeleton::ATTRIBUTES_END),
            $attributesAfter,
        );

        self::assertIsObject($composer);
        self::assertIsObject($composer->scripts ?? null);
        self::assertTrue(isset($composer->name, $composer->description), 'o skeleton tem name e description');
        unset($composer->name, $composer->description, $composer->scripts->{'post-create-project-cmd'});
        $composerAfter = (string) file_get_contents($root . '/composer.json');
        self::assertSame(json_encode($composer, self::JSON_FLAGS) . "\n", $composerAfter);

        // composer.lock: só o valor do content-hash muda.
        $lockAfter = (string) file_get_contents($root . '/composer.lock');
        $oldHash = self::lockHash($lock);
        $newHash = self::contentHash($composerAfter);
        self::assertNotSame($oldHash, $newHash, 'sem o name, o content-hash muda');
        self::assertSame($newHash, self::lockHash($lockAfter));
        self::assertSame(self::replaceHash($lock, $oldHash, $newHash), $lockAfter);

        $validate = Skeleton::run(['composer', 'validate', '--strict', '--no-check-publish', '--no-interaction'], $root);
        self::assertSame(0, $validate['code'], "composer validate reprovou:\n" . $validate['stdout'] . $validate['stderr']);

        $after = Skeleton::snapshot($root);
        foreach ($before as $path => $hash) {
            if (Skeleton::isSkeletonOnly($path) || in_array($path, ['README.md', '.gitattributes', 'composer.json', 'composer.lock'], true)) {
                continue;
            }
            self::assertSame($hash, $after[$path] ?? null, "arquivo da entrega alterado ou removido: {$path}");
        }
        foreach (array_keys($after) as $path) {
            self::assertArrayHasKey($path, $before, "arquivo novo criado pelo script: {$path}");
        }

        // 6 caminhos removidos + README + .gitattributes + composer.json + composer.lock + o próprio script + tools/.
        $lines = self::outputLines($result['stdout']);
        self::assertCount(12, $lines, "uma linha por item removido ou alterado:\n" . $result['stdout']);
        self::assertSame(
            ['alterado: composer.json', 'alterado: composer.lock'],
            array_values(array_filter($lines, static fn (string $l): bool => str_starts_with($l, 'alterado: composer.'))),
        );
        self::assertContains('removido: tools', $lines);

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
            (string) file_get_contents($root . '/README.md'),
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
                "homepage": "https://exemplo.test/acentuação/barra/normal",
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
        self::assertStringNotContainsString('"name"', (string) file_get_contents($root . '/composer.json'));
        $expected = json_decode($original, false, flags: JSON_THROW_ON_ERROR);
        unset($expected->name, $expected->description, $expected->scripts->{'post-create-project-cmd'});
        $actual = (string) file_get_contents($root . '/composer.json');
        self::assertSame(json_encode($expected, self::JSON_FLAGS) . "\n", $actual);
        self::assertStringContainsString('acentuação', $actual);
        self::assertStringContainsString('https://exemplo.test/acentuação/barra/normal', $actual);
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
            json_encode(['license' => 'MIT'], self::JSON_FLAGS) . "\n",
            (string) file_get_contents($root . '/composer.json'),
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

    // ------------------------------------------------------------------ name, description e composer.lock

    /** @return iterable<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function partialSkeletonKeys(): iterable
    {
        $post = ['post-create-project-cmd' => ['@php tools/skeleton-post-create.php'], 'test' => 'phpunit'];

        yield 'sem name' => [
            ['description' => 'Skeleton', 'license' => 'MIT', 'scripts' => $post],
            ['license' => 'MIT', 'scripts' => ['test' => 'phpunit']],
        ];
        yield 'sem description' => [
            ['name' => 'acme/api', 'license' => 'MIT', 'scripts' => $post],
            ['license' => 'MIT', 'scripts' => ['test' => 'phpunit']],
        ];
        yield 'sem post-create-project-cmd' => [
            ['name' => 'acme/api', 'description' => 'Skeleton', 'license' => 'MIT', 'scripts' => ['test' => 'phpunit']],
            ['license' => 'MIT', 'scripts' => ['test' => 'phpunit']],
        ];
    }

    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $expected
     */
    #[DataProvider('partialSkeletonKeys')]
    public function testRemovesOnlyTheSkeletonKeysThatExist(array $original, array $expected): void
    {
        $root = $this->fixture(['composer.json' => json_encode($original, self::JSON_FLAGS) . "\n"] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame(json_encode($expected, self::JSON_FLAGS) . "\n", (string) file_get_contents($root . '/composer.json'));
        self::assertContains('alterado: composer.json', self::outputLines($result['stdout']));
    }

    public function testComposerJsonWithoutSkeletonKeysStaysByteForByte(): void
    {
        $composer = "{\n  \"license\": \"MIT\",\n  \"require\": {\"php\": \">=8.3\"}\n}";
        $lock = self::lockWithHash(str_repeat('0', 32));
        $root = $this->fixture(['composer.json' => $composer, 'composer.lock' => $lock] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame($composer, (string) file_get_contents($root . '/composer.json'));
        self::assertSame($lock, (string) file_get_contents($root . '/composer.lock'), 'composer.json igual: o lock não muda');
        self::assertStringNotContainsString('composer.json', $result['stdout']);
        self::assertStringNotContainsString('composer.lock', $result['stdout']);
    }

    public function testUpdatesOnlyTheContentHashOfTheLock(): void
    {
        $composer = json_encode([
            'name' => 'acme/api',
            'description' => 'Skeleton',
            'require' => ['php' => '>=8.3'],
            'config' => ['platform' => ['php' => '8.3.0'], 'sort-packages' => true],
            'scripts' => ['post-create-project-cmd' => ['@php tools/skeleton-post-create.php']],
        ], self::JSON_FLAGS) . "\n";
        $oldHash = self::contentHash($composer);
        $lock = self::lockWithHash($oldHash);
        $root = $this->fixture(['composer.json' => $composer, 'composer.lock' => $lock] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        $newHash = self::contentHash((string) file_get_contents($root . '/composer.json'));
        self::assertNotSame($oldHash, $newHash);
        self::assertSame(self::lockWithHash($newHash), (string) file_get_contents($root . '/composer.lock'));
        self::assertSame(
            ['alterado: composer.json', 'alterado: composer.lock'],
            array_values(array_filter(
                self::outputLines($result['stdout']),
                static fn (string $l): bool => str_starts_with($l, 'alterado: composer.')
            ))
        );
    }

    public function testLockStaysWhenTheHashDoesNotChange(): void
    {
        // description não entra no content-hash: o composer.json muda, o hash não.
        $composer = json_encode([
            'description' => 'Skeleton',
            'require' => ['php' => '>=8.3'],
            'scripts' => ['post-create-project-cmd' => ['@php tools/skeleton-post-create.php']],
        ], self::JSON_FLAGS) . "\n";
        $lock = self::lockWithHash(self::contentHash($composer));
        $root = $this->fixture(['composer.json' => $composer, 'composer.lock' => $lock] + self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame($lock, (string) file_get_contents($root . '/composer.lock'));
        self::assertContains('alterado: composer.json', self::outputLines($result['stdout']));
        self::assertStringNotContainsString('composer.lock', $result['stdout']);
    }

    public function testMissingLockIsIgnored(): void
    {
        $root = $this->fixture(self::defaultFiles());

        $result = $this->runScript($root);

        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertFileDoesNotExist($root . '/composer.lock');
        self::assertStringNotContainsString('composer.lock', $result['stdout']);
        self::assertStringNotContainsString('composer.lock', $result['stderr']);
    }

    /** @return iterable<string, array{string}> */
    public static function brokenLocks(): iterable
    {
        yield 'JSON inválido' => ["{\n    \"content-hash\": \"abc\",\n"];
        yield 'sem content-hash' => ["{\n    \"packages\": []\n}\n"];
        yield 'content-hash não é texto' => ["{\n    \"content-hash\": 123,\n    \"packages\": []\n}\n"];
    }

    #[DataProvider('brokenLocks')]
    public function testBrokenLockFailsBeforeChangingAnyFile(string $lock): void
    {
        $root = $this->fixture(['composer.lock' => $lock] + self::defaultFiles() + [
            'docs/manutencao-do-skeleton.md' => "# Manutenção\n",
        ]);
        $before = Skeleton::snapshot($root);

        $result = $this->runScript($root);

        self::assertSame(1, $result['code'], $result['stdout']);
        self::assertNotSame('', trim($result['stderr']));
        self::assertSame($before, Skeleton::snapshot($root), 'nenhum arquivo pode ser alterado');
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
