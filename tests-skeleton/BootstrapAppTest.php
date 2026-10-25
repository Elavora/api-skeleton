<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * O `core/bootstrap/app.php` entregue deixa o framework carregar as extensões selecionadas no `.env` e tem a lista
 * de extensões do projeto no próprio argumento `extensions:`.
 *
 * A regra do `loadEnvironmentExtensions` é de conteúdo: o arquivo não cita o argumento em nenhum lugar, nem em
 * comentário, nem com `true`. Assim vale o padrão do framework, sem depender das mensagens de erro dele.
 *
 * O conteúdo exato do `app.php` é lido do bloco de código da própria doc, em tempo de teste, e não copiado aqui.
 *
 * @see docs/manutencao-do-skeleton.md#corebootstrapappphp
 * @see docs/manutencao-do-skeleton.md#lista-de-extensões-do-projeto-no-appphp
 * @see docs/USO.md#extensões-do-projeto
 */
final class BootstrapAppTest extends TestCase
{
    private const APP_FILE = 'core/bootstrap/app.php';

    private const ARGUMENT = 'loadEnvironmentExtensions';

    private const DOC_FILE = 'docs/manutencao-do-skeleton.md';

    /** Título da seção da doc que traz o conteúdo exato do `app.php`. */
    private const DOC_SECTION = '#### Lista de extensões do projeto no `app.php`';

    /** Frase da doc que antecede o bloco de código com o conteúdo exato. */
    private const DOC_LEAD = 'O `core/bootstrap/app.php` fica exatamente assim:';

    private const EXTENSIONS_FILE = 'core/config/extensions.php';

    private const CONFIG_DIR = 'core/config';

    /** Texto que nenhum arquivo entregue contém. */
    private const EXTENSIONS_REFERENCE = 'config/extensions.php';

    /** A lista vazia escrita direto no argumento, como no bloco da doc. */
    private const INLINE_LIST = '    extensions: [],';

    /**
     * O `app.php` versionado não contém `loadEnvironmentExtensions`. Falha com
     * `loadEnvironmentExtensions: false`, com `loadEnvironmentExtensions: true` e com o nome só num comentário.
     *
     * @see docs/manutencao-do-skeleton.md#corebootstrapappphp
     */
    public function testAppFileDoesNotCiteLoadEnvironmentExtensions(): void
    {
        self::assertStringNotContainsString(self::ARGUMENT, Skeleton::read(self::APP_FILE), self::APP_FILE);
    }

    // ------------------------------------------------------------------ conteúdo exato do app.php

    /**
     * O bloco lido da doc é o `app.php` descrito: começa por `<?php`, tem a lista vazia no argumento `extensions:`,
     * não cita `loadEnvironmentExtensions` e termina com uma única quebra de linha. Garante que a extração achou o
     * bloco certo.
     *
     * @see docs/manutencao-do-skeleton.md#lista-de-extensões-do-projeto-no-appphp
     */
    public function testDocumentedAppFileIsExtractedFromTheDoc(): void
    {
        $expected = self::documentedAppFile();

        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n", $expected);
        self::assertStringContainsString("\n" . self::INLINE_LIST . "\n", $expected);
        self::assertStringContainsString('return ApplicationBootstrap::create(', $expected);
        self::assertStringNotContainsString(self::ARGUMENT, $expected);
        self::assertStringNotContainsString(self::EXTENSIONS_REFERENCE, $expected);
        self::assertStringNotContainsString('$extensions', $expected);
        self::assertStringEndsWith(");\n", $expected);
        self::assertStringNotContainsString("\r", $expected);
    }

