<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\TaskRegistry;

// Registre aqui as tarefas da fila: o nome gravado na fila e o handler que a executa.
// Prefira nomes pesquisaveis e estaveis, como documents.process ou email.send.
return new TaskRegistry();
