<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * Bloco do APCu no `Dockerfile` e no overlay `core/compose/apcu.yml` entregues pelo skeleton.
 *
 * Saiu de `tests/` porque confere arquivos do skeleton, e não a aplicação.
 *
 * @see docs/manutencao-do-skeleton.md#testes-movidos-para-tests-skeleton
 * @see docs/manutencao-do-skeleton.md#teste-de-arquivo-do-skeleton-fica-em-tests-skeleton
 */
final class ApcuDockerConfigurationTest extends TestCase
{
    /**
     * O bloco do APCu no `Dockerfile` vem antes do bloco do MySQL, habilita a extensão e liga `apc.enable_cli`.
     *
     * @see docs/manutencao-do-skeleton.md#testes-movidos-para-tests-skeleton
     */
    public function testApcuInstallationEnablesTheExtensionForPhpCli(): void
    {
        $dockerfile = Skeleton::read('Dockerfile');
        $apcuBlockStart = strpos($dockerfile, 'if [ "$INSTALL_APCU" = "1" ]; then');
        $mysqlBlockStart = strpos($dockerfile, 'if [ "$INSTALL_MYSQL" = "1" ]; then');

        self::assertIsInt($apcuBlockStart);
        self::assertIsInt($mysqlBlockStart);
        self::assertGreaterThan($apcuBlockStart, $mysqlBlockStart);

        $apcuBlock = substr($dockerfile, $apcuBlockStart, $mysqlBlockStart - $apcuBlockStart);

        self::assertStringContainsString('docker-php-ext-enable apcu', $apcuBlock);
        self::assertStringContainsString(
            "printf '%s\\n' 'apc.enable_cli=1' > /usr/local/etc/php/conf.d/apcu-cli.ini",
            $apcuBlock,
        );
    }

    /**
     * O overlay `core/compose/apcu.yml` liga o build do APCu e o driver de cache.
     *
     * @see docs/manutencao-do-skeleton.md#testes-movidos-para-tests-skeleton
     */
    public function testApcuComposeOverlayEnablesTheBuildAndRuntimeConfiguration(): void
    {
        $overlay = Skeleton::read('core/compose/apcu.yml');

        self::assertStringContainsString('INSTALL_APCU: 1', $overlay);
        self::assertStringContainsString('CACHE_DRIVER: apcu', $overlay);
    }
}
