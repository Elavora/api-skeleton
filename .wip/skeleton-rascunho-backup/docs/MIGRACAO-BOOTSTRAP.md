# Migração para bootstrap compartilhado

[README](../README.md) · Veja também: [Ambiente de desenvolvimento e CI](ambiente-e-ci.md)

Esta página existe só no repositório `Elavora/api-skeleton`. Ela é para quem tem um projeto criado com a estrutura
1.0 do skeleton e não vai para os projetos criados a partir de agora, que já nascem com o bootstrap compartilhado.

O bootstrap compartilhado (`ApplicationBootstrap` e `EnvironmentExtensions`) faz parte do
`elavora/api-framework` a partir da versão `2.0.0`. A tag `1.0.0` do framework não tem essas classes. O skeleton
exige `elavora/api-framework: ^2.0`, instalado do Packagist, sem repositório VCS nem branch.

## Projetos existentes (estrutura 1.0)

1. Crie uma branch e registre em commit o estado atual, incluindo composer.lock.
2. Compare core/bootstrap/app.php e core/config/extensions.php com suas versões
   locais; preserve registros de extensões próprias, rotas e middlewares.
3. Atualize a dependência elavora/api-framework para ^2.0
   e execute composer update elavora/api-framework --with-dependencies em container.
4. Use o novo core/bootstrap/app.php como referência. Mantenha em
   core/config/extensions.php somente instâncias de extensões próprias ou
   overrides. Os módulos oficiais selecionados pelo ambiente já são registrados.
5. Mantenha registros adicionais no callback configure. Quando for necessário
   substituir inteiramente a configuração oficial, use
   loadEnvironmentExtensions: false e forneça a lista completa de extensões.
6. Execute composer validate --strict --no-check-publish e composer check em
   container. Valide /health, /health/show e os perfis opcionais usados pelo produto.
   Para filas, valide também php worker.php --once --queue=bootstrap-review com api-queue-worker instalado.

O framework registra primeiro os módulos oficiais, depois as extensões locais e
por último executa configure. Overrides devem respeitar essa ordem. basePath deve
apontar para a raiz do projeto, para manter os logs fora de vendor/.

Application::create() continua funcional; projetos com bootstrap antigo podem
migrar gradualmente. APP_DEBUG e os defaults dos drivers permanecem iguais.
A fila Redis continua sendo ativada pela presença do pacote, como anteriormente.

## Arquivos e propriedade

| Arquivos | Responsabilidade após criar o projeto |
| --- | --- |
| vendor/elavora/api-framework/ | Composer; não editar diretamente |
| app/ | Projeto; preservar integralmente |
| core/bootstrap/app.php e core/config/extensions.php | Projeto; adaptar uma vez e preservar customizações |
| public/index.php e worker.php | Entradas locais; delegam aos pacotes |
| .env, Docker e Compose | Projeto; alterações revisadas manualmente |
| CI, Dev Container e regras de estilo | Projeto; veja [o que se atualiza sozinho](ambiente-e-ci.md#o-que-fica-no-projeto-e-como-atualizar) |

As atualizações futuras do comportamento extraído chegam por Composer. Isso não
atualiza Docker/CI nem implementa manifesto, diagnóstico, receitas ou merge de
arquivos. A issue #25 permanece separada; esta mudança atende a extração da #28.
A documentação contribui para #30, sem declarar uma matriz de releases ainda não testadas.

## Rollback

Reverta juntos o commit de migração, composer.json e composer.lock. Recrie a imagem
com composer install usando o lock restaurado. Não restaure arquivos app/ ou .env
por cópia de template; preserve as alterações do produto.

---

[README](../README.md) · Veja também: [Ambiente de desenvolvimento e CI](ambiente-e-ci.md)
