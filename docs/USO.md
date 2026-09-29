# Guia de uso

Projeto inicial para APIs HTTP criadas com `elavora/api-framework`.

## Instalacao

```bash
composer create-project elavora/api-skeleton minha-api
cd minha-api
cp .env.example .env
docker compose up --build
```

Requisitos:

- PHP `>=8.3`
- `elavora/api-framework` `^1.0`
- Docker Compose 2.24 ou mais recente

O projeto versiona `composer.lock`. Instalacoes, CI e o Dockerfile usam
`composer install`, portanto reproduzem as mesmas versoes.

## Ambiente do Compose

O servico `api` carrega `.env` por `env_file`. O arquivo e opcional: sem ele, o
bootstrap usa seus valores padrao. O Compose ainda usa `APP_PORT` para publicar
a porta, e os blocos `environment` dos overlays prevalecem sobre o arquivo.

Exemplo:

```dotenv
APP_DEBUG=true
APP_PORT=8080
LOG_DRIVER=stdout
```

Instale `elavora/api-log-stdout` antes de selecionar esse driver. O `.env`
permanece em `.gitignore` e `.dockerignore`, e nunca e copiado para a imagem.

Em automacao, `ELAVORA_ENV_FILE` pode apontar para outro arquivo:

```bash
ELAVORA_ENV_FILE=/tmp/api-smoke.env docker compose --env-file /tmp/api-smoke.env config
```

## Fluxo HTTP

Com o servico iniciado:

```bash
curl --fail http://localhost:8080/health
curl --fail http://localhost:8080/health/show
```

Ambas as rotas respondem `{"status":"ok"}`. `/health` e um alias explicito;
`/health/show` usa a convencao do framework e exige `#[Action]` em uma action
publica de instancia.

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

O lint cobre `app/`, `core/`, `public/`, `tests/` e `worker.php` sem depender de
`find`, `xargs` ou Bash. O PHPStan roda no nivel 8 sem baseline. O entrypoint
opcional de worker fica fora da analise estatica enquanto
`elavora/api-queue-worker` nao estiver instalado, mas permanece coberto pelo
lint.

Ao mudar dependencias, atualize e revise o lock deliberadamente. Builds e
pipelines devem continuar usando `composer install`, nunca `composer update`.
