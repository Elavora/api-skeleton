# Elavora API Skeleton

Projeto inicial para APIs HTTP criadas com `elavora/api-framework`.

## Requisitos

- PHP `>=8.3`
- `elavora/api-framework` `^2.0`, que traz o bootstrap compartilhado
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
`-- config/extensions.php # Registra extensões próprias e overrides do projeto.
public/index.php          # Única entrada exposta pelo servidor HTTP.
worker.php                # Entrada CLI para consumo de filas.
tests/                    # Testes da aplicação.
```

As pastas vazias com `.gitkeep` existem para manter um padrão previsível entre
projetos. Remova apenas quando decidir oficialmente não usar uma categoria.

### O que já vem em `app/`

O projeto começa sem código de exemplo. Só três arquivos têm código:

- `app/Http/Controller/HealthController.php` é a checagem de saúde do projeto. Ele responde `{"status":"ok"}` em
  `/health/show`, pela convenção `/controller/action`.
- `app/Http/HttpRoutes.php` registra o alias `/health` para o mesmo controller. É o endereço que o CI e os health
  checks de infraestrutura usam.
- `app/Worker/tasks.php` devolve o registro de tarefas da fila, que começa vazio. Registre nele as suas tarefas.

Não apague o `HealthController` nem a rota `/health`. O job `Imagem Docker` do CI sobe a API e confere essas duas
rotas.

As outras pastas de `app/` começam vazias, só com `.gitkeep`.

## Extensões opcionais

Instale só os módulos que a aplicação usa. O bootstrap registra sozinho as extensões instaladas e selecionadas no
`.env`.

> **Atenção:** hoje nenhuma extensão tem versão que aceite o `elavora/api-framework` 2. Os comandos
> `composer require` abaixo falham até essas versões saírem.

A tabela lista só as extensões que têm overlay do Docker Compose em `core/compose/`:

| Necessidade | Pacote | Configuração principal | Overlay |
| --- | --- | --- | --- |
| Cache local no processo PHP | `elavora/api-cache-apcu` | `CACHE_DRIVER=apcu` | `core/compose/apcu.yml` |
| Cache compartilhado | `elavora/api-cache-redis` | `CACHE_DRIVER=redis` | `core/compose/redis.yml` |
| Fila Redis, junto com o cache Redis | `elavora/api-queue-redis` | `REDIS_HOST=redis` | `core/compose/redis.yml` |
| Banco MySQL | `elavora/api-database-mysql` | `DB_DRIVER=mysql` | `core/compose/mysql.yml` |
| Banco PostgreSQL | `elavora/api-database-postgresql` | `DB_DRIVER=postgresql` | `core/compose/postgresql.yml` |

As outras extensões (logs, worker de fila, storage, DataTypes e outras) estão na
[lista de pacotes da Elavora no Packagist](https://packagist.org/packages/elavora/).

Para usar um overlay, instale o pacote, defina a configuração no `.env` e informe o overlay ao Docker Compose com
`-f`. O Compose aplica os overlays na ordem recebida, sobre o `docker-compose.yml`:

```bash
composer require elavora/api-cache-apcu
docker compose -f docker-compose.yml -f core/compose/apcu.yml up --build

composer require elavora/api-cache-redis elavora/api-queue-redis
docker compose -f docker-compose.yml -f core/compose/redis.yml up --build

composer require elavora/api-database-mysql
docker compose -f docker-compose.yml -f core/compose/mysql.yml up --build

composer require elavora/api-database-postgresql
docker compose -f docker-compose.yml -f core/compose/postgresql.yml up --build
```

Os overlays podem ser combinados:

```bash
docker compose -f docker-compose.yml -f core/compose/redis.yml -f core/compose/postgresql.yml up --build
```

O overlay `core/compose/redis.yml` ativa `CACHE_DRIVER=redis`, então exige o `elavora/api-cache-redis`. O cache e a
fila Redis podem ser instalados juntos e reutilizam o pacote base `elavora/api-redis`.

Cada overlay instala na imagem PHP só a extensão que o perfil exige. Escolha `CACHE_DRIVER=apcu` ou
`CACHE_DRIVER=redis`, não os dois.

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

`composer check` executa o lint de sintaxe (`elavora-standards lint`), o PHPStan nível 8 e o PHPUnit. Atualize o
`composer.lock` deliberadamente ao mudar dependências; um `composer.json`
incompatível com o lock faz a instalação e a validação falharem.

## Atualização do bootstrap

A inicialização e os defaults das extensões oficiais pertencem ao framework e recebem atualizações via Composer.
`core/bootstrap/app.php` e `core/config/extensions.php` só guardam o que é do projeto: extensões próprias, rotas,
middlewares e overrides.

## Ambiente de desenvolvimento e CI

O projeto já vem com quatro peças: um Dev Container, um CI de qualidade, as regras de estilo da Elavora e o
Dependabot.

Para usar o Dev Container, instale o Docker Desktop e a extensão Dev Containers do VS Code. Abra o projeto e
execute **Dev Containers: Reopen in Container**. Depois, no terminal, rode `composer check`.

Os arquivos copiados na criação passam a ser do seu projeto. O skeleton não envia atualizações depois.

O que evolui chega sozinho. Framework, regras de estilo e pipeline de qualidade chegam por PR do Dependabot. A
imagem de desenvolvimento chega pela tag que acompanha as versões estáveis. A lista do que se atualiza sozinho e do
que é manual está em
[Ambiente de desenvolvimento e CI](docs/ambiente-e-ci.md#o-que-fica-no-projeto-e-como-atualizar).

## Documentação

Leia nesta ordem:

1. [Guia de uso](docs/USO.md): instalação, ambiente do Compose, rotas e comandos de qualidade.
2. [Ambiente de desenvolvimento e CI](docs/ambiente-e-ci.md): o que o projeto recebe, o que fica congelado, como
   cada item se atualiza e como trocar a versão do PHP.

<!-- skeleton:inicio -->
## Só no repositório do skeleton

Esta seção e os arquivos citados nela existem só no repositório `Elavora/api-skeleton`. Eles são removidos quando
um projeto é criado com `composer create-project`.

- [Manutenção do skeleton](docs/manutencao-do-skeleton.md): o que é entregue e o que é só do skeleton, como as
  versões são publicadas, a limpeza na criação e o conteúdo exato da entrega.
<!-- skeleton:fim -->
