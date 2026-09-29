<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

/**
 * Controller de health check do projeto base.
 */
final class HealthController
{
    /**
     * Retorna status basico da aplicacao.
     *
     * Controllers recebem a Request e devolvem uma Response. Quando surgir
     * regra de negocio, crie um service em app/Services e chame-o daqui.
     */
    #[Action]
    public function show(Request $request): Response
    {
        return Response::json(payload: ['status' => 'ok']);
    }
}
