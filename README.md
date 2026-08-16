# Elavora API Skeleton

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-skeleton.svg?style=flat-square)](https://packagist.org/packages/elavora/api-skeleton)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-skeleton.svg?style=flat-square)](https://packagist.org/packages/elavora/api-skeleton)
[![Composer Quality](https://github.com/Elavora/api-skeleton/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-skeleton/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-skeleton/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-skeleton/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-skeleton.svg?style=flat-square)](LICENSE)
Projeto inicial para APIs HTTP criadas com `elavora/api-framework`.

## Requisitos

- PHP `>=8.3`
- `elavora/api-framework` `^1.0`
- Docker Compose 2.24 ou mais recente para `env_file.required`

## Criação

```bash
composer create-project elavora/api-skeleton minha-api
cd minha-api
cp .env.example .env
docker compose up --build
curl http://localhost:8080/health
```

O `.env` é opcional. Quando existe, o Compose injeta suas variáveis no serviço
`api`; quando não existe, a aplicação usa os defaults do código. Valores
declarados em `environment` pelos overlays continuam tendo precedência.
O arquivo permanece fora da imagem e do Git.

O projeto inicial depende somente de `elavora/api-framework`. As versões
resolvidas ficam travadas em `composer.lock`, usado também durante o build da
imagem. A resposta inicial é:

```json
{"status":"ok"}
```

## Estrutura

```text
app/
|-- Attributes/       # Validações e hooks HTTP específicos da aplicação.
|-- Contracts/        # Interfaces específicas do produto.
|-- DataTypes/        # Valores tipados com regras do domínio.
|-- Enums/            # Enumerações do domínio.
|-- Http/
|   |-- Controller/   # Entrada HTTP: recebe Request e devolve Response.
|   `-- HttpRoutes.php # Aliases e URLs que não seguem /controller/action.
|-- Integrations/     # Clientes de APIs e sistemas externos.
|-- Repositories/     # Leitura e persistência de dados externos ao domínio.
|-- Services/         # Regras de negócio e casos de uso.
|-- Support/          # Helpers internos pequenos e reutilizáveis.
`-- Worker/           # Handlers e registro de tarefas executadas em fila.
core/
|-- bootstrap/app.php     # Monta Application, extensões e rotas.
|-- compose/              # Overlays opcionais do Docker Compose.
`-- config/extensions.php # Ativa somente pacotes opcionais instalados.
public/index.php          # Única entrada exposta pelo servidor HTTP.
worker.php                # Entrada CLI para consumo de filas.
tests/                    # Testes da aplicação.
```

As pastas vazias com `.gitkeep` existem para manter um padrão previsível entre
projetos. Remova apenas quando decidir oficialmente não usar uma categoria.

### Exemplos didaticos

- `app/Attributes/Permission.php` mostra como bloquear uma action antes do
  controller. Em um projeto real, conecte o attribute ao service de autenticação.
- `app/DataTypes/ProjectCode.php` mostra um DataType específico da aplicação que
  implementa `Insertable` para persistência e `Responseable` para retorno JSON.
- `app/Http/Controller/HealthController.php` mostra o formato mínimo de um
  controller.
- `app/Http/HttpRoutes.php` mostra como registrar aliases opcionais. Actions que
  seguem `/controller/action` não precisam ser cadastradas manualmente.
- `app/Worker/ExampleTaskHandler.php` mostra como implementar uma tarefa de fila.

## Extensões opcionais

Instale somente os módulos usados pela aplicação. O bootstrap registra
automaticamente as extensões instaladas e selecionadas no `.env`.

| Necessidade | Pacote | Configuração principal | Overlay |
| --- | --- | --- | --- |
| Cache local no processo PHP | `elavora/api-cache-apcu` | `CACHE_DRIVER=apcu` | `core/compose/apcu.yml` |
| Cache compartilhado | `elavora/api-cache-redis` | `CACHE_DRIVER=redis` | `core/compose/redis.yml` |
| Publicar tarefas em fila junto com cache Redis | `elavora/api-queue-redis` | `REDIS_HOST=redis` | `core/compose/redis.yml` |
| Consumir tarefas da fila | `elavora/api-queue-worker` | Nenhuma | Nenhum |
| Banco MySQL | `elavora/api-database-mysql` | `DB_DRIVER=mysql` | `core/compose/mysql.yml` |
| Banco PostgreSQL | `elavora/api-database-postgresql` | `DB_DRIVER=postgresql` | `core/compose/postgresql.yml` |
| Logs do container | `elavora/api-log-stdout` | `LOG_DRIVER=stdout` | Nenhum |
| Logs em arquivo | `elavora/api-log-file` | `LOG_DRIVER=file` | Nenhum |
| Logs em MongoDB | `elavora/api-log-mongodb` | `LOG_DRIVER=mongodb` | Nenhum |

Cache Redis e fila Redis podem ser instalados juntos. Ambos reutilizam o pacote
base `elavora/api-redis`, instalado automaticamente pelo Composer.

O overlay atual `core/compose/redis.yml` ativa `CACHE_DRIVER=redis`; portanto,
ele exige `elavora/api-cache-redis`. Um perfil Docker para fila Redis isolada ainda
não faz parte do skeleton.

Defina a configuração necessária em `.env` e combine o Compose principal com o
overlay apropriado:

Combinar não significa copiar e colar arquivos. Informe cada arquivo ao Docker
Compose com `-f`; ele aplica os overlays na ordem recebida sobre a configuração
base de `docker-compose.yml`.

```bash
composer require elavora/api-cache-apcu
docker compose -f docker-compose.yml -f core/compose/apcu.yml up --build

composer require elavora/api-cache-redis elavora/api-queue-redis
docker compose -f docker-compose.yml -f core/compose/redis.yml up --build

composer require elavora/api-queue-redis elavora/api-queue-worker
php worker.php --queue=default

composer require elavora/api-database-mysql
docker compose -f docker-compose.yml -f core/compose/mysql.yml up --build

composer require elavora/api-database-postgresql
docker compose -f docker-compose.yml -f core/compose/postgresql.yml up --build
```

Os complementos podem ser combinados:

```bash
docker compose -f docker-compose.yml -f core/compose/redis.yml -f core/compose/postgresql.yml up --build
```

Os pacotes `cache-redis` e `queue-redis` usam `elavora/api-redis` internamente para
reutilizar conexões equivalentes.

Os overlays instalam na imagem PHP apenas a extensão requerida pelo perfil.
Escolha `CACHE_DRIVER=apcu` ou `CACHE_DRIVER=redis`; não registre ambos como
provider do mesmo contrato.

Storage e DataTypes também são opcionais, mas não são selecionados pelo
bootstrap. Instale o pacote necessário quando o código da aplicação for
utilizá-lo, por exemplo:

```bash
composer require elavora/api-storage-s3
composer require elavora/api-datatype-email
```

SQLite também exige registro explícito no boot da aplicação. Logs em MongoDB
exigem a extensão PHP `mongodb` e infraestrutura configurada pela aplicação.

## Qualidade e build

Use o lock versionado em instalações, CI e imagens:

```bash
composer install --no-interaction --prefer-dist
composer validate --strict --no-check-publish
composer lint
composer analyse
composer test
composer check
docker build -t minha-api .
```

`composer check` executa lint portátil, PHPStan nível 8 e PHPUnit. Atualize o
`composer.lock` deliberadamente ao mudar dependências; um `composer.json`
incompatível com o lock faz a instalação e a validação falharem.
