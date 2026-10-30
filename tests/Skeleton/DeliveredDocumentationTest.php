<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * Documentação entregue: `README.md` (fora do bloco do skeleton), `docs/USO.md` e `docs/ambiente-e-ci.md`.
 *
 * @see docs/manutencao-do-skeleton.md#documentação-entregue
 */
final class DeliveredDocumentationTest extends TestCase
{
    /** Seções `##` do README, fora do bloco do skeleton, nesta ordem. */
    private const README_SECTIONS = ['Requisitos', 'Criação', 'Estrutura', 'Documentação'];

    /** Links do `docs/USO.md` para a doc do framework. */
    private const USO_LINKS = [
        'https://github.com/Elavora/api-framework/blob/main/docs/BOOTSTRAP.md',
        'https://github.com/Elavora/api-framework/blob/main/docs/ROTEAMENTO.md',
        'https://github.com/Elavora/api-framework/blob/main/src/Bootstrap/EnvironmentExtensions.php',
    ];

    private const README_LINK = 'https://github.com/Elavora/api-framework#documentação';

    /** Textos que não aparecem em nenhuma linha de título `##`, `###` ou `####`. */
    private const FORBIDDEN_TITLES = [
        'O que fica no projeto e como atualizar',
        'Versão do PHP',
        'Versões dos serviços',
        'Atualização do bootstrap',
    ];

    private const FORBIDDEN_TEXT = 'nenhuma extensão tem versão que aceite';

    /** @return iterable<string, array{string}> */
    public static function deliveredDocs(): iterable
    {
        foreach (['README.md', 'docs/USO.md', 'docs/ambiente-e-ci.md'] as $file) {
            yield $file => [$file];
        }
    }

    private static function delivered(string $file): string
    {
        $content = Skeleton::read($file);

        return $file === 'README.md'
            ? Skeleton::withoutMarkedBlock($content, Skeleton::README_START, Skeleton::README_END)
            : $content;
    }

    /**
     * Títulos `##` do texto, fora dos blocos de código cercados.
     *
     * @return list<string>
     */
    private static function sections(string $markdown): array
    {
        $sections = [];
        $inCode = false;
        foreach (preg_split('~\R~', $markdown) ?: [] as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
                $inCode = !$inCode;
                continue;
            }
            if (!$inCode && preg_match('~^## (.+?)\s*$~', $line, $match) === 1) {
                $sections[] = $match[1];
            }
        }

