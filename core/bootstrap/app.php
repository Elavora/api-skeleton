<?php

declare(strict_types=1);

use App\Http\HttpRoutes;
use Elavora\Api\Framework\Bootstrap\ApplicationBootstrap;

return ApplicationBootstrap::create(
    basePath: dirname(__DIR__, 2),
    // Registre aqui as extensoes proprias do projeto e as que substituem configuracoes.
    // As extensoes instaladas e selecionadas no .env sao registradas pelo framework.
    extensions: [],
    configure: HttpRoutes::register(...),
);