    /**
     * O `core/bootstrap/app.php` é o conteúdo da doc, byte a byte: fim de linha LF e uma quebra de linha no fim.
     *
     * @see docs/manutencao-do-skeleton.md#lista-de-extensões-do-projeto-no-appphp
     * @see docs/USO.md#extensões-do-projeto
     */
    public function testAppFileIsExactlyTheDocumentedContent(): void
    {
        self::assertSame(self::documentedAppFile(), Skeleton::read(self::APP_FILE), self::APP_FILE);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidAppFiles(): iterable
    {
        yield 'app.php antigo, com require do config/extensions.php' => [self::mutate(
            self::INLINE_LIST,
            "    extensions: require dirname(__DIR__) . '/config/extensions.php',",
        )];
        yield 'lista numa variável $extensions antes do return' => [self::mutate(
            "\nreturn ApplicationBootstrap::create(\n",
            "\n\$extensions = [];\n\nreturn ApplicationBootstrap::create(\n",
            self::mutate(self::INLINE_LIST, '    extensions: $extensions,'),
        )];
        yield 'com loadEnvironmentExtensions: false' => [self::mutate(
            "    configure: HttpRoutes::register(...),\n",
            "    configure: HttpRoutes::register(...),\n    loadEnvironmentExtensions: false,\n",
        )];
        yield 'fim de linha CRLF' => [str_replace("\n", "\r\n", self::documentedAppFile())];
        yield 'sem quebra de linha no fim' => [rtrim(self::documentedAppFile(), "\n")];
        yield 'duas quebras de linha no fim' => [self::documentedAppFile() . "\n"];
    }

    /**
     * Os casos que a doc diz que falham (o `app.php` antigo e a lista numa variável) e as bordas do "byte a byte".
     *
     * @param string $content conteúdo de `app.php` diferente do documentado
     *
     * @see docs/manutencao-do-skeleton.md#lista-de-extensões-do-projeto-no-appphp
     */
    #[DataProvider('invalidAppFiles')]
    public function testOtherAppFileContentsAreRejected(string $content): void
    {
        self::assertNotSame(self::documentedAppFile(), $content);
    }

    // ------------------------------------------------------------------ core/config/ não existe

    /**
     * O arquivo `core/config/extensions.php` não existe, nem a pasta `core/config/`.
     *
     * @see docs/manutencao-do-skeleton.md#lista-de-extensões-do-projeto-no-appphp
     */
    public function testExtensionsConfigFileAndFolderDoNotExist(): void
    {
        self::assertFileDoesNotExist(Skeleton::path(self::EXTENSIONS_FILE));
        self::assertDirectoryDoesNotExist(Skeleton::path(self::CONFIG_DIR));
    }

    /**
     * A pasta `core/` continua com o mesmo nome e fica só com `bootstrap/app.php` e `compose/`.
     *
     * @see docs/manutencao-do-skeleton.md#lista-de-extensões-do-projeto-no-appphp
     */
    public function testCoreHasOnlyBootstrapAppAndCompose(): void
    {
        self::assertSame(['bootstrap', 'compose'], self::entries('core'));
        self::assertSame(['app.php'], self::entries('core/bootstrap'));
        self::assertDirectoryExists(Skeleton::path('core/compose'));
    }

    // ------------------------------------------------------------------ nenhuma citação a config/extensions.php

    /**
     * Nenhum arquivo entregue contém `config/extensions.php`. A doc de manutenção e `tests-skeleton/` ficam de fora,
     * porque não são entregues.
     *
     * @see docs/manutencao-do-skeleton.md#lista-de-extensões-do-projeto-no-appphp
     */
    public function testNoDeliveredFileCitesTheExtensionsConfigFile(): void
    {
        $files = Skeleton::deliveredTextFiles();
        self::assertContains('README.md', $files);
        self::assertNotContains(self::DOC_FILE, $files);
        self::assertSame([], array_values(array_filter(
            $files,
            static fn(string $file): bool => str_starts_with($file, 'tests-skeleton/'),
        )));

        $citing = array_values(array_filter(
            $files,
            static fn(string $file): bool => self::citesExtensionsFile(Skeleton::read($file)),
        ));

        self::assertSame([], $citing, 'arquivos entregues que citam ' . self::EXTENSIONS_REFERENCE);
    }

    /**
     * Passa: a árvore de `core/` do README sem a linha `config/extensions.php`. Falha: a linha do README antigo.
     *
     * @see docs/manutencao-do-skeleton.md#lista-de-extensões-do-projeto-no-appphp
     */
    public function testReadmeTreeLineOfTheOldFileIsRecognized(): void
    {
        $old = "core/\n|-- bootstrap/app.php\n`-- config/extensions.php # Registra extensões próprias e overrides do projeto.\n";
        $new = "core/\n`-- bootstrap/app.php\n";

        self::assertTrue(self::citesExtensionsFile($old));
        self::assertFalse(self::citesExtensionsFile($new));
    }

    // ------------------------------------------------------------------ apoio

    private static function citesExtensionsFile(string $content): bool
    {
        return str_contains($content, self::EXTENSIONS_REFERENCE);
    }

    /**
     * Conteúdo do bloco `php` que segue a frase "fica exatamente assim" na seção da lista de extensões, com LF e
     * uma quebra de linha no fim.
     */
    private static function documentedAppFile(): string
    {
        $doc = str_replace("\r\n", "\n", Skeleton::read(self::DOC_FILE));

        $start = strpos($doc, "\n" . self::DOC_SECTION . "\n");
        self::assertIsInt($start, 'seção da lista de extensões ausente na doc');
        $section = substr($doc, $start + 1);
        if (preg_match('~\n#{2,4} ~', $section, $next, PREG_OFFSET_CAPTURE) === 1) {
            $section = substr($section, 0, (int) $next[0][1]);
        }

        $lead = strpos($section, self::DOC_LEAD);
        self::assertIsInt($lead, 'frase "fica exatamente assim" ausente na seção');
        $open = strpos($section, "\n```php\n", $lead);
        self::assertIsInt($open, 'bloco php ausente depois da frase');
        $body = $open + strlen("\n```php\n");
        $close = strpos($section, "\n```\n", $body);
        self::assertIsInt($close, 'bloco php sem fechamento');

        return substr($section, $body, $close - $body) . "\n";
    }

    private static function mutate(string $search, string $replace, ?string $base = null): string
    {
        $base ??= self::documentedAppFile();
        if (substr_count($base, $search) !== 1) {
            throw new \LogicException("Trecho deveria aparecer uma vez no app.php da doc: {$search}");
        }

        return str_replace($search, $replace, $base);
    }

    /** @return list<string> nomes dentro da pasta, em ordem, sem `.` e `..` */
    private static function entries(string $relative): array
    {
        $path = Skeleton::path($relative);
        self::assertDirectoryExists($path);
        $entries = array_values(array_diff(scandir($path) ?: [], ['.', '..']));
        sort($entries);

        return $entries;
    }
}
