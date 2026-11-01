# Guia de uso

[README](../README.md) · Próximo: [Ambiente de desenvolvimento e CI](ambiente-e-ci.md)

Este guia explica os arquivos que o projeto recebe na criação. Como criar e rodar o projeto está no
[README](../README.md#criação).

O comportamento do framework não é descrito aqui. Ele muda com as versões do framework, e a doc dele acompanha:

- [Bootstrap compartilhado](https://github.com/Elavora/api-framework/blob/main/docs/BOOTSTRAP.md): como a aplicação
  é montada e como as extensões são escolhidas pelas variáveis de ambiente;
- [Segurança do roteamento](https://github.com/Elavora/api-framework/blob/main/docs/ROTEAMENTO.md): a convenção
  `/controller/action` e o registro de rotas explícitas.

## Dependências

O projeto versiona o `composer.lock`. Instalações, CI e o `Dockerfile` usam `composer install`, portanto reproduzem
as mesmas versões. Ao mudar dependências, atualize e revise o lock de propósito. Builds e pipelines usam sempre
`composer install`, nunca `composer update`.

O `composer.json` do projeto criado vem sem `name` e sem `description`, que eram do pacote do skeleton. Preencha os
dois se for publicar o projeto como pacote.

## Arquivo `.env`

O serviço `api` do `docker-compose.yml` carrega o `.env` por `env_file`. O arquivo é opcional: sem ele, valem os
padrões do framework.

- `APP_DEBUG` é lido pelo framework.
- `APP_PORT` é usado pelo Compose para publicar a porta da API (padrão `8080`).
- As variáveis das extensões (cache, Redis, fila, banco, log) são lidas pelo framework. Ele registra sozinho as
  extensões instaladas e selecionadas no `.env`. Veja o
  [Bootstrap compartilhado](https://github.com/Elavora/api-framework/blob/main/docs/BOOTSTRAP.md).

O `.env.example` traz exemplos dessas variáveis, em grupos. O comentário no começo de cada grupo diz de qual pacote
ele é, e o comentário acima de cada variável diz o que ela faz. Só `APP_DEBUG` e `APP_PORT` vêm ativas. As outras vêm
comentadas: descomente as do pacote que instalar.

Nem toda extensão tem variável de ambiente. SQLite e storage local, por exemplo, não têm: elas entram pelo
`core/bootstrap/app.php` (veja [Extensões sem variável de ambiente](#extensões-sem-variável-de-ambiente)).

A lista do `.env.example` foi copiada na criação do projeto e não se atualiza sozinha. A referência das variáveis é o
framework: o
[`EnvironmentExtensions.php`](https://github.com/Elavora/api-framework/blob/main/src/Bootstrap/EnvironmentExtensions.php)
e o [Bootstrap compartilhado](https://github.com/Elavora/api-framework/blob/main/docs/BOOTSTRAP.md).

Os blocos `environment` dos overlays valem mais que o `.env`.

### Configurar o log logo no começo

Com `APP_DEBUG=false`, uma resposta 500 não traz o motivo do erro, e sem uma extensão de log nada é registrado.
Quem recebe um 500 sem log não tem como saber a causa. Por isso configure o log antes de escrever o primeiro
código, junto com a criação do `.env`:

```bash
composer require elavora/api-log-stdout
```

No `.env`:

```text
LOG_DRIVER=stdout
```

Suba o projeto e leia o log com `docker compose logs -f api`. Se preferir um arquivo, use `elavora/api-log-file` com
`LOG_DRIVER=file` e `LOG_FILE=storage/logs/app.log` (a pasta `storage/` deve existir e ser gravável). Use um só
driver de log. Para investigar um erro só em desenvolvimento, `APP_DEBUG=true` mostra o motivo na resposta, mas não o
deixe ligado em produção.

O `.env` fica no `.gitignore` e no `.dockerignore`, e nunca é copiado para a imagem.

Em automação, `ELAVORA_ENV_FILE` aponta o `env_file` para outro arquivo:

```bash
ELAVORA_ENV_FILE=/tmp/api-smoke.env docker compose --env-file /tmp/api-smoke.env config
```

## Pasta `storage`

A pasta `storage/` guarda o que a aplicação gera ao rodar: o arquivo do SQLite, o log em arquivo e os arquivos do
`elavora/api-storage-local`. Ela existe no projeto criado, com um `.gitkeep`. O `.gitignore` ignora o conteúdo e
mantém só o `.gitkeep`, então esses dados não vão para o Git. O `.dockerignore` exclui a pasta do `COPY` da imagem, e
o `Dockerfile` cria `/app/storage` com permissão de escrita para o processo da API.

Use caminhos relativos a ela nas extensões, por exemplo `storage/app.sqlite` ou `LOG_FILE=storage/logs/app.log`.
O SQLite não cria pastas: se o caminho tiver uma subpasta, crie-a antes.

Os dados ficam dentro do contêiner e somem com `docker compose down`. Para mantê-los, monte um volume em
`/app/storage` no `docker-compose.yml`:

```yaml
services:
  api:
    volumes:
      - storage:/app/storage
volumes:
  storage:
```

## Overlays do Docker Compose

Cada arquivo de `core/compose/` liga um serviço ao `docker-compose.yml`. Ele instala na imagem PHP só a extensão que
o serviço exige e define as variáveis no bloco `environment` do serviço `api`.

| Overlay | Instala na imagem | Sobe o serviço | Pacote que ele espera |
| --- | --- | --- | --- |
| `core/compose/apcu.yml` | APCu | nenhum | `elavora/api-cache-apcu` |
| `core/compose/redis.yml` | `ext-redis` | `redis` | `elavora/api-cache-redis` (e, se quiser fila, `elavora/api-queue-redis`) |
| `core/compose/mysql.yml` | PDO MySQL | `mysql` | `elavora/api-database-mysql` |
| `core/compose/postgresql.yml` | PDO PostgreSQL | `postgresql` | `elavora/api-database-postgresql` |

Para usar um overlay, instale o pacote e informe o overlay ao Compose com `-f`. O Compose aplica os overlays na ordem
recebida, sobre o `docker-compose.yml`:

```bash
composer require elavora/api-cache-redis
docker compose -f docker-compose.yml -f core/compose/redis.yml up --build
```

Os overlays podem ser combinados:

```bash
docker compose -f docker-compose.yml -f core/compose/redis.yml -f core/compose/postgresql.yml up --build
```

Não combine `apcu.yml` com `redis.yml`. Os dois escolhem o cache, e o projeto usa um só.

Para trocar de um cache pelo outro, não basta trocar o overlay e o `CACHE_DRIVER`. Remova também o pacote antigo:

```bash
composer remove elavora/api-cache-apcu
composer require elavora/api-cache-redis
```

Se o pacote antigo ficar no `composer.json` e no `composer.lock`, o `composer install --no-dev` do `Dockerfile` falha
no build. O overlay novo não instala a extensão do cache antigo na imagem (por exemplo, o `redis.yml` não instala o
APCu), e o Composer recusa instalar `elavora/api-cache-apcu` sem `ext-apcu`. O mesmo vale no sentido contrário: ao sair
do Redis para o APCu, rode `composer remove elavora/api-cache-redis`.

O `composer require` só funciona se o pacote tiver uma versão que aceite o `elavora/api-framework` travado no
`composer.lock`. Se não tiver, o Composer recusa a instalação e diz o motivo.

As outras extensões (logs, worker de fila, storage, DataTypes e outras) não têm overlay. Elas estão na
[lista de pacotes da Elavora no Packagist](https://packagist.org/packages/elavora/).

### Instalar o pacote sem PHP no host

Alguns pacotes exigem uma extensão do PHP que o host e a imagem `composer:2` não têm. O caso mais comum é o
`elavora/api-cache-apcu`, que exige `ext-apcu`. Nesse caso `composer require elavora/api-cache-apcu` falha na
verificação de plataforma, com uma mensagem como `requires ext-apcu * -> it is missing from your system`.

Há duas saídas. Escolha uma.

**1. Rodar o Composer na imagem de desenvolvimento.** A `ghcr.io/elavora/php-dev:8.3-latest` tem o APCu e as demais
extensões. Na pasta do projeto, no PowerShell (Docker Desktop no Windows):

```powershell
docker run --rm -u root --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest composer require elavora/api-cache-apcu
```

No bash (macOS ou WSL com Docker Desktop):

```bash
docker run --rm -u root -v "$PWD":/workspace -w /workspace ghcr.io/elavora/php-dev:8.3-latest composer require elavora/api-cache-apcu
```

**Git Bash no Windows.** Os exemplos em bash não funcionam no Git Bash: ele converte o `$PWD` (`/c/Users/...`) e o
`-w /workspace` em caminhos do próprio Git (como `.../Git/workspace`), e o Docker monta a pasta errada ou recusa o
diretório de trabalho. Desligue a conversão com `MSYS_NO_PATHCONV=1` e passe o caminho do Windows, que o `pwd -W`
devolve:

```bash
MSYS_NO_PATHCONV=1 docker run --rm -u root -v "$(pwd -W):/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest composer require elavora/api-cache-apcu
```

O `-u` importa porque a imagem roda como o usuário de uid 1000. No Docker Desktop do Windows, a pasta montada não
pertence a esse usuário, e o Composer para com `composer.json is not writable`. O mesmo vale para o aviso de que não
consegue gravar o `.phpunit.result.cache`. A solução é `-u root`: no Docker Desktop (Windows e macOS) os arquivos
continuam sendo seus no host. Em um host Linux, `-u root` deixaria `composer.json`, `composer.lock` e `vendor/` com
dono root. Lá use o seu próprio usuário: `-u "$(id -u):$(id -g)"`.

**2. Ignorar a verificação da extensão só nesse comando.** Qualquer imagem do Composer serve, porque nada do pacote
é executado:

No PowerShell:

```powershell
docker run --rm -u root --mount "type=bind,source=$($PWD.Path),target=/app" -w /app composer:2 require elavora/api-cache-apcu --ignore-platform-req=ext-apcu
```

No bash:

```bash
docker run --rm -u root -v "$PWD":/app -w /app composer:2 require elavora/api-cache-apcu --ignore-platform-req=ext-apcu
```

(`-u root` pelo mesmo motivo acima.) O `composer.lock` fica correto. A extensão passa a existir de verdade na imagem da
API quando você sobe com o overlay `core/compose/apcu.yml`, que instala o APCu. Não use `--ignore-platform-reqs`
(no plural), que desliga a checagem de tudo.

## Extensões do projeto

Há dois jeitos de uma extensão entrar na aplicação:

- **Extensões selecionadas no `.env`.** O framework registra sozinho as extensões instaladas e escolhidas pelas
  variáveis de ambiente (cache, banco, log, fila). Não escreva nada no código para elas.
- **Extensões do projeto.** Uma extensão que o projeto escreve, ou uma que substitui uma configuração, entra na lista
  `extensions:` do `core/bootstrap/app.php`. A lista começa vazia.

Para registrar uma extensão do projeto, coloque a instância na lista:

```php
return ApplicationBootstrap::create(
    basePath: dirname(__DIR__, 2),
    extensions: [
        new MinhaExtension(),
    ],
    configure: HttpRoutes::register(...),
);
```

### Extensões sem variável de ambiente

Nem toda extensão é escolhida pelo `.env`. O `.env.example` só cobre cache, Redis, fila, banco (MySQL e PostgreSQL) e
log. Pacotes como `elavora/api-database-sqlite` e `elavora/api-storage-local` não têm variável de ambiente: instalar o
pacote não basta, e a extensão **não** é registrada sozinha. Registre-a na lista `extensions:` do
`core/bootstrap/app.php`.

SQLite, com o arquivo em `storage/app.sqlite` (a pasta `storage/` deve existir no projeto):

```php
use Elavora\Api\Extension\DatabaseSqlite\SqliteExtension;

return ApplicationBootstrap::create(
    basePath: dirname(__DIR__, 2),
    extensions: [
        new SqliteExtension(['path' => dirname(__DIR__, 2) . '/storage/app.sqlite']),
    ],
    configure: HttpRoutes::register(...),
);
```

Storage local, com os arquivos em `storage/files`:

```php
use Elavora\Api\Extension\StorageLocal\LocalStorageExtension;

return ApplicationBootstrap::create(
    basePath: dirname(__DIR__, 2),
    extensions: [
        new LocalStorageExtension(dirname(__DIR__, 2) . '/storage/files'),
    ],
    configure: HttpRoutes::register(...),
);
```

Use caminhos absolutos montados com `dirname(__DIR__, 2)` (a raiz do projeto, o mesmo `basePath` do bootstrap). Um
caminho relativo vale a partir da pasta em que o PHP foi iniciado e muda de um ambiente para outro. O `SqliteExtension`
recebe um array de configuração (`path` para arquivo, `memory => true` para banco em memória, ou um mapa
`connections`), e o `LocalStorageExtension` recebe o diretório raiz. Os detalhes de cada pacote estão no README dele.

Para saber se um pacote é escolhido pelo `.env`, procure o nome dele no
[`EnvironmentExtensions.php`](https://github.com/Elavora/api-framework/blob/main/src/Bootstrap/EnvironmentExtensions.php).
Se não aparece lá, registre-o em `extensions:`.

Não coloque na lista uma extensão que o `.env` já seleciona: ela seria registrada duas vezes. A ordem em que o
framework registra as extensões está no
[Bootstrap compartilhado](https://github.com/Elavora/api-framework/blob/main/docs/BOOTSTRAP.md).

## Checagem de saúde

Com o serviço iniciado:

```bash
curl --fail http://localhost:8080/health
curl --fail http://localhost:8080/health/show
```

As duas rotas respondem `{"status":"ok"}`. `/health` é um alias registrado em `app/Http/HttpRoutes.php`.
`/health/show` chega ao `HealthController` pela convenção `/controller/action` do framework.

As duas rotas são um exemplo prático e também a checagem de saúde do projeto. O job `Imagem Docker` do CI depende
delas. Mantenha o `HealthController` e o alias `/health`.

## Tarefas de fila

O arquivo `app/Worker/tasks.php` devolve um `TaskRegistry` vazio. Para cada tarefa, crie um handler em `app/Worker/`
que implemente `TaskHandler` e registre-o com `add`:

```php
return (new TaskRegistry())
    ->add('email.send', new SendEmailHandler());
```

O nome (`email.send`) é o que fica gravado na fila. Prefira nomes estáveis e fáceis de pesquisar. O worker
(`php worker.php`) exige o pacote `elavora/api-queue-worker`.

## Qualidade

```bash
composer install --no-interaction --prefer-dist
composer validate --strict --no-check-publish
composer lint
composer analyse
composer test
composer check
docker build -t minha-api .
```

`composer check` roda o lint, o PHPStan e o PHPUnit.

O `docker build` instala só as extensões que você pede por `--build-arg`. Se o projeto usa um pacote que exige uma
extensão, passe o argumento dela, ou o `composer install` do build falha. No caso do `elavora/api-cache-apcu`, o
erro é `requires ext-apcu * -> it is missing from your system`, e a correção é o `INSTALL_APCU=1`:

```bash
docker build --build-arg INSTALL_APCU=1 -t minha-api .
```

Os outros argumentos são `INSTALL_REDIS`, `INSTALL_MYSQL` e `INSTALL_POSTGRESQL`, todos com padrão `0`. O
`docker compose` com um overlay de `core/compose/` já passa o argumento certo (o `apcu.yml` define `INSTALL_APCU: 1`).

- O `composer lint` roda o `elavora-standards lint`, do pacote `elavora/code-standards`. O que ele confere está em
  [lint.md](https://github.com/Elavora/code-standards/blob/main/docs/lint.md).
- O PHPStan roda no nível 8, sem baseline. O `worker.php` e o `app/Worker/` ficam fora da análise enquanto o
  `elavora/api-queue-worker` não estiver instalado. O lint confere a sintaxe deles.

### Testes

Os testes de `tests/` são do seu projeto e conferem a aplicação. Os que vêm com o projeto conferem as rotas `/health`
e `/health/show`. Escreva ao lado deles os testes do seu código.

O comportamento do framework, como o bootstrap e a escolha das extensões pelo `.env`, é testado no próprio
`elavora/api-framework`, na [pasta `tests/` do framework](https://github.com/Elavora/api-framework/tree/main/tests).
Não copie esses testes para o projeto. O framework chega atualizado pelo Composer, mas a cópia fica parada e falha
no seu CI quando o framework muda, por exemplo, uma mensagem de erro.

---

[README](../README.md) · Próximo: [Ambiente de desenvolvimento e CI](ambiente-e-ci.md)
