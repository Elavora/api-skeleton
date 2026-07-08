# Guia de uso

Projeto inicial para APIs HTTP criadas com `elavora/api-framework`.

## Instalacao

```bash
composer create-project elavora/api-skeleton minha-api
```

## Quando usar

- Iniciar uma nova API baseada no framework Elavora.
- Manter estrutura previsivel de `app/`, `core/`, `public/` e `tests`.
- Testar extensoes opcionais por variaveis de ambiente e overlays Docker.

## Exemplo rapido

```bash
composer create-project elavora/api-skeleton minha-api
cd minha-api
cp .env.example .env
docker compose up --build
curl http://localhost:8080/health
```

## Principais pontos de entrada

- Consulte `composer.json` e `README.md` para os pontos de entrada do pacote.

## Dependencias de runtime

- `elavora/api-framework` `^0.3.1`

## Validacao no projeto consumidor

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use container:

```bash
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-skeleton" composer:2 composer validate --strict --no-check-publish
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-skeleton" composer:2 sh -lc "find . \\( -path ./.git -o -path ./vendor \\) -prune -o -name '*.php' -print0 | xargs -0 -r -n1 php -l"
```

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.