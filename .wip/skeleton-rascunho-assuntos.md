# api-skeleton: partes do rascunho fora do PR 1 (adoção da base)

Backup integral do rascunho original (antes da divisão):
`C:\Users\Felipe\AppData\Local\Temp\claude\D--Felipe-Dev\e84cf1a1-1ebd-5e09-b19a-e7ce011e1923\scratchpad\skeleton-rascunho-backup\`
(README.md, docs/*.md, rascunho.diff com o diff de README/USO/MIGRACAO contra a main).
O `.devcontainer/devcontainer.json` não versionado continua na working tree (é do dev, PR 1).

Cada seção abaixo é um assunto, com branch e PR próprios. O texto entre aspas é o que estava no rascunho.

---

## PR 2: remover os exemplos de `app/` e os testes deles

Dúvida 8 do rascunho ("exemplos didáticos de app/ fora da entrega").

Trechos afetados:

- README.md, seção "### Exemplos didaticos" (lista `Permission.php`, `ProjectCode.php`, `HealthController.php`,
  `HttpRoutes.php`, `ExampleTaskHandler.php`). O rascunho só trocou o título para "Exemplos didáticos".
- `phpunit.xml`: o `<source>` hoje lista `app/Attributes`, `app/DataTypes` e `app/Http`. Se os exemplos saírem,
  essas pastas podem ficar só com `.gitkeep`. Trocar por `<directory>app</directory>` mantém a regra do PR 1
  ("`<source>` com pelo menos uma pasta, todas dentro de `app/`") e não depende de pasta existir.
  Lição do framework: sem `<source>`, o pipeline com cobertura falha.
- Conferir `/health` e `/health/show`: `HealthController` é exemplo, mas o CI (`Imagem Docker`) e os testes de
  entrega dependem dessas rotas. Decidir se o controller de health fica.

## PR 3: remover o `git` do Dockerfile

Dúvida 10 do rascunho. Hoje o estágio `dependencies` do `Dockerfile` tem:

```
# Instala dependencias de producao. Pacotes de desenvolvimento ficam fora da
# imagem final. O Git existe apenas neste estagio enquanto as branches
# coordenadas ainda nao possuem artefatos dist.
RUN apk add --no-cache --virtual .composer-install-deps git
```

Com o framework `^2.0` do Packagist (PR 1), o `--prefer-dist` não precisa de Git. Documentar a mudança e o teste
(build de produção continua passando sem `git`).

## PR 4: limpeza da doc

### 4a. MIGRACAO só no skeleton (dúvida 4, já respondida: sim)

Do rascunho:

- MIGRACAO-BOOTSTRAP.md, abertura: "Esta página existe só no repositório `Elavora/api-skeleton`. Ela é para quem tem
  um projeto criado com a estrutura 1.0 do skeleton e não vai para os projetos criados a partir de agora, que já
  nascem com o bootstrap compartilhado."
- README.md, bloco skeleton: "- [Migração para bootstrap compartilhado](docs/MIGRACAO-BOOTSTRAP.md): como adaptar um
  projeto criado com a estrutura 1.0 do skeleton."
- README.md, "Atualização do bootstrap" sem o link para o guia: "A inicialização e os defaults das extensões
  oficiais pertencem ao framework e recebem atualizações via Composer. `core/bootstrap/app.php` e
  `core/config/extensions.php` só guardam o que é do projeto: extensões próprias, rotas, middlewares e overrides."
- manutencao-do-skeleton.md: `docs/MIGRACAO-BOOTSTRAP.md` na tabela "Só do skeleton", na tabela "Arquivos só do
  skeleton" (entre `skeleton-tests.yml` e `manutencao-do-skeleton.md`), no bloco do `.gitattributes`
  (`/docs/MIGRACAO-BOOTSTRAP.md export-ignore`, mesma posição) e na lista `SKELETON_ONLY`.
- Parágrafo: "O guia de migração é só do skeleton porque um projeto criado a partir de agora já nasce com o bootstrap
  compartilhado: para ele, o guia seria um arquivo a mais, congelado e sem uso. Quem precisa dele tem um projeto
  antigo e o lê no repositório do skeleton, pelo bloco "Só no repositório do skeleton" do `README.md`."
- tests-skeleton: a doc entregue não cita `MIGRACAO-BOOTSTRAP.md`.
- MIGRACAO: os links de navegação para `ambiente-e-ci.md` deixam de valer de fora (a página não é entregue, mas o
  link continua válido no repositório do skeleton).

### 4b. CI resumido a 2 linhas e um link (dúvida 7)

Seção "## O que acontece num pull request" de `docs/ambiente-e-ci.md` (PR 1 entrega a versão longa). Resumir a duas
linhas e linkar `https://github.com/Elavora/.github/blob/main/docs/qualidade.md`. Manter só o que é do projeto:
o job `Imagem Docker` e a sugestão de regra de proteção com o check `quality / Qualidade PHP`.

