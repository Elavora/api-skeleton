# Guia de uso

[README](../README.md) · Próximo: [Ambiente de desenvolvimento e CI](ambiente-e-ci.md)

Projeto inicial para APIs HTTP criadas com `elavora/api-framework`.

## Instalação

```bash
composer create-project elavora/api-skeleton minha-api
cd minha-api
cp .env.example .env
docker compose up --build
```

Requisitos:

- PHP `>=8.3`
- `elavora/api-framework` `^2.0`
- Docker Compose 2.24 ou mais recente

O projeto versiona `composer.lock`. Instalações, CI e o Dockerfile usam
`composer install`, portanto reproduzem as mesmas versões.

O `composer.json` do projeto criado vem sem `name` e sem `description`, que eram do pacote do skeleton. Preencha os
dois se for publicar o projeto como pacote.

## Ambiente do Compose

O serviço `api` carrega `.env` por `env_file`. O arquivo é opcional: sem ele, o
bootstrap usa seus valores padrão. O Compose ainda usa `APP_PORT` para publicar
a porta, e os blocos `environment` dos overlays prevalecem sobre o arquivo.

Exemplo:

```dotenv
APP_DEBUG=true
APP_PORT=8080
LOG_DRIVER=stdout
```

Instale `elavora/api-log-stdout` antes de selecionar esse driver. O `.env`
permanece em `.gitignore` e `.dockerignore`, e nunca é copiado para a imagem.

Em automação, `ELAVORA_ENV_FILE` pode apontar para outro arquivo:

```bash
ELAVORA_ENV_FILE=/tmp/api-smoke.env docker compose --env-file /tmp/api-smoke.env config
```

## Fluxo HTTP

Com o serviço iniciado:

```bash
curl --fail http://localhost:8080/health
curl --fail http://localhost:8080/health/show
```

Ambas as rotas respondem `{"status":"ok"}`. `/health` é um alias explícito;
`/health/show` usa a convenção do framework e exige `#[Action]` em uma action
pública de instância.

Essas duas rotas são a checagem de saúde do projeto, não um exemplo. O job `Imagem Docker` do CI depende delas.
Mantenha o `HealthController` e o alias `/health` em `app/Http/HttpRoutes.php`.

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
docker build -t api-skeleton .
```

O `composer lint` roda o `elavora-standards lint`, do pacote `elavora/code-standards`. Ele roda `php -l` em todo
arquivo `.php` do projeto, menos `vendor/` e arquivos e pastas que começam com ponto. Melhorias no lint chegam
pelo PR do Dependabot que atualiza o pacote. Detalhes em
[lint.md](https://github.com/Elavora/code-standards/blob/main/docs/lint.md).

O PHPStan roda no nível 8 sem baseline. O entrypoint opcional de worker fica fora da análise estática enquanto
`elavora/api-queue-worker` não estiver instalado, mas o lint confere a sintaxe dele.

Ao mudar dependências, atualize e revise o lock deliberadamente. Builds e
pipelines devem continuar usando `composer install`, nunca `composer update`.

### Testes

Os testes de `tests/` são do seu projeto: conferem a aplicação e a configuração dela.

O comportamento do framework, como o bootstrap e a escolha das extensões pelo `.env`, é testado no próprio
`elavora/api-framework`, na [pasta `tests/` do framework](https://github.com/Elavora/api-framework/tree/main/tests).
Não copie esses testes para o projeto. O framework chega atualizado pelo Composer, mas a cópia fica parada e falha
no seu CI quando o framework muda, por exemplo, uma mensagem de erro.

---

[README](../README.md) · Próximo: [Ambiente de desenvolvimento e CI](ambiente-e-ci.md)
