<?php

declare(strict_types=1);

use Elavora\Api\Framework\Contracts\Extension;

/** @var list<Extension> $extensions */
$extensions = [];

/**
 * Pacotes opcionais nao entram nas dependencias do skeleton. A resolucao
 * dinamica evita referenciar classes ausentes durante o boot e a analise.
 *
 * @param array<string, mixed> $config
 */
$createExtension = static function (string $class, string $missingMessage, array $config): Extension {
    if (!class_exists($class)) {
        throw new RuntimeException($missingMessage);
    }

    $extension = new $class($config);
    if (!$extension instanceof Extension) {
        throw new RuntimeException(sprintf('%s deve implementar Extension.', $class));
    }

    return $extension;
};

// Pacotes opcionais nao fazem parte do framework base. Instale apenas o pacote
// usado pelo projeto e selecione o driver correspondente no ambiente.
$cacheDriver = getenv('CACHE_DRIVER') ?: '';
if ($cacheDriver !== '' && !in_array($cacheDriver, ['apcu', 'redis'], true)) {
    throw new RuntimeException('CACHE_DRIVER deve ser apcu ou redis.');
}

$redisPassword = getenv('REDIS_PASSWORD');
$redisPassword = $redisPassword === false || $redisPassword === '' ? null : $redisPassword;
$redisDatabase = getenv('REDIS_DATABASE');
$redisDatabase = $redisDatabase === false || $redisDatabase === '' ? null : $redisDatabase;

if ($cacheDriver === 'redis') {
    $extensions[] = $createExtension(
        'Elavora\Api\Extension\CacheRedis\RedisCacheExtension',
        'Instale elavora/api-cache-redis para usar CACHE_DRIVER=redis.',
        [
            'host' => getenv('REDIS_HOST') ?: 'redis',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'password' => $redisPassword,
            'database' => $redisDatabase,
            'prefix' => getenv('CACHE_PREFIX') ?: 'api:cache:',
        ]
    );
}

if ($cacheDriver === 'apcu') {
    $cacheConfig = [
        'prefix' => getenv('CACHE_PREFIX') ?: 'api:cache:',
    ];
    $cacheTtl = getenv('CACHE_TTL');
    if ($cacheTtl !== false && $cacheTtl !== '') {
        $cacheConfig['ttl'] = (int) $cacheTtl;
    }

    $extensions[] = $createExtension(
        'Elavora\Api\Extension\CacheApcu\ApcuCacheExtension',
        'Instale elavora/api-cache-apcu para usar CACHE_DRIVER=apcu.',
        $cacheConfig
    );
}

$redisQueueExtension = 'Elavora\Api\Extension\QueueRedis\RedisQueueExtension';
if (class_exists($redisQueueExtension)) {
    $extensions[] = $createExtension(
        $redisQueueExtension,
        'Instale elavora/api-queue-redis para registrar a fila Redis.',
        [
            'host' => getenv('REDIS_HOST') ?: 'redis',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'password' => $redisPassword,
            'database' => $redisDatabase,
            'prefix' => getenv('QUEUE_PREFIX') ?: 'api:queue:',
        ]
    );
}

$databaseDriver = getenv('DB_DRIVER') ?: '';
if ($databaseDriver !== '' && !in_array($databaseDriver, ['mysql', 'postgresql'], true)) {
    throw new RuntimeException('DB_DRIVER deve ser mysql ou postgresql.');
}

$databaseConfig = [
    'host' => getenv('DB_HOST') ?: $databaseDriver,
    'port' => (int) (getenv('DB_PORT') ?: ($databaseDriver === 'mysql' ? 3306 : 5432)),
    'database' => getenv('DB_DATABASE') ?: 'app',
    'username' => getenv('DB_USERNAME') ?: 'app',
    'password' => getenv('DB_PASSWORD') ?: '',
];

if ($databaseDriver === 'mysql') {
    $extensions[] = $createExtension(
        'Elavora\Api\Extension\DatabaseMySql\MySqlExtension',
        'Instale elavora/api-database-mysql para usar DB_DRIVER=mysql.',
        $databaseConfig
    );
}

if ($databaseDriver === 'postgresql') {
    $extensions[] = $createExtension(
        'Elavora\Api\Extension\DatabasePostgreSql\PostgreSqlExtension',
        'Instale elavora/api-database-postgresql para usar DB_DRIVER=postgresql.',
        $databaseConfig
    );
}

$logDriver = getenv('LOG_DRIVER') ?: '';
if ($logDriver !== '' && !in_array($logDriver, ['stdout', 'file', 'mongodb'], true)) {
    throw new RuntimeException('LOG_DRIVER deve ser stdout, file ou mongodb.');
}

if ($logDriver === 'stdout') {
    $extensions[] = $createExtension(
        'Elavora\Api\Extension\LogStdout\StdoutLogExtension',
        'Instale elavora/api-log-stdout para usar LOG_DRIVER=stdout.',
        [
            'stream' => getenv('LOG_STREAM') ?: 'stdout',
        ]
    );
}

if ($logDriver === 'file') {
    $extensions[] = $createExtension(
        'Elavora\Api\Extension\LogFile\FileLogExtension',
        'Instale elavora/api-log-file para usar LOG_DRIVER=file.',
        [
            'path' => getenv('LOG_FILE') ?: dirname(__DIR__, 2) . '/storage/logs/app.log',
        ]
    );
}

if ($logDriver === 'mongodb') {
    $extensions[] = $createExtension(
        'Elavora\Api\Extension\LogMongoDb\MongoLogExtension',
        'Instale elavora/api-log-mongodb para usar LOG_DRIVER=mongodb.',
        [
            'uri' => getenv('MONGO_LOG_URI') ?: null,
            'host' => getenv('MONGO_LOG_HOST') ?: 'mongo',
            'port' => getenv('MONGO_LOG_PORT') ?: '27017',
            'database' => getenv('MONGO_LOG_DATABASE') ?: 'api_logs',
            'collection' => getenv('MONGO_LOG_COLLECTION') ?: 'logs',
            'username' => getenv('MONGO_LOG_USERNAME') ?: null,
            'password' => getenv('MONGO_LOG_PASSWORD') ?: null,
        ]
    );
}

return $extensions;
