<?php

declare(strict_types=1);

namespace SkeletonTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SkeletonTests\Support\Skeleton;

/**
 * Forma do `.env.example`: grupos comentados por pacote e uma descrição acima de cada variável.
 *
 * Os testes conferem as regras da doc, não o texto exato. Eles não copiam a lista de variáveis do framework: a
 * existência de cada nome é conferida no framework instalado em `vendor/` e nos arquivos do Compose.
 *
 * @see docs/manutencao-do-skeleton.md#envexample
 * @see docs/manutencao-do-skeleton.md#grupos-do-envexample
 * @see docs/manutencao-do-skeleton.md#variáveis-do-envexample
 * @see docs/manutencao-do-skeleton.md#interpolação-do-compose
 */
final class EnvExampleTest extends TestCase
{
    /** Variável ativa (`NOME=valor`) ou comentada (`# NOME=valor`). */
    private const VARIABLE = '~^(?:# )?([A-Z][A-Z0-9_]*)=(.*)$~';

    /** Variável ativa (`NOME=valor`). */
    private const ACTIVE = '~^[A-Z][A-Z0-9_]*=.*$~';

    /** As duas linhas exatas do cabeçalho. */
    private const HEADER_LINKS = [
        '# https://github.com/Elavora/api-framework/blob/main/src/Bootstrap/EnvironmentExtensions.php',
        '# https://github.com/Elavora/api-framework/blob/main/docs/BOOTSTRAP.md',
    ];

    /** Variáveis ativas, nesta ordem. */
    private const ACTIVE_LINES = ['APP_DEBUG=false', 'APP_PORT=8080'];

    /** `.env.example` só com `APP_PORT`, para os casos de interpolação do Compose. */
    private const APP_PORT_ONLY = "# Porta do host em que o Compose publica a API.\nAPP_PORT=8080\n";

    /**
     * Exemplo mínimo que segue todas as regras. Os casos que falham são mutações dele.
     * Os nomes são os que a doc usa nos exemplos; não é a lista do framework.
     */
    private const VALID = <<<'ENV'
        # Exemplo de .env, lido pelo elavora/api-framework e pelo docker-compose.yml.
        # https://github.com/Elavora/api-framework/blob/main/src/Bootstrap/EnvironmentExtensions.php
        # https://github.com/Elavora/api-framework/blob/main/docs/BOOTSTRAP.md

        # Aplicação (elavora/api-framework e docker-compose.yml).
        # Liga o modo de depuração do framework.
        APP_DEBUG=false
        # Porta do host em que o Compose publica a API.
        APP_PORT=8080

        # Cache (elavora/api-cache-apcu ou elavora/api-cache-redis).
        # Escolhe o cache.
        # CACHE_DRIVER=apcu

        # Conexão Redis (elavora/api-cache-redis).
        # Endereço do servidor Redis.
        # REDIS_HOST=redis
        # Porta do servidor Redis.
        # REDIS_PORT=6379

        ENV;

    // ------------------------------------------------------------------ o arquivo do skeleton

    /**
     * O `.env.example` do skeleton segue as regras dos grupos.
     *
     * @see docs/manutencao-do-skeleton.md#grupos-do-envexample
     */
    public function testEnvExampleFollowsTheGroupRules(): void
    {
        self::assertSame([], self::groupViolations(Skeleton::read('.env.example')));
    }

    /**
     * O `.env.example` do skeleton segue as regras 1 a 4 e 6 das variáveis.
     *
     * @see docs/manutencao-do-skeleton.md#variáveis-do-envexample
     */
    public function testEnvExampleFollowsTheVariableRules(): void
    {
        self::assertSame([], self::variableViolations(Skeleton::read('.env.example')));
    }

    /**
     * Regra 5: cada nome do `.env.example` é lido pelo framework instalado ou pelo Compose.
     *
     * @see docs/manutencao-do-skeleton.md#variáveis-do-envexample
     * @see docs/manutencao-do-skeleton.md#interpolação-do-compose
     */
    public function testEveryEnvExampleVariableIsReadByTheFrameworkOrCompose(): void
    {
        self::assertSame([], self::unreadNames(Skeleton::read('.env.example')));
    }

    // ------------------------------------------------------------------ as regras, com os exemplos da doc