### 4c. Seção de release em 1 frase (dúvida 11)

Seção "## Release do seu projeto" de `docs/ambiente-e-ci.md`, hoje com 4 frases (texto do rascunho mantido no PR 1).
Reduzir a uma frase com o link para `release.md` do `Elavora/.github`.

### 4d. Tabela de extensões só com as que têm overlay, mais link para o Packagist (dúvida 12)

README.md, "## Extensões opcionais": hoje 9 linhas. Ficam só as que têm overlay em `core/compose/` (apcu, redis
para cache e fila, mysql, postgresql) e um link para a lista de pacotes `elavora/` no Packagist.
Atenção: no momento da divisão, nenhum pacote de extensão tem versão que aceite `elavora/api-framework` 2
(tags e `fix/estabiliza-pacote` exigem `^0.3.1` ou `^1.0`). Os comandos `composer require elavora/api-*` do README
falham num projeto com o framework 2 até essas versões saírem.

### 4e. Acentos e ortografia nos arquivos antigos

O rascunho corrigiu acentos que não são da adoção. Ficam para a limpeza:

- README.md: "extensoes proprias" (árvore de pastas), "### Exemplos didaticos", "## Atualizacao do bootstrap" e o
  parágrafo dela ("inicializacao", "extensoes", "atualizacoes", "migracao", "customizacoes").
- docs/USO.md: "## Instalacao", "Instalacoes", "versoes", "servico", "e opcional", "padrao", "automacao",
  "servico iniciado", "e um alias explicito", "convencao", "publica de instancia", "nivel 8", "analise estatica",
  "nao estiver", "dependencias".
- docs/MIGRACAO-BOOTSTRAP.md: título "Migracao", "versoes", "extensoes", "referencia", "instancias", "modulos",
  "ja sao", "necessario", "configuracao", "forneca", "tambem", "ultimo", "presenca", "apos", "nao editar",
  "customizacoes", "alteracoes", "atualizacoes", "extraido", "nao", "diagnostico", "mudanca", "extracao",
  "documentacao", "migracao", "copia".

## PR 5: o post-create remove `name`/`description` do `composer.json`

Dúvida 5. No rascunho, passo 4 do `tools/skeleton-post-create.php` terminava com "(pendente: dúvida 5)". O PR 1
especifica só a remoção de `scripts.post-create-project-cmd`. O PR 5 acrescenta a remoção de `name`
(`elavora/api-skeleton`) e `description` ("Projeto base para APIs construidas com o framework Elavora.") e o teste
correspondente. Conferir que `composer validate --strict --no-check-publish` continua passando sem `name` num
`type: project`.

## PR 6: trocar `tools/lint.php` por `elavora-standards lint` com `^1.1`

Dúvida 6. No rascunho, a linha da tabela "O que fica no projeto": "| Lint portátil, PHPStan e PHPUnit |
`tools/lint.php`, `phpstan.neon.dist`, `phpunit.xml` | manual (pendente: dúvida 6) |".

- `elavora/code-standards` passa de `^1.0` (PR 1) para `^1.1` no `require-dev`.
- `scripts.lint`: de `@php tools/lint.php` para `elavora-standards lint` (ver `docs/lint.md` do code-standards
  1.1.0, inclusive a migração do `tools/lint.php`).
- `tools/lint.php` sai. Com isso, `tools/` fica só com `tools/skeleton-post-create.php`, que apaga a si mesmo e a
  pasta vazia (regra já especificada no PR 1).
- docs/USO.md: o parágrafo "O lint cobre `app/`, `core/`, `public/`, `tests/` e `worker.php` sem depender de `find`,
  `xargs` ou Bash" muda para o comportamento do `elavora-standards lint`.
- README.md, "Qualidade e build": "`composer check` executa lint portátil, PHPStan nível 8 e PHPUnit".
- Testes de entrega que exigem `^1.0` aceitam `^1.` (o PR 1 já especifica a checagem por major).

## Sem PR definido

- Dúvida 9 do rascunho: mover os testes de infraestrutura de `tests/` (Dockerfile e overlays, por exemplo
  `ApcuDockerConfigurationTest.php`, `RedisExtensionEnvironmentTest.php`) para `tests-skeleton/`. O usuário não a
  incluiu em nenhum PR. O PR 1 não move testes existentes.