        return $sections;
    }

    /**
     * Linhas de título (`##`, `###` ou `####`) que têm um dos textos proibidos.
     *
     * @return list<string>
     */
    private static function forbiddenTitles(string $markdown): array
    {
        $found = [];
        foreach (preg_split('~\R~', $markdown) ?: [] as $line) {
            if (preg_match('~^#{2,4}\s~', $line) !== 1) {
                continue;
            }
            foreach (self::FORBIDDEN_TITLES as $text) {
                if (str_contains($line, $text)) {
                    $found[] = $line;
                }
            }
        }

        return $found;
    }

    // ------------------------------------------------------------------ seções do README

    /**
     * Fora do bloco do skeleton, o README tem exatamente as quatro seções `##`, nesta ordem.
     *
     * @see docs/manutencao-do-skeleton.md#seções-do-readmemd
     */
    public function testReadmeHasExactlyTheDocumentedSections(): void
    {
        self::assertSame(self::README_SECTIONS, self::sections(self::delivered('README.md')));
    }

    /**
     * O bloco do skeleton vem depois das quatro seções e tem `## Só no repositório do skeleton`.
     *
     * @see docs/manutencao-do-skeleton.md#seções-do-readmemd
     */
    public function testSkeletonBlockComesAfterTheSectionsWithItsOwnSection(): void
    {
        $readme = Skeleton::read('README.md');
        $start = strpos($readme, Skeleton::README_START);
        $end = strpos($readme, Skeleton::README_END);
        self::assertIsInt($start);
        self::assertIsInt($end);

        self::assertSame(['Só no repositório do skeleton'], self::sections(substr($readme, $start, $end - $start)));
        self::assertSame([], self::sections(substr($readme, $end)), 'nenhuma seção depois do bloco');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidReadmes(): iterable
    {
        $valid = "## Requisitos\n\n## Criação\n\n## Estrutura\n\n### O que já vem em `app/`\n\n## Documentação\n";

        foreach (['Extensões opcionais', 'Qualidade e build', 'Atualização do bootstrap', 'Ambiente de desenvolvimento e CI'] as $extra) {
            yield "com ## {$extra}" => [$valid . "\n## {$extra}\n"];
        }
        yield 'em outra ordem' => ["## Criação\n\n## Requisitos\n\n## Estrutura\n\n## Documentação\n"];
        yield 'faltando Estrutura' => ["## Requisitos\n\n## Criação\n\n## Documentação\n"];
    }

    /**
     * Os casos que a doc diz que falham: seção a mais, outra ordem ou seção faltando.
     *
     * @param string $readme README sem o bloco do skeleton
     *
     * @see docs/manutencao-do-skeleton.md#seções-do-readmemd
     */
    #[DataProvider('invalidReadmes')]
    public function testInvalidReadmeSectionsAreRejected(string $readme): void
    {
        self::assertNotSame(self::README_SECTIONS, self::sections($readme));
    }

    /**
     * Um `##` dentro de bloco de código não é seção do README.
     *
     * @see docs/manutencao-do-skeleton.md#seções-do-readmemd
     */
    public function testSectionsIgnoreHeadingsInsideCodeBlocks(): void
    {
        self::assertSame(['Requisitos'], self::sections("## Requisitos\n\n```markdown\n## Documentação\n```\n"));
    }

    // ------------------------------------------------------------------ links para a doc do framework

    /**
     * O `docs/USO.md` tem os três links para a `main` do framework.
     *
     * @see docs/manutencao-do-skeleton.md#links-para-a-doc-do-framework
     */
    public function testUsoLinksToTheFrameworkDocumentation(): void
    {
        $uso = Skeleton::read('docs/USO.md');

        foreach (self::USO_LINKS as $link) {
            self::assertStringContainsString('(' . $link . ')', $uso);
        }
    }

    /**
     * O README, fora do bloco do skeleton, tem o link para a documentação do framework.
     *
     * @see docs/manutencao-do-skeleton.md#links-para-a-doc-do-framework
     */
    public function testReadmeLinksToTheFrameworkDocumentation(): void
    {
        self::assertStringContainsString('(' . self::README_LINK . ')', self::delivered('README.md'));
    }

    // ------------------------------------------------------------------ o que a doc entregue não diz

    /**
     * Nenhuma linha de título `##`, `###` ou `####` tem um dos textos proibidos.
     *
     * @param string $file arquivo da documentação entregue
     *
     * @see docs/manutencao-do-skeleton.md#o-que-a-doc-entregue-não-diz
     */
    #[DataProvider('deliveredDocs')]
    public function testDeliveredDocumentationHasNoUpdateGuideTitles(string $file): void
    {
        self::assertSame([], self::forbiddenTitles(self::delivered($file)));
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenTitleLines(): iterable
    {
        foreach (self::FORBIDDEN_TITLES as $index => $text) {
            $level = str_repeat('#', 2 + $index % 3);
            yield "{$level} {$text}" => ["{$level} {$text}"];
        }
    }

    /**
     * Cada texto proibido é reconhecido num título de nível 2, 3 ou 4.
     *
     * @param string $line linha de título com um texto proibido
     *
     * @see docs/manutencao-do-skeleton.md#o-que-a-doc-entregue-não-diz
     */
    #[DataProvider('forbiddenTitleLines')]
    public function testForbiddenTitlesAreRecognized(string $line): void
    {
        self::assertSame([$line], self::forbiddenTitles("Texto.\n\n{$line}\n"));
    }

    /**
     * O texto proibido só conta em linha de título: no corpo do texto ele não reprova.
     *
     * @see docs/manutencao-do-skeleton.md#o-que-a-doc-entregue-não-diz
     */
    public function testForbiddenTextOutsideTitlesIsAllowed(): void
    {
        self::assertSame([], self::forbiddenTitles("A Versão do PHP é decisão do projeto.\n"));
    }

    /**
     * Nenhum dos três cita o aviso com data sobre as extensões e o framework 2.
     *
     * @param string $file arquivo da documentação entregue
     *
     * @see docs/manutencao-do-skeleton.md#o-que-a-doc-entregue-não-diz
     */
    #[DataProvider('deliveredDocs')]
    public function testDeliveredDocumentationHasNoDatedExtensionWarning(string $file): void
    {
        self::assertStringNotContainsString(self::FORBIDDEN_TEXT, self::delivered($file));
    }
}
