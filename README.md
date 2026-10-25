# Elavora API Skeleton

Projeto inicial para APIs HTTP criadas com `elavora/api-framework`.

O skeleton é só o ponto de partida. Os arquivos copiados na criação passam a ser do seu projeto, e o skeleton não
envia atualizações depois. O comportamento da API (bootstrap, extensões, rotas) vem do framework, pelo Composer.

## Requisitos

- PHP `>=8.3`
- `elavora/api-framework` `^2.0`
- Docker Compose 2.24 ou mais recente, para o `env_file.required`

## Criação

```bash
composer create-project elavora/api-skeleton minha-api
cd minha-api
cp .env.example .env
docker compose up --build
curl http://localhost:8080/health
```

A resposta é:

```json
{"status":"ok"}
```

O `.env` é opcional. O que ele faz e como ligar Redis, MySQL, PostgreSQL ou APCu no Compose está no
[Guia de uso](docs/USO.md).

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
|-- bootstrap/app.php     # Monta Application, registra extensões do projeto e rotas.
`-- compose/              # Overlays opcionais do Docker Compose.
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

## Documentação

1. [Guia de uso](docs/USO.md): o `.env`, os overlays do Compose, a checagem de saúde, a fila e os comandos de
   qualidade.
2. [Ambiente de desenvolvimento e CI](docs/ambiente-e-ci.md): o Dev Container, o CI de qualidade, as regras de
   estilo e o Dependabot.
3. [Documentação do framework](https://github.com/Elavora/api-framework#documentação): bootstrap, extensões
   carregadas pelo ambiente, rotas, requests e responses.

<!-- skeleton:inicio -->
## Só no repositório do skeleton

Esta seção e os arquivos citados nela existem só no repositório `Elavora/api-skeleton`. Eles são removidos quando
um projeto é criado com `composer create-project`.

- [Manutenção do skeleton](docs/manutencao-do-skeleton.md): o que é entregue e o que é só do skeleton, como as
  versões são publicadas, a limpeza na criação e o conteúdo exato da entrega.
<!-- skeleton:fim -->
