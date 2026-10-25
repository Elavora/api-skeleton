# Ambiente de desenvolvimento e CI

[README](../README.md) · Anterior: [Guia de uso](USO.md)

## Visão geral

Um projeto criado com `composer create-project elavora/api-skeleton` já vem com quatro peças:

| Peça | Arquivos no projeto | O que faz |
| --- | --- | --- |
| Dev Container | `.devcontainer/devcontainer.json` | Abre o projeto na imagem `ghcr.io/elavora/php-dev`, com PHP 8.3, PHP-CS-Fixer, editorconfig-checker e formatação ao salvar |
| Qualidade | `.github/workflows/quality.yml` | No PR: formata (e commita a correção), confere o `.editorconfig`, roda lint, PHPStan, `composer audit` e testes, e comenta o resultado. Também sobe a imagem Docker e confere `/health` |
| Regras de estilo | `elavora/code-standards` no `require-dev`, `.php-cs-fixer.dist.php` e `.editorconfig` | Padrão PER-CS e regras de editor da Elavora |
| Dependabot | `.github/dependabot.yml` | Uma vez por semana, propõe atualizações do Composer e das GitHub Actions |

Esses arquivos passam a ser do seu projeto. Mude-os quando o projeto precisar.

O Dev Container e o CI usam a mesma imagem, `ghcr.io/elavora/php-dev` na tag `8.3-latest`, e as mesmas regras de
estilo, as do `elavora/code-standards` travado no `composer.lock`.

## Usar o Dev Container

Instale o Docker Desktop e a extensão Dev Containers do VS Code. Abra o projeto e execute
**Dev Containers: Reopen in Container**.

Na primeira abertura, o container roda `composer install`, com as dependências de desenvolvimento. Ao salvar um
arquivo PHP, o editor o formata com as regras do projeto.

No terminal do editor:

```sh
composer test
composer check
php-cs-fixer fix
php -S 0.0.0.0:8080 -t public public/index.php
```

A API fica disponível na porta 8080, encaminhada pelo editor. Se o Docker Compose já estiver usando essa porta,
pare-o com `docker compose down` no host ou use outra porta.

O código fica montado no container. Alterações em PHP não exigem reconstruir nada.

- Cobertura: `XDEBUG_MODE=coverage composer test -- --coverage-text`.
- Depuração com o listener PHP do VS Code:
  `XDEBUG_MODE=debug php -dxdebug.client_host=127.0.0.1 -dxdebug.start_with_request=yes -S 0.0.0.0:8080 -t public public/index.php`.
- Conferir o `.editorconfig` como o CI confere: `git ls-files -z | xargs -0 editorconfig-checker`.

A imagem de desenvolvimento não substitui o `Dockerfile`. O `Dockerfile` continua sendo a imagem de produção da API.
Detalhes da imagem estão em [Elavora/devcontainers](https://github.com/Elavora/devcontainers/blob/main/docs/uso.md).

### Sem editor

Na pasta do projeto, no PowerShell, com a mesma imagem do `.devcontainer/devcontainer.json`:

```powershell
docker run --rm --pull always --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer check'
```

## O que acontece num pull request

O check **`quality / Qualidade PHP`** vem do pipeline central do `Elavora/.github`: formata o código, roda lint,
análise, testes com cobertura e `composer audit`, e comenta o resultado no PR. O que cada etapa faz está em
[qualidade.md](https://github.com/Elavora/.github/blob/main/docs/qualidade.md).

O `quality.yml` também tem um job do próprio projeto, **`Imagem Docker`**. Ele sobe a API com
`docker compose up --build` e confere que `/health` e `/health/show` respondem `{"status":"ok"}`.

O CI avisa, mas não bloqueia o merge. Para exigir que ele passe, configure uma regra de proteção da `main` no GitHub
com o check `quality / Qualidade PHP`.

## Dependabot

O `.github/dependabot.yml` cuida só do Composer e das GitHub Actions. As imagens Docker ficam de fora: a versão do
PHP e a de cada serviço são uma decisão do projeto, a mesma no `Dockerfile`, no Dev Container e no CI.

O Dependabot só permite configurar o **prefixo** da mensagem, não o texto. Os commits e os títulos dos PRs dele
ficam assim:

| Atualização | Exemplo de mensagem |
| --- | --- |
| dependência de produção do Composer | `chore(deps): bump elavora/api-framework from 2.0.0 to 2.1.0` |
| dependência de desenvolvimento do Composer | `chore(deps-dev): bump elavora/code-standards from 1.0.0 to 1.1.0` |
| GitHub Actions e pipeline central | `ci(deps): bump Elavora/.github from 1.3.0 to 1.4.0` |

O prefixo segue o Conventional Commits. O resto da frase (`bump ... from ... to ...`) é gerado pelo Dependabot em
inglês e não pode ser traduzido. Ao integrar o PR com squash, você pode reescrever a mensagem em português.

Os PRs do Dependabot recebem só a label `dependencies`.

Num PR do Dependabot, o CI não commita a formatação, porque o token do Dependabot é só leitura. Ele só avisa no
resumo do job.

## Commits

O padrão de commits da Elavora é o Conventional Commits, com a descrição em português do Brasil
(`fix: corrige validação de CPF`). A correção automática de formatação do CI segue esse padrão
(`style: organiza o código`).

## Release do seu projeto

O projeto não herda o release automático da Elavora; se quiser um processo parecido, veja o
[fluxo de release do Elavora/.github](https://github.com/Elavora/.github/blob/main/docs/release.md).

---

[README](../README.md) · Anterior: [Guia de uso](USO.md)
