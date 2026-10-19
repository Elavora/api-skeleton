<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * Exemplos de "precisa passar" e "precisa falhar" de
 * docs/manutencao-do-skeleton.md, seção "Atualizações do Dependabot não quebram os testes".
 * Garante que as checagens dos outros testes conferem o formato, não o valor.
 */
final class ReferenceFormatTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function validCallerLines(): iterable
    {
        yield 'valor da adoção' => ['uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0'];
        yield 'outro SHA com # 1.4.0' => ['uses: Elavora/.github/.github/workflows/php-quality.yml@0123456789abcdef0123456789abcdef01234567 # 1.4.0'];
        yield 'indentado como no workflow' => ['    uses: Elavora/.github/.github/workflows/php-quality.yml@ffffffffffffffffffffffffffffffffffffffff # 10.20.30'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCallerLines(): iterable
    {
        yield 'tag em vez de SHA' => ['uses: Elavora/.github/.github/workflows/php-quality.yml@1.3.0'];
        yield 'sem o comentário de versão' => ['uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb606f84016dd031ad748d009d820fd5487'];
        yield 'SHA curto' => ['uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb'];
        yield 'branch main' => ['uses: Elavora/.github/.github/workflows/php-quality.yml@main'];
        yield 'SHA maiúsculo' => ['uses: Elavora/.github/.github/workflows/php-quality.yml@D96E9BB606F84016DD031AD748D009D820FD5487 # 1.3.0'];
        yield 'comentário sem espaço' => ['uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb606f84016dd031ad748d009d820fd5487 #1.3.0'];
        yield 'versão com v' => ['uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # v1.3.0'];
        yield 'workflow errado' => ['uses: Elavora/.github/.github/workflows/quality.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0'];
    }

    #[DataProvider('validCallerLines')]
    public function testCallerLineFormatAcceptsDependabotUpdates(string $line): void
    {
        self::assertTrue(Skeleton::isElavoraCallerLine($line, 'php-quality.yml'));
    }

    #[DataProvider('invalidCallerLines')]
    public function testCallerLineFormatRejectsInvalidReferences(string $line): void
    {
        self::assertFalse(Skeleton::isElavoraCallerLine($line, 'php-quality.yml'));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function actionRefs(): iterable
    {
        yield 'valor da adoção, # v7' => ['actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7', true];
        yield 'outro SHA, # v7.1.0' => ['actions/checkout@0123456789abcdef0123456789abcdef01234567 # v7.1.0', true];
        yield '# v7.0' => ['actions/checkout@0123456789abcdef0123456789abcdef01234567 # v7.0', true];
        yield 'tag' => ['actions/checkout@v7', false];
        yield 'sem comentário' => ['actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1', false];
        yield 'SHA curto' => ['actions/checkout@3d3c42e # v7', false];
        yield 'comentário sem v' => ['actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # 7', false];
    }

    #[DataProvider('actionRefs')]
    public function testThirdPartyActionFormat(string $ref, bool $valid): void
    {
        self::assertSame($valid, Skeleton::isPinnedActionRef($ref));
    }

    /** @return iterable<string, array{string, int, bool}> */
    public static function constraints(): iterable
    {
        yield 'framework ^2.0' => ['^2.0', 2, true];
        yield 'framework ^2.1 (Dependabot)' => ['^2.1', 2, true];
        yield 'framework ^2.0.3' => ['^2.0.3', 2, true];
        yield 'framework ^3.0 (outro major)' => ['^3.0', 2, false];
        yield 'framework dev-main' => ['dev-main as 1.0.x-dev', 2, false];
        yield 'framework ^1.0' => ['^1.0', 2, false];
        yield 'code-standards ^1.0' => ['^1.0', 1, true];
        yield 'code-standards ^1.2' => ['^1.2', 1, true];
        yield 'code-standards ^2.0' => ['^2.0', 1, false];
    }

    #[DataProvider('constraints')]
    public function testComposerConstraintChecksOnlyTheMajor(string $constraint, int $major, bool $valid): void
    {
        self::assertSame($valid, Skeleton::hasMajor($constraint, $major));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function images(): iterable
    {
        yield 'tag que acompanha as estáveis' => ['ghcr.io/elavora/php-dev:8.3-latest', true];
        yield 'nome local' => ['elavora/php-dev:8.3', false];
        yield 'tag sem -latest' => ['ghcr.io/elavora/php-dev:8.3', false];
        yield ':latest sem versão do PHP' => ['ghcr.io/elavora/php-dev:latest', false];
        yield 'tag fixa 8.3-0.1.0' => ['ghcr.io/elavora/php-dev:8.3-0.1.0', false];
        yield 'tag fixa 8.3-0.3.0' => ['ghcr.io/elavora/php-dev:8.3-0.3.0', false];
        yield 'tag fixa com v' => ['ghcr.io/elavora/php-dev:8.3-v0.3.0', false];
    }

    #[DataProvider('images')]
    public function testDevelopmentImageReference(string $reference, bool $valid): void
    {
        self::assertSame([$reference], Skeleton::imageReferences('"image": "' . $reference . '"'));
        self::assertSame($valid, Skeleton::isValidImageReference($reference));
    }
}
