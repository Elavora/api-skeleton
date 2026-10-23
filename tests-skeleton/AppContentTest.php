<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SkeletonTests\Support\Skeleton;

/** docs/manutencao-do-skeleton.md: "`app/` e `tests/`". */
final class AppContentTest extends TestCase
{
    private const APP_FILES = [
        'app/Attributes/.gitkeep',
        'app/Contracts/.gitkeep',
        'app/DataTypes/.gitkeep',
        'app/Enums/.gitkeep',
        'app/Http/Controller/HealthController.php',
        'app/Http/HttpRoutes.php',
        'app/Integrations/.gitkeep',
        'app/Repositories/.gitkeep',
        'app/Services/.gitkeep',
        'app/Support/.gitkeep',
        'app/Worker/tasks.php',
    ];

    public function testAppContainsExactlyTheDocumentedFiles(): void
    {
        $root = Skeleton::path('app');
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = 'app/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
        sort($files);

        self::assertSame(self::APP_FILES, $files);
    }

    public function testGitkeepFilesAreEmpty(): void
    {
        foreach (self::APP_FILES as $file) {
            if (str_ends_with($file, '.gitkeep')) {
                self::assertSame('', Skeleton::read($file), $file);
            }
        }
    }

    public function testHttpRoutesKeepsTheHealthAlias(): void
    {
        $routes = Skeleton::read('app/Http/HttpRoutes.php');

        self::assertStringContainsString('/health', $routes);
        self::assertStringContainsString('HealthController', $routes);
    }

    public function testTasksFileIsExactlyTheEmptyRegistry(): void
    {
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use Elavora\Api\Extension\QueueWorker\TaskRegistry;

            // Registre aqui as tarefas da fila: o nome gravado na fila e o handler que a executa.
            // Prefira nomes pesquisaveis e estaveis, como documents.process ou email.send.
            return new TaskRegistry();

            PHP;

        self::assertSame($expected, str_replace("\r\n", "\n", Skeleton::read('app/Worker/tasks.php')));
    }

    /** @return iterable<string, array{string}> */
    public static function removedFiles(): iterable
    {
        foreach ([
            'app/Attributes/Permission.php',
            'app/DataTypes/ProjectCode.php',
            'app/Worker/ExampleTaskHandler.php',
            'tests/GuideExamplesTest.php',
            'tests/ResponseableDataTypeTest.php',
        ] as $file) {
            yield $file => [$file];
        }
    }

    #[DataProvider('removedFiles')]
    public function testExampleFileIsRemoved(string $file): void
    {
        self::assertFileDoesNotExist(Skeleton::path($file));
    }

    public function testHealthTestsStay(): void
    {
        self::assertFileExists(Skeleton::path('tests/HealthEndpointTest.php'));
        self::assertFileExists(Skeleton::path('tests/ConventionRouteTest.php'));
        self::assertFileExists(Skeleton::path('worker.php'));
    }

    /** @return iterable<string, array{string}> */
    public static function exampleNames(): iterable
    {
        foreach (['Permission.php', 'ProjectCode', 'ExampleTaskHandler', 'example.ping', 'Exemplos didaticos'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('exampleNames')]
    public function testNoDeliveredFileCitesTheExamples(string $name): void
    {
        foreach (Skeleton::deliveredTextFiles() as $file) {
            self::assertStringNotContainsString($name, Skeleton::read($file), $file);
        }
    }

    public function testReadmeDescribesWhatComesInApp(): void
    {
        self::assertMatchesRegularExpression('~^### O que já vem em `app/`\s*$~m', Skeleton::read('README.md'));
    }
}
