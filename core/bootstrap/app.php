<?php

declare(strict_types=1);

use App\Http\HttpRoutes;
use Elavora\Api\Framework\Bootstrap\ApplicationBootstrap;

return ApplicationBootstrap::create(
    basePath: dirname(__DIR__, 2),
    extensions: require dirname(__DIR__) . '/config/extensions.php',
    configure: HttpRoutes::register(...),
);
