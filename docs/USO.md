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

A lista do `.env.example` foi copiada na criação do projeto e não se atualiza sozinha. A referência das variáveis é o
framework: o
[`EnvironmentExtensions.php`](https://github.com/Elavora/api-framework/blob/main/src/Bootstrap/EnvironmentExtensions.php)
e o [Bootstrap compartilhado](https://github.com/Elavora/api-framework/blob/main/docs/BOOTSTRAP.md).

Os blocos `environment` dos overlays valem mais que o `.env`.

O `.env` fica no `.gitignore` e no `.dockerignore`, e nunca é copiado para a imagem.

Em automação, `ELAVORA_ENV_FILE` aponta o `env_file` para outro arquivo:

```bash
ELAVORA_ENV_FILE=/tmp/api-smoke.env docker compose --env-file /tmp/api-smoke.env config
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

O `composer require` só funciona se o pacote tiver uma versão que aceite o `elavora/api-framework` travado no
`composer.lock`. Se não tiver, o Composer recusa a instalação e diz o motivo.

As outras extensões (logs, worker de fila, storage, DataTypes e outras) não têm overlay. Elas estão na
[lista de pacotes da Elavora no Packagist](https://packagist.org/packages/elavora/).

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
