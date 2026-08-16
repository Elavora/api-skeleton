<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApcuDockerConfigurationTest extends TestCase
{
    public function testApcuInstallationEnablesTheExtensionForPhpCli(): void
    {
        $dockerfile = (string) file_get_contents(dirname(__DIR__) . '/Dockerfile');
        $apcuBlockStart = strpos($dockerfile, 'if [ "$INSTALL_APCU" = "1" ]; then');
        $mysqlBlockStart = strpos($dockerfile, 'if [ "$INSTALL_MYSQL" = "1" ]; then');

        self::assertIsInt($apcuBlockStart);
        self::assertIsInt($mysqlBlockStart);
        self::assertGreaterThan($apcuBlockStart, $mysqlBlockStart);

        $apcuBlock = substr($dockerfile, $apcuBlockStart, $mysqlBlockStart - $apcuBlockStart);

        self::assertStringContainsString('docker-php-ext-enable apcu', $apcuBlock);
        self::assertStringContainsString(
            "printf '%s\\n' 'apc.enable_cli=1' > /usr/local/etc/php/conf.d/apcu-cli.ini",
            $apcuBlock
        );
    }

    public function testApcuComposeOverlayEnablesTheBuildAndRuntimeConfiguration(): void
    {
        $overlay = (string) file_get_contents(dirname(__DIR__) . '/core/compose/apcu.yml');

        self::assertStringContainsString('INSTALL_APCU: 1', $overlay);
        self::assertStringContainsString('CACHE_DRIVER: apcu', $overlay);
    }
}
