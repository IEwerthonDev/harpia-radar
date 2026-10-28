# Como contribuir

Obrigado por querer ajudar! O Harpia Radar é mantido pela comunidade Harpia, e toda contribuição conta: sugerir um projeto, corrigir um texto, melhorar a acessibilidade ou escrever código.

Se for sua primeira contribuição em código aberto, procure as issues com a label [`good first issue`](../../issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22). Dúvidas são bem-vindas nas issues, em português ou em inglês.

## Sugerir um projeto

A forma mais rápida é editar o [`projects.yaml`](projects.yaml) direto pelo GitHub (ícone de lápis) e abrir um PR.

1. Escolha a categoria em `categories`. Se nenhuma servir, proponha uma nova no mesmo PR.
2. Adicione o projeto no fim de `projects`:

   ```yaml
     - id: nome-do-projeto              # único, letras minúsculas, números e hífens
       name: nome-do-projeto            # como o projeto se chama
       repo: github:dono/repositorio    # na v1, só GitHub
       category: fiscal                 # id de uma categoria existente
       description: O que o projeto faz, em uma frase.
       language: PHP                    # opcional
       replaces: Que solução paga ele substitui   # opcional
       tags: [nf-e, boleto]             # opcional
   ```

3. No PR, conte em uma ou duas frases por que o projeto é importante para o Brasil.

O CI roda `bin/radar validate`, que confere o formato, os ids únicos, a categoria e se o repositório existe. Se algo estiver errado, a mensagem diz o projeto e o campo.

**O que ajuda a revisão:** o projeto ser de código aberto, ter repositório público, ser usado por pessoas, empresas ou órgãos públicos no Brasil e substituir ou evitar uma solução fechada. A decisão final é das pessoas revisoras listadas em [`CODEOWNERS`](.github/CODEOWNERS); cada PR precisa de uma aprovação.

**Tom:** o Radar existe para ajudar quem mantém os projetos. Nas descrições, evite palavras como "abandonado", "morto" ou "arriscado".

## Mudar os limites dos selos

Os limites ficam em [`config/health.yaml`](config/health.yaml). Abra um PR explicando o motivo, de preferência com exemplos de projetos que mudariam de selo.

## Contribuir com código

Você só precisa de Docker:

```sh
docker compose build php
docker compose run --rm php composer install
docker compose run --rm php composer test
docker compose run --rm php composer stan
```

Regras do projeto:

- PHP 8.5, `declare(strict_types=1)`, PSR-4 e PSR-12, PHPStan nível 8.
- Testes primeiro, com Pest. Testes nunca acessam a rede: use as respostas gravadas em `tests/fixtures/` e nunca grave nomes, logins ou e-mails reais nelas.
- Classes de métricas e de classificação são puras: sem I/O e recebendo a data atual como parâmetro.
- Código, commits e chaves de dados em inglês; textos da página, README e este guia em português.
- Nenhuma dependência nova de produção sem discutir antes numa issue.
- A página precisa continuar funcionando sem JavaScript, sem recursos de terceiros e passando no pa11y (WCAG 2.2 AA). Todo selo tem texto, nunca só cor.

Para ver a página localmente com seus dados, gere um token de leitura em https://github.com/settings/personal-access-tokens/new e rode:

```sh
export GITHUB_TOKEN=seu_token
docker compose run --rm php bin/radar collect
docker compose run --rm php bin/radar build
```

## Código de conduta

Este projeto segue o [código de conduta da comunidade Harpia](CODE_OF_CONDUCT.md).
