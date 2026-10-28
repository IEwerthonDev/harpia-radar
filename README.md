# Harpia Radar

[Read in English](README.en.md)

A saúde dos projetos de código aberto de que o Brasil depende, atualizada todo dia.

O Radar mostra, para cada projeto da lista, se ele está mudando (**Atividade**) e quantas pessoas sustentam essas mudanças (**Sustentação**). O objetivo é apontar onde a comunidade pode ajudar: projetos ativos, mas sustentados por uma pessoa só. Ele nasceu da [discussão #10](https://github.com/orgs/HarpyBR/discussions/10) da comunidade Harpia e automatiza o levantamento feito ali à mão.

- **Página:** https://iewerthondev.github.io/harpia-radar/
- **Espelho:** https://iewerthondev.codeberg.page/harpia-radar/
- **Dados abertos:** [`data/snapshot.json`](data/snapshot.json), com histórico no próprio git

## O que o Radar é, e o que não é

O Radar existe para ajudar quem mantém projetos, nunca para expor ninguém. Um projeto sustentado por uma pessoa aparece como "Precisa de mãos", nunca como "arriscado" ou "morto".

- **Não é ranking.** Não há nota única nem "melhor projeto".
- **Não avalia** qualidade de código nem segurança.
- **Não descobre projetos sozinho.** A lista é curada por pessoas, via PR.
- **Não mostra nomes.** A página só tem números agregados.
- **Não tem** servidor, banco de dados, login, cookies, analytics nem IA.

## Os selos

| Selo | Valores |
| --- | --- |
| Atividade | Ativo · Ativo, sem release recente · Sem alterações recentes · Arquivado |
| Sustentação | Bem distribuído · Poucas pessoas · Precisa de mãos · Poucos dados na janela |

As regras exatas estão na seção "Como ler os selos" da página. Os limites ficam em [`config/health.yaml`](config/health.yaml) e podem ser discutidos via PR. A especificação completa está em [`docs/SPEC.md`](docs/SPEC.md).

## Sugerir um projeto

Edite [`projects.yaml`](projects.yaml) e abra um PR. O [guia de contribuição](CONTRIBUTING.md) explica o passo a passo. Se preferir, abra uma issue com o modelo "Sugerir projeto".

## Rodar localmente

Só é preciso Docker. O PHP 8.5 e o Composer vêm na imagem.

```sh
docker compose build php
docker compose run --rm php composer install

# Token do GitHub só de leitura de repositórios públicos:
# https://github.com/settings/personal-access-tokens/new
export GITHUB_TOKEN=seu_token

docker compose run --rm php bin/radar validate   # confere projects.yaml
docker compose run --rm php bin/radar collect    # grava data/snapshot.json
docker compose run --rm php bin/radar build      # gera public/index.html
```

Abra `public/index.html` no navegador. O token é obrigatório: sem autenticação, o limite da API do GitHub é baixo demais para a coleta.

Para desenvolver:

```sh
docker compose run --rm php composer test       # testes (Pest), sem acesso à rede
docker compose run --rm php composer coverage   # cobertura mínima de 80%
docker compose run --rm php composer stan       # PHPStan nível 8
```

## Como funciona

1. Um workflow do GitHub Actions roda todo dia.
2. `bin/radar collect` lê `projects.yaml` e consulta cada repositório pela API do GitHub.
3. Classes puras calculam as métricas e os selos, sem acesso à rede.
4. O snapshot é gravado em `data/snapshot.json` e entra num commit.
5. `bin/radar build` gera `public/` com Twig: HTML e CSS, sem JavaScript.
6. A página é checada com pa11y (WCAG 2.2 AA) e publicada no GitHub Pages e no Codeberg Pages.

```
projects.yaml            lista curada
config/health.yaml       janela, limites dos selos e bots
schema/                  JSON Schemas de projects.yaml, snapshot.json e health.yaml
src/Catalog              leitura e validação da lista
src/Source               adaptador da forja (GitHub na v1)
src/Metrics, src/Health  cálculo e classificação, puros
src/Site                 montagem da página
templates/               Twig e CSS
tests/fixtures/          respostas gravadas da API, anonimizadas
```

## Licença

Código sob a licença [MIT](LICENSE). Dados em `data/` sob [CC BY 4.0](data/LICENSE).

Este projeto segue o [código de conduta da comunidade Harpia](CODE_OF_CONDUCT.md).
