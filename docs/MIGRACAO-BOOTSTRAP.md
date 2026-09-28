# Migracao para bootstrap compartilhado

Esta integracao depende do PR coordenado do api-framework. Enquanto estiver em
revisao, a dependencia aponta para a branch do framework e o lock fixa seu commit.
Antes de publicar o Skeleton, publique uma release do framework contendo
ApplicationBootstrap e EnvironmentExtensions, substitua a dependencia de revisao
pela constraint estavel correspondente e regenere composer.lock em container.
Nao presumir que a tag 1.0.0 ja contem estas classes.

## Projetos existentes (estrutura 1.0)

1. Crie uma branch e registre em commit o estado atual, incluindo composer.lock.
2. Compare core/bootstrap/app.php e core/config/extensions.php com suas versoes
   locais; preserve registros de extensoes proprias, rotas e middlewares.
3. Atualize a dependencia elavora/api-framework para a release que contem esta API
   e execute composer update elavora/api-framework --with-dependencies em container.
4. Use o novo core/bootstrap/app.php como referencia. Mantenha em
   core/config/extensions.php somente instancias de extensoes proprias ou
   overrides. Os modulos oficiais selecionados pelo ambiente ja sao registrados.
5. Mantenha registros adicionais no callback configure. Quando for necessario
   substituir inteiramente a configuracao oficial, use
   loadEnvironmentExtensions: false e forneca a lista completa de extensoes.
6. Execute composer validate --strict --no-check-publish e composer check em
   container. Valide /health, /health/show e os perfis opcionais usados pelo produto.
   Para filas, valide tambem php worker.php --once --queue=bootstrap-review com api-queue-worker instalado.

O framework registra primeiro os modulos oficiais, depois as extensoes locais e
por ultimo executa configure. Overrides devem respeitar essa ordem. basePath deve
apontar para a raiz do projeto, para manter os logs fora de vendor/.

Application::create() continua funcional; projetos com bootstrap antigo podem
migrar gradualmente. APP_DEBUG e os defaults dos drivers permanecem iguais.
A fila Redis continua sendo ativada pela presenca do pacote, como anteriormente.

## Arquivos e propriedade

| Arquivos | Responsabilidade apos criar o projeto |
| --- | --- |
| vendor/elavora/api-framework/ | Composer; nao editar diretamente |
| app/ | Projeto; preservar integralmente |
| core/bootstrap/app.php e core/config/extensions.php | Projeto; adaptar uma vez e preservar customizacoes |
| public/index.php e worker.php | Entradas locais; delegam aos pacotes |
| .env, Docker, Compose e CI | Projeto; alteracoes revisadas manualmente |

As atualizacoes futuras do comportamento extraido chegam por Composer. Isso nao
atualiza Docker/CI nem implementa manifesto, diagnostico, receitas ou merge de
arquivos. A issue #25 permanece separada; esta mudanca atende a extracao da #28.
A documentacao contribui para #30, sem declarar uma matriz de releases ainda nao testadas.

## Rollback

Reverta juntos o commit de migracao, composer.json e composer.lock. Recrie a imagem
com composer install usando o lock restaurado. Nao restaure arquivos app/ ou .env
por copia de template; preserve as alteracoes do produto.
