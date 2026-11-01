<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * docs/manutencao-do-skeleton.md: "`storage/`" e "Linhas do `.gitignore`" (regras do `storage/`).
 *
 * @see docs/manutencao-do-skeleton.md#storage
 */
final class StorageFolderTest extends TestCase
{
    private ?string $image = null;

    protected function tearDown(): void
    {
        if ($this->image !== null) {
            Skeleton::run(['docker', 'image', 'rm', '--force', $this->image]);
            $this->image = null;
        }
    }

    // ------------------------------------------------------------------ pasta entregue

    /** `storage/.gitkeep` existe e está vazio. */
    public function testStorageGitkeepExistsAndIsEmpty(): void
    {
        self::assertFileExists(Skeleton::path('storage/.gitkeep'));
        self::assertSame('', Skeleton::read('storage/.gitkeep'));
    }

    /** `storage/.gitkeep` é um dos arquivos entregues pelo `composer create-project`. */
    public function testStorageGitkeepIsDelivered(): void
    {
        self::assertContains('storage/.gitkeep', Skeleton::deliveredTextFiles());
    }

    // ------------------------------------------------------------------ .gitignore

    /** O `.gitignore` entregue tem as linhas exatas `/storage/*` e `!/storage/.gitkeep`. */
    public function testGitignoreHasTheStorageLines(): void
    {
        $lines = array_map('trim', preg_split('~\R~', Skeleton::read('.gitignore')) ?: []);

        self::assertContains('/storage/*', $lines);
        self::assertContains('!/storage/.gitkeep', $lines);
    }

    /** Com o `.gitignore` entregue: sqlite e log ignorados (0), `.gitkeep` não ignorado (1). */
    public function testDeliveredGitignoreIgnoresStorageContentAndKeepsGitkeep(): void
    {
        $codes = self::checkIgnoreCodes(Skeleton::read('.gitignore'));

        self::assertSame(0, $codes['storage/app.sqlite'], 'git check-ignore -q storage/app.sqlite');
        self::assertSame(0, $codes['storage/logs/app.log'], 'git check-ignore -q storage/logs/app.log');
        self::assertSame(1, $codes['storage/.gitkeep'], 'git check-ignore -q storage/.gitkeep');
    }

    /**
     * Exemplo da doc: `/storage/` ignora a pasta inteira e o `.gitkeep` some do Git (falha).
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function gitignoreStorageExamples(): iterable
    {
        yield 'passa: /storage/* com exceção do .gitkeep' => ["/storage/*\n!/storage/.gitkeep\n", true];
        yield 'falha: /storage/ ignora a pasta inteira' => ["/storage/\n", false];
    }

    #[DataProvider('gitignoreStorageExamples')]
    public function testCheckIgnoreFollowsTheDocExamplesForStorage(string $gitignore, bool $valid): void
    {
        $codes = self::checkIgnoreCodes($gitignore);

        $isValid = $codes['storage/app.sqlite'] === 0
            && $codes['storage/logs/app.log'] === 0
            && $codes['storage/.gitkeep'] === 1;

        self::assertSame($valid, $isValid);
    }

    // ------------------------------------------------------------------ .dockerignore

    /** O `.dockerignore` continua com a linha `storage`: dados locais não entram na imagem. */
    public function testDockerignoreKeepsTheStorageLine(): void
    {
        $lines = array_map('trim', preg_split('~\R~', Skeleton::read('.dockerignore')) ?: []);

        self::assertContains('storage', $lines);
    }

    // ------------------------------------------------------------------ Dockerfile (estático)

