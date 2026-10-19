# Ambiente de desenvolvimento e CI

[README](../README.md) · Anterior: [Guia de uso](USO.md)

## Por que isto existe

Um projeto criado com `composer create-project elavora/api-skeleton` já vem com quatro peças:

1. um **Dev Container**, para todo mundo desenvolver no mesmo ambiente PHP sem instalar nada na máquina. Ele
   formata o código ao salvar;
2. um **CI de qualidade** que, em todo pull request, organiza o código, roda o lint, a análise estática e os testes
   e comenta no PR se está tudo certo;
3. as **regras de estilo** da Elavora (PER-CS e `.editorconfig`), que vêm do pacote `elavora/code-standards`,
   instalado como dependência de desenvolvimento. Editor e CI usam as mesmas regras;
4. uma configuração do **Dependabot**, que abre PRs quando as dependências e a automação ficam desatualizadas.

Os arquivos copiados na criação passam a ser **do seu projeto**. O skeleton não envia atualizações depois: o que
mudar nele não chega aos projetos que já existem. Por isso o skeleton copia só o mínimo de "cola" e deixa o que
evolui em lugares que se atualizam sozinhos:

- o comportamento da API vem do `elavora/api-framework`, um pacote Composer;
- as regras de estilo vêm do `elavora/code-standards`, também um pacote Composer;
- o pipeline de qualidade vem de um workflow reutilizável do `Elavora/.github`, chamado por SHA;
- as ferramentas de desenvolvimento (PHP-CS-Fixer, editorconfig-checker, Xdebug) vêm da imagem
  `ghcr.io/elavora/php-dev`, na tag `8.3-latest`. Essa tag acompanha as versões estáveis da imagem: muda quando sai
  uma estável e nunca aponta para uma beta.

