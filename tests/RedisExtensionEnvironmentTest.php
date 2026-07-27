<?php

declare(strict_types=1);

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Extension;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RedisExtensionConfigurationSpy implements Extension
{
    /** @var array<string, array<string, mixed>> */
    public static array $configs = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        self::$configs[(string) $config['prefix']] = $config;
    }

    public function register(Application $application): void
    {
    }
}

final class RedisExtensionEnvironmentTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([
            'CACHE_DRIVER',
            'CACHE_PREFIX',
            'QUEUE_PREFIX',
            'REDIS_PASSWORD',
            'REDIS_DATABASE',
        ] as $variable) {
            putenv($variable);
        }

        RedisExtensionConfigurationSpy::$configs = [];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRedisCredentialsArePropagatedToCacheAndQueue(): void
    {
        self::assertTrue(class_alias(
            RedisExtensionConfigurationSpy::class,
            'Elavora\Api\Extension\CacheRedis\RedisCacheExtension'
        ));
        self::assertTrue(class_alias(
            RedisExtensionConfigurationSpy::class,
            'Elavora\Api\Extension\QueueRedis\RedisQueueExtension'
        ));

        putenv('CACHE_DRIVER=redis');
        putenv('CACHE_PREFIX=cache:test:');
        putenv('QUEUE_PREFIX=queue:test:');
        putenv('REDIS_PASSWORD=secret');
        putenv('REDIS_DATABASE=7');

        require dirname(__DIR__) . '/core/config/extensions.php';

        self::assertSame('secret', RedisExtensionConfigurationSpy::$configs['cache:test:']['password']);
        self::assertSame('7', RedisExtensionConfigurationSpy::$configs['cache:test:']['database']);
        self::assertSame('secret', RedisExtensionConfigurationSpy::$configs['queue:test:']['password']);
        self::assertSame('7', RedisExtensionConfigurationSpy::$configs['queue:test:']['database']);

        RedisExtensionConfigurationSpy::$configs = [];
        putenv('REDIS_PASSWORD=');
        putenv('REDIS_DATABASE=');

        require dirname(__DIR__) . '/core/config/extensions.php';

        self::assertNull(RedisExtensionConfigurationSpy::$configs['cache:test:']['password']);
        self::assertNull(RedisExtensionConfigurationSpy::$configs['cache:test:']['database']);
        self::assertNull(RedisExtensionConfigurationSpy::$configs['queue:test:']['password']);
        self::assertNull(RedisExtensionConfigurationSpy::$configs['queue:test:']['database']);
    }
}