    /**
     * O exemplo mínimo passa em todas as regras. Garante que os casos que falham falham pela mutação.
     *
     * @see docs/manutencao-do-skeleton.md#grupos-do-envexample
     * @see docs/manutencao-do-skeleton.md#variáveis-do-envexample
     */
    public function testValidExamplePassesEveryRule(): void
    {
        self::assertSame([], self::groupViolations(self::VALID));
        self::assertSame([], self::variableViolations(self::VALID));
        self::assertSame([], self::unreadNames(self::VALID));
        self::assertSame([], self::groupViolations(str_replace("\n", "\r\n", self::VALID)), 'CRLF não muda as regras');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidGroups(): iterable
    {
        yield 'grupo que começa direto pela variável, sem o comentário com o pacote' => [self::mutate(
            "# Cache (elavora/api-cache-apcu ou elavora/api-cache-redis).\n# Escolhe o cache.\n",
            '',
        )];
        yield 'primeira linha do grupo sem elavora/<nome>' => [self::mutate(
            '# Cache (elavora/api-cache-apcu ou elavora/api-cache-redis).',
            '# Cache.',
        )];
        yield 'cabeçalho sem o link do EnvironmentExtensions.php' => [self::mutate(self::HEADER_LINKS[0] . "\n", '')];
        yield 'cabeçalho sem o link do BOOTSTRAP.md' => [self::mutate(self::HEADER_LINKS[1] . "\n", '')];
        yield 'cabeçalho com variável' => [self::mutate(
            self::HEADER_LINKS[1] . "\n",
            self::HEADER_LINKS[1] . "\n# Tempo de vida das chaves.\n# CACHE_TTL=60\n",
        )];
        yield 'duas linhas vazias seguidas' => [self::mutate("\n\n# Cache", "\n\n\n# Cache")];
        yield 'linha vazia no começo' => ["\n" . self::VALID];
        yield 'linha vazia no fim' => [self::VALID . "\n"];
        yield 'sem quebra de linha no fim' => [rtrim(self::VALID, "\n")];
        yield 'grupo da aplicação sem docker-compose.yml na primeira linha' => [self::mutate(
            '# Aplicação (elavora/api-framework e docker-compose.yml).',
            '# Aplicação (elavora/api-framework).',
        )];
        yield 'grupo da aplicação sem elavora/api-framework na primeira linha' => [self::mutate(
            '# Aplicação (elavora/api-framework e docker-compose.yml).',
            '# Aplicação (elavora/api-skeleton e docker-compose.yml).',
        )];
        yield 'grupo da aplicação com outra variável' => [self::mutate(
            "APP_PORT=8080\n",
            "APP_PORT=8080\n# Prefixo das chaves do cache.\n# CACHE_PREFIX=api:cache:\n",
        )];
        yield 'grupo da aplicação sem APP_PORT' => [self::mutate(
            "# Porta do host em que o Compose publica a API.\nAPP_PORT=8080\n",
            '',
        )];
    }

    /**
     * Cada caso que a doc diz que falha, e as bordas das regras 1 a 4 dos grupos.
     *
     * @param string $content conteúdo de `.env.example` que quebra uma regra dos grupos
     *
     * @see docs/manutencao-do-skeleton.md#grupos-do-envexample
     */
    #[DataProvider('invalidGroups')]
    public function testInvalidGroupsAreRejected(string $content): void
    {
        self::assertNotSame([], self::groupViolations($content));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidVariables(): iterable
    {
        yield 'o mesmo nome duas vezes' => [self::mutate(
            "# CACHE_DRIVER=apcu\n",
            "# CACHE_DRIVER=apcu\n# Escolhe o cache.\n# CACHE_DRIVER=redis\n",
        )];
        yield 'variável ativa além das duas' => [self::mutate('# CACHE_DRIVER=apcu', 'CACHE_DRIVER=apcu')];
        yield 'variável logo depois de outra, sem descrição' => [self::mutate("# Porta do servidor Redis.\n", '')];
        yield 'variável logo depois de uma linha vazia' => [self::mutate(
            "# CACHE_DRIVER=apcu\n",
            "# CACHE_DRIVER=apcu\n\n# CACHE_TTL=60\n",
        )];
        yield 'ELAVORA_ENV_FILE' => [self::mutate(
            "# CACHE_DRIVER=apcu\n",
            "# CACHE_DRIVER=apcu\n# Arquivo de ambiente.\n# ELAVORA_ENV_FILE=.env\n",
        )];
        yield 'variáveis ativas fora de ordem' => [self::mutate(
            "# Liga o modo de depuração do framework.\nAPP_DEBUG=false\n# Porta do host em que o Compose publica a API.\nAPP_PORT=8080\n",
            "# Porta do host em que o Compose publica a API.\nAPP_PORT=8080\n# Liga o modo de depuração do framework.\nAPP_DEBUG=false\n",
        )];
        yield 'APP_DEBUG com outro valor' => [self::mutate('APP_DEBUG=false', 'APP_DEBUG=true')];
        yield 'APP_PORT comentada' => [self::mutate('APP_PORT=8080', '# APP_PORT=8080')];
        yield 'linha que não é comentário nem variável' => [self::mutate(
            "# CACHE_DRIVER=apcu\n",
            "# CACHE_DRIVER=apcu\ntexto solto\n",
        )];
        yield 'variável com nome em minúsculas' => [self::mutate(
            "# CACHE_DRIVER=apcu\n",
            "# CACHE_DRIVER=apcu\n# Descrição.\ncache_ttl=60\n",
        )];
    }

    /**
     * Cada caso que a doc diz que falha nas regras 1 a 4 e 6 das variáveis, e algumas bordas.
     *
     * @param string $content conteúdo de `.env.example` que quebra uma regra das variáveis
     *
     * @see docs/manutencao-do-skeleton.md#variáveis-do-envexample
     */
    #[DataProvider('invalidVariables')]
    public function testInvalidVariablesAreRejected(string $content): void
    {
        self::assertNotSame([], self::variableViolations($content));
    }

    /**
     * Regra 5: `# APP_NAME=api` falha, porque nem o framework nem o Compose leem `APP_NAME`.
     *
     * @see docs/manutencao-do-skeleton.md#variáveis-do-envexample
     * @see docs/manutencao-do-skeleton.md#interpolação-do-compose
     */
    public function testVariableNotReadByTheFrameworkOrComposeIsRejected(): void
    {
        $content = self::mutate("# CACHE_DRIVER=apcu\n", "# CACHE_DRIVER=apcu\n# Nome da aplicação.\n# APP_NAME=api\n");

        self::assertSame([], self::variableViolations($content), 'só a regra 5 reprova este caso');
        self::assertSame(['APP_NAME'], self::unreadNames($content));
    }

    /**
     * Regra 5: `APP_DEBUG` vem do framework (`'APP_DEBUG'` num `.php` do bootstrap) e `APP_PORT` do Compose
     * (`${APP_PORT` com o nome inteiro, seguido de `}`, `:`, `-`, `?` ou `+`).
     *
     * @see docs/manutencao-do-skeleton.md#variáveis-do-envexample
     * @see docs/manutencao-do-skeleton.md#interpolação-do-compose
     */
    public function testApplicationVariablesComeFromTheFrameworkAndCompose(): void
    {
        self::assertStringContainsString("'APP_DEBUG'", implode("\n", self::frameworkSources()));
        self::assertMatchesRegularExpression('~\$\{APP_PORT[}:?+-]~', implode("\n", self::composeSources()));
    }

    /** @return iterable<string, array{string}> */
    public static function composeReadsAppPort(): iterable
    {
        yield '${APP_PORT}' => ['${APP_PORT}'];
        yield '${APP_PORT:-8080}' => ['"${APP_PORT:-8080}:8080"'];
        yield '${APP_PORT-8080}' => ['${APP_PORT-8080}'];
        yield '${APP_PORT:?defina a porta}' => ['${APP_PORT:?defina a porta}'];
        yield '${APP_PORT?defina a porta}' => ['${APP_PORT?defina a porta}'];
        yield '${APP_PORT:+8080}' => ['${APP_PORT:+8080}'];
        yield '${APP_PORT+8080}' => ['${APP_PORT+8080}'];
    }

    /**
     * Regra 5: cada forma de interpolação com chaves e o nome inteiro conta como leitura de `APP_PORT`.
     *
     * @param string $compose texto do Compose que lê `APP_PORT`
     *
     * @see docs/manutencao-do-skeleton.md#variáveis-do-envexample
     * @see docs/manutencao-do-skeleton.md#interpolação-do-compose
     */
    #[DataProvider('composeReadsAppPort')]
    public function testComposeInterpolationReadsTheVariable(string $compose): void
    {
        self::assertFrameworkDoesNotReadAppPort();
        self::assertSame([], self::unreadNames(self::APP_PORT_ONLY, $compose));
    }

    /** @return iterable<string, array{string}> */
    public static function composeDoesNotReadAppPort(): iterable
    {
        yield 'só ${APP_PORT_EXTRA:-9090}' => ['"${APP_PORT_EXTRA:-9090}:8080"'];
        yield 'só ${APP_PORTA}' => ['${APP_PORTA}'];
        yield 'só $APP_PORT, sem chaves' => ['"$APP_PORT:8080"'];
        yield 'nome ausente' => ['services: {}'];
    }

    /**
     * Regra 5: um nome que é só o começo de outra variável, ou a forma sem chaves, não conta como leitura.
     *
     * @param string $compose texto do Compose que não lê `APP_PORT`
     *
     * @see docs/manutencao-do-skeleton.md#variáveis-do-envexample
     * @see docs/manutencao-do-skeleton.md#interpolação-do-compose
     */
    #[DataProvider('composeDoesNotReadAppPort')]
    public function testComposeInterpolationOfAnotherNameIsNotARead(string $compose): void
    {
        self::assertFrameworkDoesNotReadAppPort();
        self::assertSame(['APP_PORT'], self::unreadNames(self::APP_PORT_ONLY, $compose));
    }

    /** Os casos de interpolação só fazem sentido se o framework não ler `APP_PORT`, como diz a doc. */
    private static function assertFrameworkDoesNotReadAppPort(): void
    {
        self::assertStringNotContainsString("'APP_PORT'", implode("\n", self::frameworkSources()));
    }

    // ------------------------------------------------------------------ regras

    private static function mutate(string $search, string $replace): string
    {
        if (substr_count(self::VALID, $search) !== 1) {
            throw new \LogicException("Trecho deveria aparecer uma vez no exemplo: {$search}");
        }

        return str_replace($search, $replace, self::VALID);
    }

    /**
     * Linhas do arquivo, sem a quebra de linha final.
     *
     * @return list<string>
     */
    private static function lines(string $content): array
    {
        $content = str_replace("\r\n", "\n", $content);
        if (str_ends_with($content, "\n")) {
            $content = substr($content, 0, -1);
        }

        return explode("\n", $content);
    }

    /**
     * Regras 1 a 4 dos grupos.
     *
     * @return list<string> uma mensagem por regra quebrada
     */
    private static function groupViolations(string $content): array
    {
        $violations = [];
        if (!str_ends_with($content, "\n")) {
            $violations[] = 'o arquivo não termina com uma quebra de linha';
        }
        $lines = self::lines($content);
        if ($lines[0] === '') {
            $violations[] = 'linha vazia no começo';
        }
        if ($lines[count($lines) - 1] === '') {
            $violations[] = 'linha vazia no fim';
        }

        $groups = [];
        $current = [];
        foreach ($lines as $number => $line) {
            if ($line !== '') {
                $current[] = $line;
                continue;
            }
            if ($number > 0 && $lines[$number - 1] === '') {
                $violations[] = 'duas linhas vazias seguidas na linha ' . ($number + 1);
            }
            if ($current !== []) {
                $groups[] = $current;
            }
            $current = [];
        }
        if ($current !== []) {
            $groups[] = $current;
        }

        foreach ($groups as $index => $group) {
            if (!str_starts_with($group[0], '# ') || preg_match('~elavora/[a-z0-9-]+~', $group[0]) !== 1) {
                $violations[] = 'grupo ' . ($index + 1) . " não começa por um comentário com elavora/<nome>: {$group[0]}";
            }
        }

        $header = $groups[0] ?? [];
        if (self::names($header) !== []) {
            $violations[] = 'o cabeçalho tem variável';
        }
        foreach (self::HEADER_LINKS as $link) {
            if (!in_array($link, $header, true)) {
                $violations[] = "o cabeçalho não tem a linha {$link}";
            }
        }

        $application = $groups[1] ?? [''];
        if (!str_contains($application[0], 'elavora/api-framework') || !str_contains($application[0], 'docker-compose.yml')) {
            $violations[] = 'a primeira linha do grupo da aplicação não cita elavora/api-framework e docker-compose.yml';
        }
        $names = self::names($application);
        sort($names);
        if ($names !== ['APP_DEBUG', 'APP_PORT']) {
            $violations[] = 'o grupo da aplicação não tem só APP_DEBUG e APP_PORT: ' . implode(', ', $names);
        }

        return $violations;
    }

    /**
     * Regras 1 a 4 e 6 das variáveis. A regra 5 está em unreadNames().
     *
     * @return list<string> uma mensagem por regra quebrada
     */
    private static function variableViolations(string $content): array
    {
        $violations = [];
        $lines = self::lines($content);
        $active = [];
        $seen = [];
        foreach ($lines as $index => $line) {
            if ($line === '') {
                continue;
            }
            $number = $index + 1;
            $isActive = preg_match(self::ACTIVE, $line) === 1;
            if (!$isActive && !str_starts_with($line, '#')) {
                $violations[] = "linha {$number} não é comentário nem variável: {$line}";
            }
            if ($isActive) {
                $active[] = $line;
            }
            if (preg_match(self::VARIABLE, $line, $match) !== 1) {
                continue;
            }
            $seen[$match[1]] = ($seen[$match[1]] ?? 0) + 1;
            $above = $lines[$index - 1] ?? '';
            if (!str_starts_with($above, '#') || preg_match(self::VARIABLE, $above) === 1) {
                $violations[] = "linha {$number}: {$match[1]} sem comentário de descrição na linha de cima";
            }
        }
        if ($active !== self::ACTIVE_LINES) {
            $violations[] = 'as variáveis ativas não são exatamente ' . implode(' e ', self::ACTIVE_LINES)
                . ': ' . implode(', ', $active);
        }
        foreach ($seen as $name => $count) {
            if ($count > 1) {
                $violations[] = "{$name} aparece {$count} vezes";
            }
        }
        if (str_contains($content, 'ELAVORA_ENV_FILE')) {
            $violations[] = 'o arquivo tem ELAVORA_ENV_FILE';
        }

        return $violations;
    }

    /**
     * Regra 5: nomes que não aparecem como `'NOME'` no bootstrap do framework nem como `${NOME` seguido de `}`, `:`,
     * `-`, `?` ou `+` no Compose.
     *
     * @param string      $content conteúdo de `.env.example`
     * @param string|null $compose texto do Compose; `null` usa os arquivos do skeleton (composeSources())
     * @return list<string>
     *
     * @see docs/manutencao-do-skeleton.md#interpolação-do-compose
     */
    private static function unreadNames(string $content, ?string $compose = null): array
    {
        $framework = implode("\n", self::frameworkSources());
        $compose ??= implode("\n", self::composeSources());

        return array_values(array_filter(
            self::names(self::lines($content)),
            static fn(string $name): bool => !str_contains($framework, "'{$name}'")
                && preg_match('~\$\{' . preg_quote($name, '~') . '[}:?+-]~', $compose) !== 1,
        ));
    }

    /**
     * Nomes das variáveis (ativas e comentadas), na ordem do arquivo.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private static function names(array $lines): array
    {
        $names = [];
        foreach ($lines as $line) {
            if (preg_match(self::VARIABLE, $line, $match) === 1) {
                $names[] = $match[1];
            }
        }

        return $names;
    }

    /** @return list<string> conteúdo dos `.php` de `vendor/elavora/api-framework/src/Bootstrap/` */
    private static function frameworkSources(): array
    {
        $files = glob(Skeleton::path('vendor/elavora/api-framework/src/Bootstrap') . '/*.php') ?: [];
        self::assertNotSame([], $files, 'framework ausente em vendor/: rode composer install');

        return array_map(static fn(string $file): string => (string) file_get_contents($file), $files);
    }

    /** @return list<string> conteúdo do `docker-compose.yml` e dos arquivos de `core/compose/` */
    private static function composeSources(): array
    {
        $files = array_merge(
            [Skeleton::path('docker-compose.yml')],
            glob(Skeleton::path('core/compose') . '/*') ?: [],
        );

        return array_map(static fn(string $file): string => (string) file_get_contents($file), $files);
    }
}
