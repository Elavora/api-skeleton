<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\QueueWorkerCommand;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Queue;

require __DIR__ . '/vendor/autoload.php';

if (!class_exists(QueueWorker::class)) {
    throw new RuntimeException('Instale elavora/api-queue-worker para executar o worker de filas.');
}

/** @var Application $app */
$app = require __DIR__ . '/core/bootstrap/app.php';

// Registre novos handlers em app/Worker/tasks.php. Este entrypoint somente
// prepara o worker generico e entrega a execucao para o pacote queue-worker.
/** @var TaskRegistry $tasks */
$tasks = require __DIR__ . '/app/Worker/tasks.php';

$command = new QueueWorkerCommand(new QueueWorker(
    queue: $app->container()->get(Queue::class),
    tasks: $tasks
));

exit($command->run($argv));