    /** O estágio final do Dockerfile tem o `mkdir`/`chmod` depois do `COPY . .` e antes do `EXPOSE 8080`. */
    public function testFinalStageCreatesStorageBetweenCopyAndExpose(): void
    {
        $lines = self::instructions(self::finalStage());

        $copy = array_search('COPY . .', $lines, true);
        $mkdir = array_search('RUN mkdir -p storage && chmod 775 storage', $lines, true);
        $expose = array_search('EXPOSE 8080', $lines, true);

        self::assertNotFalse($copy, 'COPY . . no estágio final');
        self::assertNotFalse($mkdir, 'RUN mkdir -p storage && chmod 775 storage no estágio final');
        self::assertNotFalse($expose, 'EXPOSE 8080 no estágio final');
        self::assertGreaterThan($copy, $mkdir, 'o mkdir vem depois do COPY . .');
        self::assertLessThan($expose, $mkdir, 'o mkdir vem antes do EXPOSE 8080');
    }

    /** Se o Dockerfile ganhar `USER`, a instrução precisa fazer `chown` (doc). */
    public function testFinalStageWithUserAlsoChownsStorage(): void
    {
        $stage = self::finalStage();

        if (preg_match('~^USER\s~m', $stage) === 1) {
            self::assertMatchesRegularExpression('~^RUN .*chown .*storage~m', $stage);
        } else {
            self::assertDoesNotMatchRegularExpression('~^USER\s~m', $stage);
        }
    }

    // ------------------------------------------------------------------ imagem construída

    /** Na imagem construída, a pasta existe e aceita escrita: `test -d`, `test -w` e `touch` terminam com 0. */
    #[Group('docker')]
    public function testBuiltImageHasWritableStorageFolder(): void
    {
        $probe = Skeleton::run(['sh', '-c', 'command -v docker']);
        if ($probe['code'] !== 0 || trim($probe['stdout']) === '') {
            self::markTestSkipped('comando docker não existe neste ambiente');
        }

        $this->image = 'elavora-skeleton-storage-test:' . bin2hex(random_bytes(6));

        $build = Skeleton::run(['docker', 'build', '--tag', $this->image, Skeleton::root()]);
        self::assertSame(0, $build['code'], "docker build falhou:\n" . $build['stderr'] . $build['stdout']);

        $run = Skeleton::run([
            'docker', 'run', '--rm', '--entrypoint', 'sh', $this->image, '-c',
            'test -d storage && test -w storage && touch storage/app.sqlite',
        ]);
        self::assertSame(0, $run['code'], 'storage ausente ou sem escrita na imagem: ' . $run['stderr']);
    }

    // ------------------------------------------------------------------ apoio

    /** Texto do último estágio (da última linha `FROM` até o fim). */
    private static function finalStage(): string
    {
        $dockerfile = str_replace("\r\n", "\n", Skeleton::read('Dockerfile'));
        $parts = preg_split('~^(?=FROM\s)~m', $dockerfile) ?: [];
        $last = end($parts);
        self::assertIsString($last);
        self::assertStringStartsWith('FROM ', $last);

        return $last;
    }

    /**
     * Instruções do estágio sem comentários e sem linhas vazias.
     *
     * @return list<string>
     */
    private static function instructions(string $stage): array
    {
        $lines = array_map('trim', explode("\n", $stage));

        return array_values(array_filter(
            $lines,
            static fn(string $l): bool => $l !== '' && !str_starts_with($l, '#'),
        ));
    }

    /**
     * Código de saída de `git check-ignore -q` para cada caminho de `storage/`, num repositório temporário que só
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

        $dir = Skeleton::tempDir('skeleton-storage-ignore');
        try {
            $init = Skeleton::run(['git', 'init', '--quiet', $dir]);
            self::assertSame(0, $init['code'], 'git init: ' . $init['stderr']);
            file_put_contents($dir . '/.gitignore', $gitignore);

            $codes = [];
            foreach (['storage/app.sqlite', 'storage/logs/app.log', 'storage/.gitkeep'] as $path) {
                $result = Skeleton::run(['git', 'check-ignore', '-q', $path], $dir);
                self::assertContains($result['code'], [0, 1], "git check-ignore {$path}: " . $result['stderr']);
                $codes[$path] = $result['code'];
            }

            return $codes;
        } finally {
            Skeleton::removeTree($dir);
        }
    }
}
