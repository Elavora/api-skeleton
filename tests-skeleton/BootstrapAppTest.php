<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * O `core/bootstrap/app.php` entregue deixa o framework carregar as extensões selecionadas no `.env`.
 *
 * A regra é de conteúdo: o arquivo não cita o argumento `loadEnvironmentExtensions` em nenhum lugar, nem em
 * comentário, nem com `true`. Assim vale o padrão do framework, sem depender das mensagens de erro dele.
 *
 * @see docs/manutencao-do-skeleton.md#corebootstrapappphp
 */
final class BootstrapAppTest extends TestCase
{
    private const APP_FILE = 'core/bootstrap/app.php';

    private const ARGUMENT = 'loadEnvironmentExtensions';

    /**
     * O `app.php` versionado não contém `loadEnvironmentExtensions`. Falha com
     * `loadEnvironmentExtensions: false`, com `loadEnvironmentExtensions: true` e com o nome só num comentário.
     *
     * @see docs/manutencao-do-skeleton.md#corebootstrapappphp
     */
    public function testAppFileDoesNotCiteLoadEnvironmentExtensions(): void
    {
        self::assertStringNotContainsString(self::ARGUMENT, Skeleton::read(self::APP_FILE), self::APP_FILE);
    }
}
