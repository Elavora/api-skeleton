<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/** docs/manutencao-do-skeleton.md: "`.devcontainer/devcontainer.json`". */
final class DevContainerTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function config(): array
    {
        // json_decode rejeita comentários: o arquivo precisa ser JSON válido, sem comentários.
        $data = json_decode(Skeleton::read('.devcontainer/devcontainer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    public function testTopLevelFields(): void
    {
        $config = self::config();

        self::assertSame('Elavora API Skeleton - PHP 8.3', $config['name'] ?? null);
        self::assertSame(Skeleton::DEV_IMAGE, $config['image'] ?? null);
        self::assertSame('vscode', $config['remoteUser'] ?? null);
        self::assertSame([8080], $config['forwardPorts'] ?? null);
        self::assertSame(
            'git config --global --add safe.directory "$(pwd)" && composer install --no-interaction --prefer-dist',
            $config['postCreateCommand'] ?? null,
        );
    }

    public function testVsCodeExtensions(): void
    {
        $extensions = self::config()['customizations']['vscode']['extensions'] ?? null;

        self::assertIsArray($extensions);
        self::assertEqualsCanonicalizing(
            [
                'xdebug.php-debug',
                'bmewburn.vscode-intelephense-client',
                'junstyle.php-cs-fixer',
                'EditorConfig.EditorConfig',
            ],
            $extensions,
        );
    }

    public function testVsCodeSettingsFormatOnSaveWithProjectRules(): void
    {
        $settings = self::config()['customizations']['vscode']['settings'] ?? null;

        self::assertIsArray($settings);
        self::assertEquals(
            [
                'php.validate.executablePath' => '/usr/local/bin/php',
                'php-cs-fixer.executablePath' => '/usr/local/bin/php-cs-fixer',
                'php-cs-fixer.config' => '.php-cs-fixer.php;.php-cs-fixer.dist.php',
                'php-cs-fixer.rules' => '@PER-CS',
                'php-cs-fixer.onsave' => false,
                '[php]' => [
                    'editor.defaultFormatter' => 'junstyle.php-cs-fixer',
                    'editor.formatOnSave' => true,
                ],
            ],
            $settings,
        );
        self::assertFalse($settings['php-cs-fixer.onsave']);
        self::assertTrue($settings['[php]']['editor.formatOnSave']);
    }

    public function testImageIsTheSameInDevContainerAndSkeletonTestsJob(): void
    {
        $job = Skeleton::jobBlock(Skeleton::read('.github/workflows/skeleton-tests.yml'), 'entrega');

        self::assertNotNull($job, 'skeleton-tests.yml precisa do job entrega');
        self::assertSame([Skeleton::DEV_IMAGE], array_values(array_unique(Skeleton::imageReferences($job))));
        self::assertSame(self::config()['image'] ?? null, Skeleton::DEV_IMAGE);
    }
}
