# Manutenção do skeleton

[README](../README.md) · Veja também: [Ambiente de desenvolvimento e CI](ambiente-e-ci.md)

Esta página é para quem mantém o repositório `Elavora/api-skeleton`. Ela não vai para os projetos criados a partir
dele: o script de criação a remove (veja [Limpeza na criação do projeto](#limpeza-na-criação-do-projeto)).

## Por que separar o que é do skeleton

O `api-skeleton` tem dois papéis ao mesmo tempo.

Ele **é um pacote da Elavora**, publicado no Packagist como `elavora/api-skeleton`. Como todo pacote da
organização, ele tem release por labels, sincronização de labels e notificação ao Packagist.

Ele também **é o ponto de partida de outros projetos**. Tudo o que estiver nele na hora do
`composer create-project` vira arquivo do projeto novo. O projeto **não recebe atualizações depois**.

Essa última regra orienta tudo aqui. Um erro copiado para um projeto só sai dele se alguém daquele projeto corrigir
à mão. Por isso:

1. o que é copiado é o mínimo: arquivos finos, que só apontam para algo atualizável;
2. o que evolui se atualiza sozinho, de um destes dois jeitos:
   - **por PR do Dependabot**, com a referência fixa: pacote Composer (`elavora/api-framework`,
     `elavora/code-standards`) travado no lock, ou workflow reutilizável do `Elavora/.github` fixado por SHA com o
     comentário de versão;
   - **por tag que acompanha as estáveis**, sem PR: a imagem `ghcr.io/elavora/php-dev` na tag `8.3-latest`, usada
     pelo Dev Container e, por padrão, pelo pipeline central;
3. nenhuma referência copiada aponta para `main`, para uma branch ou para uma tag beta;
4. a versão do PHP, e a de cada serviço do Compose, é uma decisão do projeto. Ela é a mesma em produção, no Dev
   Container e no CI. Por isso o Dependabot não cuida de imagens Docker: ele trocaria a versão num lugar só. O
   projeto troca todos os lugares juntos, como descreve [Versão do PHP](ambiente-e-ci.md#versão-do-php);
5. a documentação entregue não cita SHA nem tag fixa da imagem. Ela aponta para o arquivo onde a referência está.
   Os valores exatos ficam nesta página, que não é entregue.

Se a automação do skeleton fosse junto, cada projeto criado tentaria publicar releases como `elavora/api-skeleton`,
apagaria as próprias labels toda semana e procuraria um secret do Packagist. Por isso os arquivos se dividem em dois
grupos:

| Grupo | Exemplos | Vai para o projeto criado? |
| --- | --- | --- |
| **Entrega** | `app/`, `core/`, `tests/`, `.devcontainer/`, `.github/workflows/quality.yml`, `.github/dependabot.yml`, `.editorconfig`, `.php-cs-fixer.dist.php`, `phpunit.xml`, `docs/USO.md`, `docs/ambiente-e-ci.md` | sim, e passa a ser do projeto |
| **Só do skeleton** | `.github/workflows/skeleton-*.yml`, `tests-skeleton/`, esta página, o script de limpeza | não, é removido na criação |

A explicação da entrega para quem usa o projeto está em [Ambiente de desenvolvimento e CI](ambiente-e-ci.md). Lá
está a tabela do que fica congelado e de como cada item se atualiza.

O conteúdo exato da entrega está na [Referência da entrega](#referência-da-entrega), mais abaixo.

## Como uma versão do skeleton é publicada

O skeleton usa o fluxo central do [`Elavora/.github`](https://github.com/Elavora/.github/blob/main/docs/release.md),
na versão `1.3.0`. Na prática:

1. Abra o PR para a `main` e aplique as labels. Elas decidem a versão:
   - `upgrade`: major (`1.1.0` → `2.0.0`);
   - `enhancement`, `feature` ou `improvement`: minor (`1.1.0` → `1.2.0`);
   - qualquer outra label, ou nenhuma: patch (`1.1.0` → `1.1.1`).
2. Sem a label `release`, o merge gera uma **beta** (`1.2.0-beta.1`, depois `1.2.0-beta.2`...). Com `release`, gera
   a **estável** (`1.2.0`).
3. No merge, o workflow cria a tag (sem `v`) e a GitHub Release com as notas em português. Depois pede ao Packagist
   que atualize o pacote.

A seção "Notas de release" do template de PR da organização, quando preenchida, substitui o título do PR nas notas.

Quem roda `composer create-project elavora/api-skeleton` recebe a **última estável**. As betas só são instaladas
com `--stability=beta`. Elas servem para testar uma mudança antes de liberá-la para todos.

As regras completas de versão (agregação de PRs, transições de beta, tags legadas) estão em
[versionamento.md](https://github.com/Elavora/.github/blob/main/docs/versionamento.md).

### Primeira versão no fluxo novo

A adoção sai em etapas:

1. **PR de adoção, com a label `upgrade` e sem `release`.** Ele gera `2.0.0-beta.1`. O major acompanha o framework
   2, que o skeleton passa a exigir (`elavora/api-framework: ^2.0`). Com `enhancement` sairia `1.1.0-beta.1`, que é
   menor que a `v1.1.0-rc.2` legada (veja [Tags antigas](#tags-antigas)).
2. **Validação.** Um projeto real é criado a partir da beta
   (`composer create-project elavora/api-skeleton <pasta> 2.0.0-beta.1`) e usado de verdade. Conferir pelo menos:
   - a limpeza: nenhum arquivo só do skeleton, `README.md` sem o bloco de manutenção, `composer.json` sem
     `post-create-project-cmd`;
   - o Dev Container abre e formata ao salvar;
   - o primeiro PR roda `quality / Qualidade PHP` e `Imagem Docker`, com o comentário fixo;
   - o primeiro PR do Dependabot roda o pipeline central (veja [Riscos e pendências](#riscos-e-pendências));
   - um PR do Dependabot para o `Elavora/.github` troca o SHA e o comentário de versão do `quality.yml`, e os testes
     da entrega continuam passando.
3. **PR seguinte, com `release`.** Ele gera a estável, com o que a validação tiver corrigido.

Enquanto só houver a beta, `composer create-project elavora/api-skeleton` continua instalando a `v1.0.0`.

### Tags antigas

As tags `vX.Y.Z` existentes contam como histórico. A última estável é a `v1.0.0`. As `vX.Y.Z-rc.N` são ignoradas
pelo fluxo novo.

Os PRs integrados desde a `v1.0.0` (#37, #38 com `enhancement`, #39) entram na primeira versão, junto com o PR de
adoção.

Para o Composer, um `1.1.0-beta.1` seria menor que a `v1.1.0-rc.2` antiga. Quem pedisse `--stability=RC` receberia
essa rc. Por isso o PR de adoção usa `upgrade`, que gera `2.0.0-beta.1`.

## Labels

O repositório é sincronizado com o catálogo de labels da organização
([labels.md](https://github.com/Elavora/.github/blob/main/docs/labels.md)). Isso acontece uma vez por semana e sob
demanda, em **Actions > Sincronizar labels > Run workflow**.

A sincronização apaga toda label fora do catálogo. Hoje isso remove `IA`, `github_actions`, `github-actions`, `php`,
`security`, `ci`, `major`, `minor`, `patch` e `prerelease`. Ela também cria `feature` e `improvement`.

O Dependabot é configurado com `labels: ["dependencies"]` para não recriar as labels de ecossistema.

## Packagist

A notificação ao Packagist é um job do workflow de release. O antigo `packagist-sync.yml` foi removido.

O job precisa do secret `PACKAGIST_WEBHOOK_URL`, de preferência na organização, com acesso ao repositório
`api-skeleton`.

Se esse job falhar, a tag e a release já existem. Corrija o secret e use **Re-run failed jobs**.

## Limpeza na criação do projeto

Dois mecanismos tiram os arquivos só do skeleton do projeto novo. Os dois chegam ao mesmo resultado:

1. **`export-ignore` no `.gitattributes`.** O pacote baixado do Packagist (o zip da tag) já vem sem esses arquivos.
   É o caminho normal do `composer create-project`.
2. **Script `tools/skeleton-post-create.php`**, chamado pelo `post-create-project-cmd` do Composer. Ele remove o que
   tiver sobrado (por exemplo, com `--prefer-source`, que clona o repositório inteiro). Depois limpa as referências
   aos arquivos removidos e, por fim, apaga a si mesmo.

Cada workflow só do skeleton também tem a condição `github.repository == 'Elavora/api-skeleton'`. Se um deles
escapar da limpeza, ele não roda em outro repositório.

### Ao adicionar um arquivo só do skeleton

Coloque o caminho em três lugares: a lista `SKELETON_ONLY` do script, o bloco do `.gitattributes` e a tabela da
[referência](#arquivos-só-do-skeleton). Os testes da entrega acusam a diferença.

### Ao mudar algo da entrega

Pergunte primeiro: isso pode ficar num pacote ou no workflow central, em vez de ser copiado? Se for copiado, os
projetos já criados nunca recebem a mudança.

Quando a mudança corrigir um erro que já foi entregue, descreva nas notas de release o que os projetos existentes
precisam fazer à mão.

## Testes da entrega

O workflow `Testes do skeleton` (`.github/workflows/skeleton-tests.yml`) verifica o skeleton como produto:

- **Entrega**: roda os testes de `tests-skeleton/`. Eles conferem o conteúdo dos arquivos entregues e executam o
  script de limpeza numa cópia do repositório;
- **Build de produção**: confere que o `docker build` do `Dockerfile` (com `composer install --no-dev`) continua
  funcionando;
- **Perfis de aplicação**: sobe a API com cada overlay de `core/compose/` e confere `/health`, a extensão PHP e as
  classes esperadas;
- **actionlint**: valida os workflows.

Para rodar os testes da entrega localmente, na raiz do repositório, no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && vendor/bin/phpunit -c tests-skeleton/phpunit.xml --exclude-group docker'
```

Os testes de `tests/` são da aplicação e vão para o projeto criado. Eles conferem o código de `app/`. Teste que fala
do skeleton em si (limpeza, workflows do skeleton, conteúdo da entrega, arquivos como o `Dockerfile` e o Compose)
fica em `tests-skeleton/`, que não vai para o projeto.

### Teste de arquivo do skeleton fica em `tests-skeleton/`

`tests/` não tem teste que confira o conteúdo de um arquivo do skeleton, como o `Dockerfile`, o
`docker-compose.yml`, os overlays de `core/compose/`, o `composer.json` ou o `composer.lock`.

Esses testes ficam em `tests-skeleton/`. O motivo: depois da criação, esses arquivos são do projeto. O projeto pode
instalar outras extensões no `Dockerfile`, mudar o build ou trocar o Compose. Um teste copiado para `tests/`
travaria a escolha do skeleton e falharia no CI do projeto na primeira mudança, sem que a aplicação estivesse
errada.

A conferência continua existindo, mas no lugar certo: ela garante que o skeleton entrega esses arquivos certos. Os
testes que saíram de `tests/` por esse motivo estão em
[Testes movidos para `tests-skeleton/`](#testes-movidos-para-tests-skeleton).

### Teste do framework fica no framework

`tests/` não tem teste que confira só o comportamento do `elavora/api-framework`. Exemplos: as mensagens de erro do
`EnvironmentExtensions` para um driver inválido ou um pacote ausente, e as variáveis do Redis repassadas ao cache e à
fila.

Esses testes ficam no repositório `Elavora/api-framework`, em `tests/EnvironmentExtensionsTest.php` e
`tests/RedisExtensionEnvironmentTest.php` ([pasta `tests/` do framework](https://github.com/Elavora/api-framework/tree/main/tests)).

O motivo: o projeto recebe o framework pelo Composer, mas os testes copiados ficam congelados. Quando o framework
muda uma mensagem de erro, o teste copiado falha no CI do projeto, sem que nada do projeto tenha mudado.

A lista exata do que fica em `tests/` está em [`app/` e `tests/`](#app-e-tests).

### Atualizações do Dependabot não quebram os testes

O Dependabot do skeleton abre PRs que mudam referências fixas. Esses PRs precisam passar nos testes da entrega sem
ninguém editar os testes.

Por isso os testes conferem o **formato** das referências que o Dependabot atualiza, e nunca o **valor**:

| O que o Dependabot muda | O que o teste confere | O que o teste não confere |
| --- | --- | --- |
| SHA e comentário do `Elavora/.github` nos workflows | a linha `uses` tem o caminho certo, `@`, um SHA de 40 caracteres hexadecimais minúsculos, um espaço, `# ` e uma versão `X.Y.Z` | qual SHA e qual versão |
| SHA e comentário das actions de terceiros | a linha `uses` tem `@`, um SHA de 40 caracteres hexadecimais minúsculos e um comentário `# v` seguido de uma versão (`v7`, `v7.0`, `v7.0.1`) | qual SHA e qual versão |
| Restrição de versão no `composer.json` | o major: `elavora/api-framework` começa com `^2.`; `elavora/code-standards` começa com `^1.` seguido de um minor 1 ou maior (`^1.1`, `^1.2`, `^1.10`) | o resto da restrição (`^2.0` e `^2.1` passam) |
| Versões instaladas no `composer.lock` | nada | o conteúdo do lock |
| Bloco do `.editorconfig` (quando a versão nova do pacote muda o bloco) | os marcadores e a estrutura (veja [Regras de estilo](#regras-de-estilo)) | se o bloco é igual ao que a versão travada gera |

Exemplos que precisam passar:

- `uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0` trocado
  por outro SHA com `# 1.4.0`;
- `actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7` trocado por outro SHA com `# v7.1.0`;
- `"elavora/api-framework": "^2.0"` trocado por `"^2.1"`;
- `"elavora/code-standards": "^1.1"` trocado por `"^1.2"`.

Exemplos que precisam falhar:

- `uses: Elavora/.github/.github/workflows/php-quality.yml@1.3.0` (tag em vez de SHA);
- `uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb606f84016dd031ad748d009d820fd5487` (sem o comentário
  de versão);
- `uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb` (SHA curto);
- `"elavora/api-framework": "^3.0"` (outro major);
- `"elavora/code-standards": "^1.0"` (minor abaixo de 1: a 1.0.0 não tem o `lint`);
- `"elavora/code-standards": "^2.1"` (outro major).

Os valores da tabela [Versões e referências](#versões-e-referências) são os da adoção. Nenhum teste os compara com
os workflows. Depois de um PR do Dependabot, a tabela pode ficar com o valor antigo.

## Riscos e pendências

- **Primeiro PR de adoção**: o formatador reformata o código existente e commita um `style: organiza o código` na
  branch. É esperado.
- **PRs do Dependabot e OIDC.** O pipeline central descobre a própria versão pelo token OIDC (`id-token: write`).
  Se o GitHub não conceder o `id-token` nos PRs do Dependabot, o check falha nesses PRs, com a mensagem pedindo o
  `automation_ref`. Conferir na validação da beta. Se falhar, a correção é no `Elavora/.github`, não no projeto.
- **PRs de fork** não recebem `id-token` e falham no início do pipeline central. Aceito: os projetos trabalham com
  branches internas.
- **Perfis de aplicação e o framework 2.** Os pacotes de extensão ainda não têm versão que aceite
  `elavora/api-framework` 2. As tags exigem versões `0.x`, e as branches `fix/estabiliza-pacote` exigem `^1.0`. Um perfil
  cujo pacote não aceite o framework 2 sai da matriz até o pacote ter uma versão compatível, e isso é registrado no
  PR. Hoje isso tira os perfis `apcu`, `redis`, `mysql` e `postgresql`. Fica o perfil `base`.

## Referência da entrega

Esta seção define o conteúdo exato que o skeleton entrega. Ela é a base dos testes de `tests-skeleton/`. Ela vale
também para o próprio repositório `api-skeleton`, que usa os mesmos arquivos.

### Versões e referências

| Constante | Valor na adoção | Como se atualiza no projeto |
| --- | --- | --- |
| Imagem de desenvolvimento | `ghcr.io/elavora/php-dev:8.3-latest` | sozinha: a tag acompanha as estáveis da imagem |
| Imagem do CI de qualidade | nenhuma no `quality.yml`: vale o padrão do `php-quality.yml`, que na 1.3.0 é `ghcr.io/elavora/php-dev:8.3-latest` | sozinha |
| `Elavora/.github` | `d96e9bb606f84016dd031ad748d009d820fd5487`, com o comentário `# 1.3.0` | PR do Dependabot |
| `elavora/code-standards` | `^1.1` em `require-dev` (o lock instala a 1.1.0) | PR do Dependabot |
| `elavora/api-framework` | `^2.0` em `require` (o lock instala a 2.0.0) | PR do Dependabot |
| PHP | `8.3`: `php:8.3-cli-alpine` no `Dockerfile`, `8.3-latest` na imagem de desenvolvimento, `>=8.3` em `require.php` | decisão do projeto, todos os lugares juntos |

Em qualquer arquivo entregue, a única referência válida à imagem de desenvolvimento é exatamente
`ghcr.io/elavora/php-dev:8.3-latest`. São inválidos:

- o nome local `elavora/php-dev:8.3`, a tag `ghcr.io/elavora/php-dev:8.3` e `:latest` sem a versão do PHP;
- qualquer tag fixa da imagem (`8.3-0.1.0`, `8.3-0.2.0`, `8.3-0.3.0`, ou com `v`, como `8.3-v0.3.0`). Ela
  congelaria a imagem no projeto, que nunca receberia as estáveis seguintes;
- qualquer referência ao `Elavora/.github` por nome de branch ou de tag, em vez de SHA.

### `composer.json`

Só as mudanças desta tarefa. O resto continua igual.

- `require.elavora/api-framework`: `^2.0`. Sai o `dev-main as 1.0.x-dev`.
- A chave `repositories` (repositório VCS do `api-framework`) é removida. O framework vem do Packagist.
- `require-dev.elavora/code-standards`: `^1.1`. O subcomando `lint` só existe a partir da 1.1.0, e o CI roda o lint
  com as dependências nas versões mínimas. Com `^1.0`, o CI instalaria a 1.0.0 e o `composer lint` falharia.
  O `friendsofphp/php-cs-fixer` **não** entra: o binário vem da imagem.
- `scripts.lint`: exatamente `elavora-standards lint`, sem argumentos. Sai o `@php tools/lint.php`, e o arquivo
  `tools/lint.php` é removido. O lint do pacote confere todo `.php` do projeto, menos `vendor/` e o que começa com
  ponto, então não precisa de lista de pastas. O `scripts.check` continua chamando `@lint`.
- `scripts.post-install-cmd` e `scripts.post-update-cmd`: cada um é exatamente
  `["Elavora\\CodeStandards\\Composer::sync"]`.
- `scripts.post-create-project-cmd` é só do skeleton (veja [`composer.json` do skeleton](#composerjson-do-skeleton)).
- O `composer.lock` é regenerado em container, porque `require` e `require-dev` mudaram. Na troca de `^1.0` para
  `^1.1`, a versão instalada continua a 1.1.0; muda só o `content-hash` (`composer update --lock`).

A sincronização é um método PHP, e não o comando `vendor/bin/elavora-standards sync`. O motivo é o build de
produção, que roda `composer install --no-dev`.

Sem o pacote instalado, o Composer só avisa
`Class Elavora\CodeStandards\Composer is not autoloadable, can not call post-install-cmd script` e termina com
código 0. Um comando em `vendor/bin` faria a instalação falhar. O `Dockerfile` não muda por causa disso.

### `phpunit.xml`

O pipeline central mede a cobertura com `XDEBUG_MODE=coverage composer test -- --coverage-clover=...`. Para isso:

- o `phpunit.xml` tem `<source><include>` com pelo menos um `<directory>`;
- todo `<directory>` dentro de `<source><include>` é `app` ou começa com `app/`. O código da aplicação fica em
  `app/`, e não em `src/`;
- o script `test` do `composer.json` chama o PHPUnit diretamente, para receber os argumentos extras.

Sem `<source>`, o PHPUnit avisa que não há filtro de cobertura. Com `failOnWarning="true"`, esse aviso reprova a
etapa de testes.

O `<source>` fica exatamente assim:

```xml
<source>
    <include>
        <directory>app</directory>
    </include>
    <exclude>
        <directory>app/Worker</directory>
    </exclude>
</source>
```

- `<include>` tem um único `<directory>`, `app`. Ele cobre o código que o projeto criar em qualquer pasta de `app/`.
  Listar pastas uma a uma quebraria quando uma delas ficasse vazia.
- `<exclude>` tem um único `<directory>`, `app/Worker`. O `tasks.php` usa o `elavora/api-queue-worker`, que não vem
  instalado, e nenhum teste o executa. É o mesmo motivo de o PHPStan ignorar `app/Worker`. Sem essa exclusão, a
  cobertura do projeto recém-criado ficaria abaixo da meta de 80%.
- O resto do `phpunit.xml` não muda.

### `app/` e `tests/`

O skeleton não entrega código de exemplo. Um exemplo copiado para o projeto vira código do projeto, que alguém
precisa apagar à mão. Fica só o que o projeto usa de verdade.

A checagem de saúde **não** é exemplo. O job `Imagem Docker` do `quality.yml` sobe a API e confere `/health` e
`/health/show`. Por isso o `HealthController`, o `HttpRoutes` e os testes dessas rotas ficam.

Conteúdo de `app/` depois desta mudança (nada além disto):

| Caminho | Conteúdo |
| --- | --- |
| `app/Attributes/.gitkeep` | vazio |
| `app/Contracts/.gitkeep` | vazio |
| `app/DataTypes/.gitkeep` | vazio |
| `app/Enums/.gitkeep` | vazio |
| `app/Http/Controller/HealthController.php` | sem mudança |
| `app/Http/HttpRoutes.php` | sem mudança: registra `GET /health` para `HealthController::show` |
| `app/Integrations/.gitkeep` | vazio |
| `app/Repositories/.gitkeep` | vazio |
| `app/Services/.gitkeep` | vazio |
| `app/Support/.gitkeep` | vazio |
| `app/Worker/tasks.php` | registro de tarefas vazio (abaixo) |

O `app/Worker/tasks.php` fica exatamente assim:

```php
<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\TaskRegistry;

// Registre aqui as tarefas da fila: o nome gravado na fila e o handler que a executa.
// Prefira nomes pesquisaveis e estaveis, como documents.process ou email.send.
return new TaskRegistry();
```

O `worker.php` não muda. Ele continua carregando o `tasks.php`.

Saem estes arquivos:

| Arquivo | Por quê |
| --- | --- |
| `app/Attributes/Permission.php` | exemplo de attribute |
| `app/DataTypes/ProjectCode.php` | exemplo de DataType |
| `app/Worker/ExampleTaskHandler.php` | exemplo de handler de fila |
| `tests/GuideExamplesTest.php` | testa só `Permission` e `ProjectCode` |
| `tests/ResponseableDataTypeTest.php` | testa só `ProjectCode` |

#### O que fica em `tests/`

Conteúdo de `tests/` (nada além disto):

| Arquivo | O que confere |
| --- | --- |
| `tests/ConventionRouteTest.php` | `/health/show`, pela convenção `/controller/action` |
| `tests/HealthEndpointTest.php` | `/health` responde `{"status":"ok"}` sem extensões opcionais |
| `tests/bootstrap.php` | carrega o autoload do Composer |

Os arquivos que ficam não mudam.

#### Testes movidos para `tests-skeleton/`

Estes dois testes saem de `tests/` e passam para `tests-skeleton/`, com o mesmo nome de arquivo e de classe. Eles
conferem arquivos do skeleton, e não a aplicação (veja
[Teste de arquivo do skeleton fica em `tests-skeleton/`](#teste-de-arquivo-do-skeleton-fica-em-tests-skeleton)):

| Antes | Depois |
| --- | --- |
| `tests/ApcuDockerConfigurationTest.php` | `tests-skeleton/ApcuDockerConfigurationTest.php` |
| `tests/ProjectConfigurationTest.php` | `tests-skeleton/ProjectConfigurationTest.php` |

Nenhuma conferência se perde. Cada teste continua conferindo exatamente o mesmo de antes:

**`ApcuDockerConfigurationTest`** (bloco do APCu):

- no `Dockerfile`, o trecho `if [ "$INSTALL_APCU" = "1" ]; then` existe e vem antes de
  `if [ "$INSTALL_MYSQL" = "1" ]; then`;
- o texto entre esses dois trechos (o bloco do APCu) contém `docker-php-ext-enable apcu` e
  `printf '%s\n' 'apc.enable_cli=1' > /usr/local/etc/php/conf.d/apcu-cli.ini`;
- o overlay `core/compose/apcu.yml` contém `INSTALL_APCU: 1` e `CACHE_DRIVER: apcu`.

**`ProjectConfigurationTest`** (lock, build e Compose):

- o `composer.lock` existe, é JSON válido e tem as chaves `content-hash`, `packages` e `packages-dev`;
- no `Dockerfile`, `COPY composer.json composer.lock ./` vem antes de
  `RUN composer install --no-interaction --no-progress --prefer-dist --no-dev --optimize-autoloader`, que vem antes
  de `COPY . .`;
- o `Dockerfile` não contém `composer update`;
- o `docker-compose.yml` contém `path: ${ELAVORA_ENV_FILE:-.env}` e `required: false` (o `.env` opcional do
  `env_file`).

O [`DockerfileTest`](#dockerfile) confere outra parte do `Dockerfile` (o estágio `dependencies` e a ausência do Git).
Ele não cobre o bloco do APCu, a ordem até o `COPY . .`, o `composer update` nem o `env_file` do Compose. Por isso os
dois testes são movidos inteiros, e não apagados.

Os dois rodam no job `entrega` do `skeleton-tests.yml`. Não precisam de Docker, então não entram no grupo `docker`.

A cobertura medida pelo CI do projeto não muda: ela conta só o código de `app/`, e esses testes não executavam nada
de `app/`.

#### Testes que repetiam o framework

Saem estes testes. Eles conferiam o `EnvironmentExtensions` do framework, e não o projeto (veja
[Teste do framework fica no framework](#teste-do-framework-fica-no-framework)):

| Arquivo | O que conferia | Onde fica coberto |
| --- | --- | --- |
| `tests/ExtensionConfigurationTest.php` | erro para `CACHE_DRIVER` e `DB_DRIVER` inválidos e para pacote de cache ou banco não instalado | `tests/EnvironmentExtensionsTest.php` do framework |
| `tests/LogExtensionConfigurationTest.php` | erro para `LOG_DRIVER` inválido e para pacote de log não instalado | `tests/EnvironmentExtensionsTest.php` do framework |
| `tests/RedisExtensionEnvironmentTest.php` | `REDIS_PASSWORD` e `REDIS_DATABASE` repassados ao cache e à fila | `tests/RedisExtensionEnvironmentTest.php` do framework |

Nenhum arquivo entregue (fora de `docs/manutencao-do-skeleton.md` e `tests-skeleton/`) cita
`ExtensionConfigurationTest`, `LogExtensionConfigurationTest` ou `RedisExtensionEnvironmentTest`.

A cobertura medida pelo CI não muda: ela conta só o código de `app/`, e esses testes não executavam nada de `app/`.

### `core/bootstrap/app.php`

O `app.php` entregue deixa o framework carregar as extensões selecionadas no `.env`. É o que o README promete:
"o bootstrap registra sozinho as extensões instaladas e selecionadas no `.env`".

Para isso, o `app.php` não passa o argumento `loadEnvironmentExtensions` ao `ApplicationBootstrap::create`, e vale o
padrão do framework. A regra confere o texto do arquivo: o `core/bootstrap/app.php` não contém
`loadEnvironmentExtensions` em nenhum lugar, nem em comentário.

- Passa: o `app.php` atual, que chama `ApplicationBootstrap::create(basePath: ..., extensions: ..., configure: ...)`.
- Falha: `ApplicationBootstrap::create(..., loadEnvironmentExtensions: false)`.
- Falha também: `loadEnvironmentExtensions: true`. O valor repete o padrão, e a regra é não citar o argumento.

Antes, os testes que repetiam o framework davam essa garantia de passagem. Agora ela é uma checagem de conteúdo em
`tests-skeleton/`, que não depende das mensagens de erro do framework.

Nenhum arquivo entregue (fora de `docs/manutencao-do-skeleton.md` e `tests-skeleton/`) cita `Permission.php`,
`ProjectCode`, `ExampleTaskHandler`, `example.ping` ou `Exemplos didaticos`. O README troca a seção
"Exemplos didaticos" por "O que já vem em `app/`".

### Regras de estilo

Conforme o contrato do `elavora/code-standards` (`docs/adocao.md` do pacote):

- **`.php-cs-fixer.dist.php`**, na raiz, versionado, com exatamente o stub que o pacote cria:

  ```php
  <?php

  require_once __DIR__ . '/vendor/elavora/code-standards/src/PhpCsFixer.php';

  return Elavora\CodeStandards\PhpCsFixer::config(__DIR__);
  ```

  O stub não lista pastas. A configuração do pacote formata todo `.php` do projeto, menos `vendor/` e arquivos e
  pastas que começam com ponto. O pacote nunca reescreve um stub que já existe.
- **`.editorconfig`**, na raiz, versionado. Ele tem exatamente um bloco, gerado pela sincronização, entre as linhas
  `# >>> elavora/code-standards >>>` e `# <<< elavora/code-standards <<<`. Não há nada fora do bloco. O arquivo é
  versionado para o editor funcionar antes do primeiro `composer install`.
- **`.gitignore`** ganha as linhas `/.elavora/` (pasta onde o CI baixa os scripts centrais) e `/.php-cs-fixer.php`
  (override pessoal).
- Na adoção, `composer install` sobre o repositório recém-clonado não altera nenhum dos dois arquivos. Isso é
  conferido à mão, no PR de adoção. Não é teste automático, porque um PR do Dependabot pode mudar o bloco (veja
  [Atualizações do Dependabot não quebram os testes](#atualizações-do-dependabot-não-quebram-os-testes)).

### `Dockerfile`

O estágio `dependencies` não instala o Git.

O Git só existia ali porque o framework vinha de uma branch, por repositório VCS, sem zip para baixar. Agora o
`elavora/api-framework` `^2.0` vem do Packagist. Com `--prefer-dist`, o Composer baixa o zip de cada pacote e não
precisa do Git.

O estágio `dependencies` fica exatamente assim:

```dockerfile
FROM runtime AS dependencies

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./

# Instala dependencias de producao a partir dos zips do Packagist (--prefer-dist).
# Pacotes de desenvolvimento ficam fora da imagem final.
RUN composer install --no-interaction --no-progress --prefer-dist --no-dev --optimize-autoloader
```

O que sai:

- a linha `RUN apk add --no-cache --virtual .composer-install-deps git`;
- o `&& apk del .composer-install-deps` no fim do `composer install`;
- o comentário sobre as branches coordenadas sem artefatos dist.

O resto do `Dockerfile` não muda: os estágios `composer` e `runtime`, o estágio final, o `EXPOSE` e o `CMD`.

Regras que os testes conferem:

- O `Dockerfile` não tem a palavra `git`, em nenhuma caixa (`git`, `Git`, `GIT`), nem o texto
  `.composer-install-deps`.
- No `composer.lock`, todo pacote da lista `packages` (as dependências de produção) tem `dist` com `type` igual a
  `zip`. Sem isso, o `--prefer-dist` precisaria clonar o pacote, e o build falharia sem o Git. A regra confere só o
  formato, não as versões. Assim, um PR do Dependabot que atualize o lock continua passando.
- O `docker build` do `Dockerfile` termina com sucesso (o teste de build de produção que já existe).
- A imagem do estágio `dependencies` (`docker build --target dependencies`) não tem o comando `git`:
  `command -v git` não encontra nada.

Exemplos:

- passa: o estágio `dependencies` acima, sem nenhuma linha `apk`;
- falha: `RUN apk add --no-cache --virtual .composer-install-deps git`;
- falha: `RUN apk add --no-cache git` em qualquer estágio;
- falha: um pacote em `packages` do `composer.lock` só com `source` (tipo `git`) e sem `dist`.

### `.devcontainer/devcontainer.json`

Segue o `examples/devcontainer.json` do `Elavora/devcontainers`:

| Campo | Valor |
| --- | --- |
| `name` | `Elavora API Skeleton - PHP 8.3` |
| `image` | o valor da tabela [Versões e referências](#versões-e-referências) |
| `remoteUser` | `vscode` |
| `forwardPorts` | `[8080]` |
| `postCreateCommand` | `git config --global --add safe.directory "$(pwd)" && composer install --no-interaction --prefer-dist` |
| `customizations.vscode.extensions` | `xdebug.php-debug`, `bmewburn.vscode-intelephense-client`, `junstyle.php-cs-fixer`, `EditorConfig.EditorConfig` |
| `customizations.vscode.settings` | `"php.validate.executablePath": "/usr/local/bin/php"`, `"php-cs-fixer.executablePath": "/usr/local/bin/php-cs-fixer"`, `"php-cs-fixer.config": ".php-cs-fixer.php;.php-cs-fixer.dist.php"`, `"php-cs-fixer.rules": "@PER-CS"`, `"php-cs-fixer.onsave": false` e `"[php]": { "editor.defaultFormatter": "junstyle.php-cs-fixer", "editor.formatOnSave": true }` |

O arquivo é JSON válido, sem comentários.

O rascunho que está hoje na working tree não atende: ele tem `"image": "elavora/php-dev:8.3"` e não tem as extensões
de formatação.

### `.github/workflows/quality.yml`

Substitui o `quality.yml` antigo, que chamava o SHA `1e0f42a` da branch abandonada `fix/centraliza-automacao`.

Segue o chamador de [qualidade.md](https://github.com/Elavora/.github/blob/main/docs/qualidade.md) do
`Elavora/.github` 1.3.0:

```yaml
name: Qualidade

on:
  pull_request:
    branches: [main]
  push:
    branches: [main]

permissions:
  contents: read

jobs:
  quality:
    permissions:
      contents: write
      pull-requests: write
      id-token: write
    uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0

  imagem:
    name: Imagem Docker
    # ...
```

- O job `quality` não tem `with` nem `secrets`.
- Sem `image`: a imagem do CI é o padrão do pipeline central, `8.3-latest`, que acompanha as estáveis. Passar
  `image` com uma tag fixa congelaria a imagem no projeto.
- Sem `automation_ref`: o pipeline central descobre a própria versão pelo OIDC, e o SHA aparece uma única vez, no
  `uses`. Assim o Dependabot troca o SHA e o comentário `# 1.3.0` na mesma linha. Com `automation_ref`, o
  Dependabot atualizaria só o `uses`, e as duas posições ficariam diferentes.
- Os padrões do pipeline central ficam ligados: cobertura com meta de 80% (só reporta), `composer audit` e
  dependências mínimas.
- Job local `imagem` (nome `Imagem Docker`), que não depende do job `quality`:
  - `runs-on: ubuntu-latest`, `permissions: contents: read`;
  - passos: checkout; `docker compose up --build --detach`; esperar `/health` responder (até 20 tentativas, uma
    por segundo); conferir que `/health` e `/health/show` respondem exatamente `{"status":"ok"}`; no fim, sempre,
    `docker compose down --volumes --remove-orphans`;
  - não roda `composer update`. O build reproduz o `composer.lock`.
- Actions de terceiros fixadas por SHA completo, com a versão em comentário
  (`actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7`).

O `quality.yml` **não** pode conter nenhum destes textos:

| Texto proibido | Motivo |
| --- | --- |
| `1e0f42a` | SHA da branch abandonada `fix/centraliza-automacao` |
| `@main` | referência que muda sem o projeto saber |
| `automation_ref` | duplicaria a versão, e o Dependabot só atualiza o `uses` |
| `fix/estabiliza-pacote` ou `dev-` | branches de desenvolvimento de pacotes |
| `PACKAGIST_WEBHOOK_URL` | secret do release do skeleton |
| `secrets: inherit` | o workflow de qualidade não precisa de secrets do repositório |
| `php-dev` | a imagem do CI é a do pipeline central. O projeto só a informa ao trocar a versão do PHP |

### `.github/dependabot.yml`

- `version: 2`.
- Dois itens em `updates`, nesta ordem: `package-ecosystem: "composer"` e `package-ecosystem: "github-actions"`,
  ambos com `directory: "/"`.
- Não há item `docker` nem `devcontainers`. A versão do PHP e a dos serviços são decisão do projeto, trocadas em
  todos os lugares juntos. A imagem de desenvolvimento se atualiza pela tag `8.3-latest`.
- Em cada item: agenda semanal (`interval: "weekly"`, `day: "monday"`, `time: "09:00"`,
  `timezone: "America/Sao_Paulo"`), `open-pull-requests-limit: 5` e `labels: ["dependencies"]`. A lista `labels`
  tem só `dependencies`.
- `commit-message` em cada item:
  - `composer`: `prefix: "chore"`, `prefix-development: "chore"` e `include: "scope"`. O Dependabot gera
    `chore(deps): ...` para `require` e `chore(deps-dev): ...` para `require-dev`;
  - `github-actions`: `prefix: "ci"` e `include: "scope"`. O Dependabot gera `ci(deps): ...`.
- Os prefixos não têm `:` nem espaço no fim. O Dependabot põe o escopo e os dois-pontos.

### Workflows que não existem mais

Estes arquivos são removidos: `.github/workflows/codeql.yml`, `.github/workflows/dependency-review.yml`,
`.github/workflows/packagist-sync.yml`, `.github/workflows/release.yml` e `.github/workflows/sync-labels.yml`.

Todos, menos o `packagist-sync.yml`, chamavam workflows da branch abandonada do `Elavora/.github`.

CodeQL e Dependency Review também exigem o GitHub Advanced Security em repositórios privados. Esse é o caso comum de
um projeto criado a partir do skeleton. Por isso eles não são entregues nem ficam no skeleton.

O release e as labels do skeleton continuam existindo, com outros nomes, só no repositório do skeleton.

Depois da criação do projeto, o único arquivo em `.github/workflows/` é o `quality.yml`. O `.github/` não tem
template de PR: o da organização vale para os repositórios dela.

## Referência do que é só do skeleton

### Arquivos só do skeleton

| Caminho | Tipo | O que é |
| --- | --- | --- |
| `.github/workflows/skeleton-release.yml` | arquivo | release por labels e Packagist |
| `.github/workflows/skeleton-sync-labels.yml` | arquivo | sincronização de labels |
| `.github/workflows/skeleton-tests.yml` | arquivo | testes da entrega, perfis e actionlint |
| `docs/manutencao-do-skeleton.md` | arquivo | esta página |
| `tests-skeleton/` | pasta | testes da entrega |
| `tools/skeleton-post-create.php` | arquivo | script de limpeza (apaga a si mesmo por último) |

Essa lista, nessa ordem, é a constante `SKELETON_ONLY` do script.

### Guia de migração removido

O skeleton não tem mais o guia de migração para projetos da estrutura 1.0. O arquivo foi apagado e saiu de quatro
lugares: a tabela acima, o bloco do [`.gitattributes`](#gitattributes), a constante `SKELETON_ONLY` do script e o
bloco "Só no repositório do skeleton" do `README.md`.

Por quê: o guia só servia a projetos criados com a estrutura 1.0, e nenhum projeto usa essa estrutura. Ele também
citava issues já tratadas.

Com o arquivo apagado, nenhum arquivo do repositório cita o guia. Por isso os testes da entrega não conferem mais se a
documentação entregue cita o guia.

### `.gitattributes`

O arquivo ganha, no fim, um bloco delimitado por duas linhas exatas:

```gitattributes
# skeleton:inicio
/.github/workflows/skeleton-release.yml export-ignore
/.github/workflows/skeleton-sync-labels.yml export-ignore
/.github/workflows/skeleton-tests.yml export-ignore
/docs/manutencao-do-skeleton.md export-ignore
/tests-skeleton export-ignore
# skeleton:fim
```

`tools/skeleton-post-create.php` **não** entra no bloco. Com `export-ignore`, ele não estaria no zip, e o
`post-create-project-cmd` falharia. O resto do `.gitattributes` não muda.

### `composer.json` do skeleton

Em `scripts`, a chave `post-create-project-cmd` tem exatamente `["@php tools/skeleton-post-create.php"]`.

Três chaves do `composer.json` são só do skeleton e saem na criação do projeto: `name` (`elavora/api-skeleton`),
`description` e `scripts.post-create-project-cmd`.

As outras mudanças (framework `^2.0`, saída do repositório VCS, `elavora/code-standards` e a sincronização) fazem
parte da entrega. Elas estão em [`composer.json`](#composerjson).

No `create-project`, o `post-install-cmd` (sincronização das regras de estilo) roda antes do
`post-create-project-cmd` (limpeza). Os dois são independentes, e a ordem entre eles não muda o resultado.

### `tools/skeleton-post-create.php`

Roda com `php tools/skeleton-post-create.php`, sem argumentos. A raiz do projeto é a pasta pai de `tools/`
(`dirname(__DIR__)`), e não o diretório atual. O script só lê e altera caminhos dentro dessa raiz.

Passos, nesta ordem:

1. **Remove os caminhos de `SKELETON_ONLY`**, exceto o próprio script. Pastas são removidas com todo o conteúdo.
   Caminho que não existe é ignorado, sem erro.
2. **README.md**: remove o trecho que vai da linha `<!-- skeleton:inicio -->` até a linha `<!-- skeleton:fim -->`,
   incluindo as duas linhas.
3. **.gitattributes**: remove o trecho que vai da linha `# skeleton:inicio` até a linha `# skeleton:fim`, incluindo
   as duas linhas.
4. **composer.json**: remove as chaves `name`, `description` e `scripts.post-create-project-cmd`. Se `scripts`
   ficar vazio, remove também `scripts`. Chave que não existe é ignorada, sem erro. Todas as outras chaves
   continuam, na mesma ordem. Se pelo menos uma chave saiu, o arquivo é regravado com indentação de 4 espaços, sem
   escapar `/` nem caracteres acentuados, e termina com uma quebra de linha. Se nenhuma saiu, o arquivo fica igual,
   byte a byte, e não há linha na saída.
   - `name` (`elavora/api-skeleton`) e `description` são do pacote do skeleton. O projeto criado não é esse
     pacote. Quem quiser publicar o projeto preenche os dois.
   - Num `type: project`, o `name` é opcional: `composer validate --strict --no-check-publish` passa sem ele.
5. **composer.lock**: o `content-hash` do lock depende do `name` do `composer.json`. Sem atualizar o hash, o
   `composer validate` reprova o lock com "The lock file is not up to date". Por isso, depois do passo 4:
   - o script calcula o hash do `composer.json` novo com o mesmo algoritmo do Composer (abaixo);
   - se o valor for diferente do `content-hash` atual, troca só esse valor no arquivo. Todo o resto do lock fica
     igual, byte a byte, inclusive a formatação e as quebras de linha;
   - se for igual, ou se o passo 4 não mudou o `composer.json`, o lock fica igual e não há linha na saída;
   - se o `composer.lock` não existir, o passo é ignorado, sem erro.
6. **Remove o próprio script.** Se `tools/` ficar vazia, ela também é removida. No skeleton atual ela fica vazia,
   porque o `tools/lint.php` não existe mais: o projeto criado não tem a pasta `tools/`.
7. Imprime na saída padrão uma linha por item removido ou alterado e termina com código 0. Os arquivos alterados
   aparecem como `alterado: composer.json` e `alterado: composer.lock`, uma vez cada, nesta ordem.

Regras dos trechos marcados (passos 2 e 3):

- O marcador é reconhecido quando a linha inteira, sem espaços nas pontas, é igual ao texto do marcador.
- As linhas fora do trecho ficam iguais, inclusive as quebras de linha (`\n` ou `\r\n`).
- Arquivo inexistente ou sem nenhum dos dois marcadores: o arquivo fica igual, sem aviso.
- Marcadores incompletos ou fora de ordem (só `inicio`, só `fim`, `fim` antes de `inicio`, ou mais de um par): o
  arquivo fica igual. O script escreve um aviso na saída de erro, com o nome do arquivo, e continua. O código de
  saída continua 0, porque o projeto já foi criado e o problema é só cosmético.

Algoritmo do `content-hash` (o mesmo do `Locker::getContentHash` do Composer 2):

1. Do `composer.json` decodificado como array, pegue só as chaves que existirem entre `name`, `version`, `require`,
   `require-dev`, `conflict`, `replace`, `provide`, `minimum-stability`, `prefer-stable`, `repositories` e `extra`.
2. Se existir `config.platform`, acrescente-o como `config` => [`platform` => valor].
3. Ordene as chaves do primeiro nível (`ksort`).
4. O hash é `md5(json_encode($dados))`, sem flags no `json_encode`.

O resultado tem que bater com o do Composer: depois do script, `composer validate --strict --no-check-publish`
passa no projeto criado. Conferido na imagem `ghcr.io/elavora/php-dev:8.3-latest` (Composer 2.10.3): sem o `name`,
o hash calculado assim é igual ao que `composer update --lock` grava, e o `validate` volta a passar.

Erros:

- Falha ao remover um caminho ou ao gravar um arquivo: mensagem na saída de erro, com o caminho, e código de saída 1.
  Os passos seguintes não rodam.
- `composer.json` ausente ou com JSON inválido: mensagem na saída de erro e código 1, sem alterar o arquivo.
- `composer.lock` existente, mas com JSON inválido ou sem a chave `content-hash` como texto: mensagem na saída de
  erro e código 1. O script confere o lock no início, junto com o `composer.json`, antes de alterar qualquer arquivo.

Exemplo de `README.md`, antes e depois:

```markdown
## Documentação

- [Guia de uso](docs/USO.md)

<!-- skeleton:inicio -->
## Só no repositório do skeleton

- [Manutenção do skeleton](docs/manutencao-do-skeleton.md)
<!-- skeleton:fim -->
```

```markdown
## Documentação

- [Guia de uso](docs/USO.md)

```

Rodar uma cópia do script de novo, sobre o projeto já limpo, não altera nenhum arquivo.

### Workflows só do skeleton

Os três usam a condição `github.repository == 'Elavora/api-skeleton'` em todos os jobs.

Os chamadores do `Elavora/.github` seguem o padrão da 1.3.0: SHA no `uses` com o comentário de versão, **sem**
`automation_ref`, e `id-token: write` no job chamador.

**`skeleton-release.yml`** (`name: Release`):

- gatilho `pull_request`, `types: [closed]`, `branches: [main]`;
- `permissions: contents: read` no workflow; no job, `contents: write`, `pull-requests: read` e `id-token: write`;
- `if: github.event.pull_request.merged == true && github.repository == 'Elavora/api-skeleton'`;
- `uses: Elavora/.github/.github/workflows/release.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0`;
- `with`: `package_name: elavora/api-skeleton` (`packagist` fica no padrão, `true`);
- `secrets`: `PACKAGIST_WEBHOOK_URL: ${{ secrets.PACKAGIST_WEBHOOK_URL }}`, sem `secrets: inherit`.

**`skeleton-sync-labels.yml`** (`name: Sincronizar labels`):

- gatilhos `schedule` (`cron: '17 3 * * 1'`) e `workflow_dispatch`;
- `permissions: contents: read` no workflow; no job, `contents: read`, `issues: write` e `id-token: write`;
- `if: github.repository == 'Elavora/api-skeleton'`;
- `uses: Elavora/.github/.github/workflows/sync-labels.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0`.

Nenhum workflow do repositório pode citar o SHA antigo `1e0f42a92a4aafc35522709c30bc4e83acab82f1`, o SHA da 1.0.0
(`6884d27a5dc5930dceee3fe39f0b112faf5c3034`) nem `automation_ref`. O Dependabot do skeleton atualiza esses SHAs
como atualiza o do `quality.yml`.

**`skeleton-tests.yml`** (`name: Testes do skeleton`): gatilhos `pull_request` (`branches: [main]`), `push`
(`branches: [main]`) e `workflow_dispatch`; `permissions: contents: read`. Jobs:

- `entrega`: roda na imagem `ghcr.io/elavora/php-dev:8.3-latest`, como root, porque o workspace do runner pertence a
  outro UID (`COMPOSER_ALLOW_SUPERUSER: '1'`). Roda `composer install --no-interaction --no-progress --prefer-dist` e
  `vendor/bin/phpunit -c tests-skeleton/phpunit.xml --exclude-group docker`. A imagem não tem Docker;
- `build-producao`: roda direto no `ubuntu-latest`, que já tem Docker, PHP e Composer. Roda
  `composer install --no-interaction --no-progress --prefer-dist` e
  `vendor/bin/phpunit -c tests-skeleton/phpunit.xml --group docker`;
- `perfis`: a matriz `application-smoke` que estava no antigo `quality.yml`, com as mesmas verificações. Nenhum
  perfil troca o `elavora/api-framework` por uma branch: o framework vem do `composer.lock`. Os perfis cujo pacote
  não aceite o framework 2 saem da matriz (veja [Riscos e pendências](#riscos-e-pendências));
- `actionlint`: roda o actionlint sobre `.github/workflows/`.

### O que `tests-skeleton/` verifica

Todas as checagens de referências seguem
[Atualizações do Dependabot não quebram os testes](#atualizações-do-dependabot-não-quebram-os-testes).

- Cada item da [Referência da entrega](#referência-da-entrega):
  - imagem `ghcr.io/elavora/php-dev:8.3-latest` igual no `devcontainer.json` e no job `entrega` do
    `skeleton-tests.yml`;
  - campos do `devcontainer.json`;
  - chamador e job `imagem` do `quality.yml`, e os textos proibidos;
  - `dependabot.yml`, com só os itens `composer` e `github-actions`;
  - stub do PHP-CS-Fixer, estrutura do `.editorconfig` e linhas do `.gitignore`;
  - `<source>` do `phpunit.xml`: só `app` no `<include>` e só `app/Worker` no `<exclude>`;
  - ausência dos workflows removidos;
  - conteúdo de `app/` igual à tabela de [`app/` e `tests/`](#app-e-tests), o `tasks.php` exato, a ausência dos
    arquivos de exemplo e de seus testes, e nenhuma citação aos exemplos nos arquivos entregues;
  - conteúdo de `tests/` igual à tabela de [O que fica em `tests/`](#o-que-fica-em-tests), a ausência dos
    [testes que repetiam o framework](#testes-que-repetiam-o-framework) e nenhuma citação a eles nos arquivos
    entregues;
  - os [testes movidos para `tests-skeleton/`](#testes-movidos-para-tests-skeleton): o bloco do APCu no
    `Dockerfile` e no overlay `core/compose/apcu.yml`, o `composer.lock` versionado, a ordem do build no
    `Dockerfile` sem `composer update` e o `env_file` opcional do `docker-compose.yml`;
  - o [`core/bootstrap/app.php`](#corebootstrapappphp) sem `loadEnvironmentExtensions`.
- O `composer.json`: `elavora/api-framework` com restrição que começa com `^2.`, sem `dev-` nem chave
  `repositories`; `elavora/code-standards` em `require-dev`, com restrição que começa com `^1.` e minor 1 ou
  maior; o script `lint` igual a `elavora-standards lint`; a ausência de `tools/lint.php`; os callbacks
  `Elavora\CodeStandards\Composer::sync`; e o `post-create-project-cmd`.
- **O build de produção continua funcionando** com o `elavora/code-standards` fora da instalação `--no-dev`:
  `docker build` do `Dockerfile` termina com sucesso, e a imagem gerada não tem `vendor/elavora/code-standards`.
  - O build também funciona sem o Git: a imagem do estágio `dependencies` não tem o comando `git` (veja
    [`Dockerfile`](#dockerfile)).
  - Os testes que precisam de Docker ficam no grupo PHPUnit `docker` (`#[Group('docker')]`).
  - Quando o comando `docker` não existir no ambiente, eles são pulados (`markTestSkipped`), não reprovados.
  - No CI, eles rodam no job `build-producao`.
- O `Dockerfile` sem `git` nem `.composer-install-deps`, e todo pacote de `packages` do `composer.lock` com `dist`
  do tipo `zip`. Esses testes não precisam de Docker e rodam no job `entrega`.
- Os workflows só do skeleton: condição de repositório, formato da referência ao `Elavora/.github` (SHA e
  comentário), `id-token: write`, ausência de `automation_ref` e dos SHAs antigos.
- A lista `SKELETON_ONLY`, o bloco do `.gitattributes` e a tabela desta página batem.
- O script de limpeza, executado numa cópia do repositório, em pasta temporária. Depois dele:
  - nenhum caminho de `SKELETON_ONLY` existe;
  - os marcadores sumiram do `README.md` e do `.gitattributes`;
  - o `composer.json` não tem `name`, `description` nem `post-create-project-cmd`;
  - `composer validate --strict --no-check-publish` passa na cópia (o `content-hash` do lock bate);
  - o `composer.lock` difere do original só no valor do `content-hash`;
  - também os casos: `composer.json` sem `name` ou sem `description` (só sai o que existe); `composer.json` já sem
    as três chaves (nenhum dos dois arquivos muda); `composer.lock` ausente (passo ignorado); `composer.lock` com JSON
    inválido ou sem `content-hash` (código 1 e nenhum arquivo alterado);
  - os arquivos da entrega continuam iguais.

  Também os casos de marcadores incompletos e de caminhos já ausentes.
- A documentação entregue (`README.md` fora do bloco do skeleton, `docs/USO.md` e `docs/ambiente-e-ci.md`) não cita
  `8.3-0.`, nenhuma sequência de 40 caracteres hexadecimais nem `manutencao-do-skeleton.md`.
- O `quality.yml` não tem `image` nem `php-dev`.

---

[README](../README.md) · Veja também: [Ambiente de desenvolvimento e CI](ambiente-e-ci.md)