O Dependabot do projeto propõe as atualizações dos pacotes e do pipeline. A imagem não precisa de PR: o CI usa a
última estável a cada execução, e o Dev Container, quando é reconstruído. A tabela de
[O que fica no projeto e como atualizar](#o-que-fica-no-projeto-e-como-atualizar) mostra, item por item, o que se
atualiza sozinho e o que depende de alguém mudar à mão.

## Visão geral

| Peça | Arquivos no projeto | O que faz |
| --- | --- | --- |
| Dev Container | `.devcontainer/devcontainer.json` | Abre o projeto na imagem `ghcr.io/elavora/php-dev`, com PHP 8.3, PHP-CS-Fixer, editorconfig-checker e formatação ao salvar |
| Qualidade | `.github/workflows/quality.yml` | No PR: formata (e commita a correção), confere `.editorconfig`, lint, PHPStan, `composer audit` e testes, e comenta o resultado. Também sobe a imagem Docker e confere `/health` |
| Regras de estilo | `elavora/code-standards` no `require-dev`, `.php-cs-fixer.dist.php` e `.editorconfig` | Padrão PER-CS e regras de editor da Elavora |
| Dependabot | `.github/dependabot.yml` | Uma vez por semana, propõe atualizações do Composer (inclusive das regras de estilo) e das GitHub Actions (inclusive do pipeline de qualidade) |

O Dev Container e o CI usam as mesmas ferramentas, porque os dois usam a tag `8.3-latest` da imagem
`ghcr.io/elavora/php-dev`, e as mesmas regras, porque os dois leem o `elavora/code-standards` travado no
`composer.lock`.

## Usar o Dev Container

Com o Docker Desktop e a extensão Dev Containers do VS Code instalados, abra o projeto e execute
**Dev Containers: Reopen in Container**. Na primeira abertura, o container roda `composer install`, incluindo as
dependências de desenvolvimento. Ao salvar um arquivo PHP, o editor o formata com as regras do projeto.

No terminal do editor:

```sh
composer test
composer check
php-cs-fixer fix
php -S 0.0.0.0:8080 -t public public/index.php
```

A API fica disponível na porta 8080 encaminhada pelo editor, incluindo `/health`. Se o Docker Compose já estiver
usando essa porta, pare-o com `docker compose down` no host ou use outra porta. O código fica montado no
container, então alterações em PHP não exigem reconstruir nada.

- Cobertura: `XDEBUG_MODE=coverage composer test -- --coverage-text`.
- Depuração com o listener PHP do VS Code:
  `XDEBUG_MODE=debug php -dxdebug.client_host=127.0.0.1 -dxdebug.start_with_request=yes -S 0.0.0.0:8080 -t public public/index.php`.
- Conferir o `.editorconfig` como o CI confere: `git ls-files -z | xargs -0 editorconfig-checker`.

A imagem de desenvolvimento não substitui o `Dockerfile`: o `Dockerfile` continua sendo a imagem de produção da
API. Detalhes da imagem estão em
[Elavora/devcontainers](https://github.com/Elavora/devcontainers/blob/main/docs/uso.md).

### Sem editor

Na pasta do projeto, no PowerShell, com a mesma imagem do `.devcontainer/devcontainer.json`:

```powershell
docker run --rm --pull always --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer check'
```

## O que acontece num pull request

O check principal aparece no PR como **`quality / Qualidade PHP`**. Ele vem do workflow reutilizável
`php-quality.yml` do `Elavora/.github`, na versão fixada no `quality.yml`:

1. **Formatação.** Roda o PHP-CS-Fixer com a configuração do `elavora/code-standards`. Se algum arquivo estava fora
   do padrão, commita a correção na branch do PR com a mensagem `style: organiza o código`. Dê `git pull` antes do
   próximo push.
2. **Estilo e análise.** Confere o `.editorconfig` (editorconfig-checker), roda `composer lint`, `composer analyse`
   (PHPStan) e `composer audit`, que aponta dependências com falhas de segurança conhecidas.
3. **Testes.** Roda `composer test` com as dependências nas versões mínimas permitidas pelo `composer.json` e mede
   a cobertura, com meta de 80%. Abaixo disso, o resultado aparece no comentário, mas não reprova.
4. **Comentário no PR.** Um comentário fixo, atualizado a cada execução, diz "testes rodaram ok" ou
   "testes com erro", com o resultado de cada etapa.

O `quality.yml` também tem um job do próprio projeto, **`Imagem Docker`**: ele sobe a API com
`docker compose up --build` e confere que `/health` e `/health/show` respondem `{"status":"ok"}`.

Casos em que o CI não commita a formatação e só avisa: PR do Dependabot, push na `main` e PR vindo de fork. PR de
fork falha logo no início, porque o GitHub não entrega a ele o token OIDC que o pipeline central usa para descobrir
a própria versão. Os repositórios da Elavora trabalham com branches internas.

O CI avisa, mas não bloqueia o merge. Se o seu time quiser exigir que ele passe, configure uma regra de proteção da
`main` no GitHub com o check `quality / Qualidade PHP`.

## O que fica no projeto e como atualizar

Cada item abaixo foi copiado (ou instalado) na criação do projeto. A coluna "Como atualiza" diz se ele muda
sozinho, por PR do Dependabot, ou se alguém precisa mudar à mão.

| Item | Onde fica | Como atualiza |
| --- | --- | --- |
| Framework (`elavora/api-framework`) e extensões oficiais | `composer.json` e `composer.lock` | **Dependabot** (`chore(deps): ...`). Inclui o bootstrap e os defaults das extensões |
| Ferramentas de teste e análise (PHPUnit, PHPStan) | `require-dev` | **Dependabot** (`chore(deps-dev): ...`) |
| Regras do PHP-CS-Fixer | `vendor/elavora/code-standards`, carregado pelo `.php-cs-fixer.dist.php` | **Dependabot** (`chore(deps-dev): bump elavora/code-standards ...`). O stub não muda: ele só carrega a configuração do pacote |
| Bloco da Elavora no `.editorconfig` | `.editorconfig`, entre `# >>> elavora/code-standards >>>` e `# <<< elavora/code-standards <<<` | **Semiautomático.** O `composer install` reescreve o bloco com a versão instalada do pacote. O Dependabot roda o Composer sem scripts, então o PR dele não traz o `.editorconfig` novo: rode `composer install` na branch e commite a diferença |
| Seções próprias no `.editorconfig` | `.editorconfig`, depois do bloco da Elavora | do projeto; a sincronização não mexe |
| Stub do PHP-CS-Fixer | `.php-cs-fixer.dist.php` | do projeto; o pacote cria uma vez e nunca reescreve |
| Pipeline de qualidade (formatação, lint, testes, cobertura, auditoria, comentário) | `Elavora/.github`, chamado pelo SHA no `uses` do `quality.yml` | **Dependabot** (`ci(deps): bump Elavora/.github ...`): troca o SHA e o comentário de versão na mesma linha |
| Imagem usada pelo CI de qualidade | definida pelo pipeline central; o `quality.yml` não escolhe imagem | **Automático.** O pipeline central usa a tag `8.3-latest`, então cada execução usa a última estável da imagem. Para outra versão do PHP, veja [Versão do PHP](#versão-do-php) |
| Actions de terceiros do job `Imagem Docker` | `quality.yml`, fixadas por SHA com a versão em comentário | **Dependabot** (`ci(deps): ...`) |
| Gatilhos, permissões e passos do job `Imagem Docker` | `quality.yml` | manual |
| Imagem do Dev Container | `image` do `.devcontainer/devcontainer.json`, na tag `8.3-latest` | **Automático ao reconstruir o container.** O Docker reaproveita a imagem já baixada: para pegar a estável nova, rode `docker pull ghcr.io/elavora/php-dev:8.3-latest` e depois **Dev Containers: Rebuild Container** |
| Extensões e configurações do VS Code | `.devcontainer/devcontainer.json` | manual |
| Configuração do Dependabot | `.github/dependabot.yml` | manual. Ele cuida só do Composer e das GitHub Actions. As imagens Docker ficam de fora de propósito (ver [Versão do PHP](#versão-do-php)) |
| Imagem do PHP de produção | `FROM php:...-cli-alpine` do `Dockerfile` | correções dentro da mesma versão do PHP chegam ao reconstruir com `docker compose build --pull`. Trocar a versão do PHP é manual: [Versão do PHP](#versão-do-php) |
| Imagem do Composer usada no build | `FROM composer:2` do `Dockerfile` | correções dentro do Composer 2 chegam ao reconstruir com `docker compose build --pull` |
| Imagens dos serviços (Redis, MySQL, PostgreSQL) | `image` de cada arquivo em `core/compose/` | correções dentro da mesma versão chegam com `docker compose ... pull`. Trocar a versão é manual: [Versões dos serviços](#versões-dos-serviços) |
| Lint portátil, PHPStan e PHPUnit | `tools/lint.php`, `phpstan.neon.dist`, `phpunit.xml` | manual (pendente: dúvida 6) |
| Entradas e bootstrap | `core/bootstrap/app.php`, `core/config/extensions.php`, `public/index.php`, `worker.php` | do projeto. São finos: o comportamento vem do framework, pelo Composer |
| Código, testes e documentação | `app/`, `tests/`, `docs/`, `README.md` | do projeto |
| Template de PR | não é copiado | repositórios da organização Elavora herdam o template do `Elavora/.github`. Fora da organização, crie o seu se quiser |

Faça cada atualização manual num PR próprio e confira o CI.

### Versão do PHP

O projeto roda numa única versão do PHP, igual no desenvolvimento, no CI e em produção. Por isso o Dependabot não
propõe imagens novas do PHP: uma versão nova num lugar só deixaria o projeto testado numa versão e rodando em
outra. Trocar a versão (por exemplo, de 8.3 para 8.4) é uma decisão única, feita num só PR que muda todos estes
lugares juntos:

| Lugar | O que muda |
| --- | --- |
| `Dockerfile` | a tag do `FROM php:8.3-cli-alpine` (produção) |
| `.devcontainer/devcontainer.json` | o `image` (`ghcr.io/elavora/php-dev:8.3-latest`) e o `name` |
| `.github/workflows/quality.yml` | o job `quality` passa a informar a imagem, porque sem ela o pipeline central usa a do PHP 8.3: um `with:` com `image: ghcr.io/elavora/php-dev:8.4-latest` |
| `composer.json` | a versão mínima em `require.php` (`>=8.3`) |
| `README.md` e `docs/USO.md` | a linha de requisitos (PHP `>=8.3`) |

Antes de abrir o PR, confira:

1. a imagem `ghcr.io/elavora/php-dev` tem a tag `-latest` da versão nova (veja
   [Elavora/devcontainers](https://github.com/Elavora/devcontainers/blob/main/docs/uso.md));
2. o framework, as extensões e as outras dependências aceitam a versão nova. No Dev Container reconstruído,
   `composer install` aponta o pacote que não aceita: atualize-o com `composer update <pacote>` ou espere uma
   versão compatível;
3. `composer check` passa no Dev Container, e o CI passa no PR;
4. `git grep -n "8\.3"` não encontra mais a versão antiga nesses lugares.

### Versões dos serviços

Redis, MySQL e PostgreSQL seguem a mesma lógica: cada serviço tem uma versão escolhida pelo projeto, a mesma no
desenvolvimento e no ambiente onde a API roda. A de desenvolvimento fica no `image` do arquivo do serviço em
`core/compose/` (`redis.yml`, `mysql.yml`, `postgresql.yml`). Para trocar, mude esse `image` e o servidor de
produção juntos, num PR próprio.

Em banco de dados, trocar de versão maior pode exigir migrar os dados: o volume criado por uma versão pode não abrir
na outra. No desenvolvimento local, o caminho simples é recriar o volume com
`docker compose -f docker-compose.yml -f core/compose/<serviço>.yml down --volumes`, que **apaga os dados locais**.

### PRs do Dependabot

O Dependabot só permite configurar o **prefixo** da mensagem, não o texto. Os commits e os títulos dos PRs dele
ficam assim:

| Atualização | Exemplo de mensagem |
| --- | --- |
| dependência de produção do Composer | `chore(deps): bump elavora/api-framework from 2.0.0 to 2.1.0` |
| dependência de desenvolvimento do Composer | `chore(deps-dev): bump elavora/code-standards from 1.0.0 to 1.1.0` |
| GitHub Actions e pipeline central | `ci(deps): bump Elavora/.github from 1.2.0 to 1.3.0` |

O prefixo segue o Conventional Commits. O resto da frase (`bump ... from ... to ...`) é gerado pelo Dependabot em
inglês e não pode ser traduzido. Ao integrar o PR com squash, você pode reescrever a mensagem em português.

Os PRs do Dependabot recebem só a label `dependencies`. Sem a configuração `labels`, o Dependabot também criaria
labels com o nome do ecossistema (`php`, `github_actions`).

Num PR do Dependabot, o CI não commita a formatação (o token do Dependabot é só leitura) e só avisa no resumo do
job. Se uma versão nova do `elavora/code-standards` mudar a formatação ou o `.editorconfig`, rode na branch do PR
`composer install` e `php-cs-fixer fix` e commite o resultado.

## Commits

O padrão de commits da Elavora é o Conventional Commits, com a descrição em português do Brasil
(`fix: corrige validação de CPF`). A correção automática de formatação do CI segue esse padrão
(`style: organiza o código`).

## Release do seu projeto

O projeto criado não herda o release automático da Elavora (tags por labels, betas e Packagist). Esse fluxo
existe para os pacotes da organização. Se o seu projeto precisar de versões, escolha o processo que fizer sentido
para ele. O fluxo da Elavora está descrito em
[Elavora/.github](https://github.com/Elavora/.github/blob/main/docs/release.md), caso queira tomá-lo como base.

---

[README](../README.md) · Anterior: [Guia de uso](USO.md)
