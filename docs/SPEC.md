# Harpia Radar — PRD e Spec

28/10/2026 · IEwerthonDev

O Harpia Radar é uma página estática, atualizada todo dia, que mostra a saúde dos projetos de código aberto de que o Brasil depende. Ele automatiza o inventário que a comunidade pediu na discussão #10 da Harpia.

## Contexto e problema

Na discussão [#10 Clone de Ferramentas](https://github.com/orgs/HarpyBR/discussions/10), a comunidade convergiu para **curadoria antes de clonar**. Ludmila, Chr1sMartins, carlosvoliv e coproduto defenderam isso, e IEwerthonDev fez um levantamento manual de bibliotecas de NF-e, boleto e Pix.

O levantamento mostrou que o problema não é falta de código. É dependência de poucas pessoas: no sped-nfe, cerca de 57% dos commits do último ano são de uma pessoa só, e no laravel-boleto, cerca de 90%. Se essas pessoas pararem, pequenos negócios são empurrados para soluções fechadas e pagas.

Hoje esse diagnóstico foi feito à mão, uma vez, e envelhece rápido. O Radar refaz o diagnóstico todo dia e deixa a lista de projetos aberta a PRs.

Outras discussões definem restrições do projeto:

- [#3](https://github.com/orgs/HarpyBR/discussions/3): impacto nacional e acessibilidade como obrigação.
- [#14](https://github.com/orgs/HarpyBR/discussions/14) e [#1](https://github.com/orgs/HarpyBR/discussions/1): conteúdo em português, código em inglês.
- [#2](https://github.com/orgs/HarpyBR/discussions/2): não depender só do GitHub.
- [#17](https://github.com/orgs/HarpyBR/discussions/17): nada preso a IA proprietária.
- [#9](https://github.com/orgs/HarpyBR/discussions/9): bons projetos de entrada para quem nunca contribuiu.

## Objetivos, não-objetivos e público

O objetivo da v1 é uma página pública com 15 a 30 projetos curados, atualizada automaticamente todo dia, sem servidor e sem custo.

**Objetivos**

1. Mostrar, para cada projeto, sinais objetivos de atividade e de dependência de pessoas.
2. Agrupar os projetos por categoria de uso: fiscal, pagamentos, documentos e PDF, assinatura digital, transparência, infraestrutura.
3. Permitir que qualquer pessoa sugira um projeto editando um arquivo YAML via PR.
4. Apontar onde a comunidade pode ajudar: projetos ativos, mas sustentados por uma pessoa só.

**Não-objetivos da v1**

- Não é ranking. Não há nota única nem "melhor projeto".
- Não avalia qualidade de código nem segurança. Isso pode vir na v2 via [OpenSSF Scorecard](https://github.com/ossf/scorecard).
- Não tem login, banco de dados, servidor ou IA.
- Não descobre projetos sozinho. A lista é curada por pessoas.
- Não hospeda nem faz fork dos projetos.

**Público**

| Quem | O que precisa |
| --- | --- |
| Dev que vai escolher uma biblioteca | Saber se ela está viva antes de depender dela |
| Contribuidor da Harpia | Saber onde ajudar tem mais impacto |
| Mantenedor de projeto | Visibilidade e chegada de novos contribuidores |
| Pequeno negócio, MEI, gestor público | Encontrar alternativas abertas por categoria |

O tom importa: o Radar existe para ajudar mantenedores, não para expô-los. Um projeto com uma pessoa só aparece como "precisa de mãos", nunca como "arriscado" ou "morto".

## Requisitos funcionais

São sete requisitos na v1. Cada um tem um ID para ser citado em issues, PRs e no prompt de spec.

| ID | Requisito | Critério de aceite |
| --- | --- | --- |
| RF-01 | Ler a lista curada de `projects.yaml` | Dado um YAML válido com N projetos, o coletor processa os N. Dado um campo obrigatório ausente, o comando `validate` falha e diz o projeto e o campo. |
| RF-02 | Coletar dados de cada repositório pela API do GitHub | Para cada projeto, o snapshot traz último release, commits e autores nos últimos 90 dias, data do último commit e flag de arquivado. Um projeto com erro (404, timeout) vira `status: error` e não derruba a coleta dos outros. |
| RF-03 | Calcular métricas e classificar a saúde | As regras da seção de métricas são aplicadas de forma determinística: os mesmos dados de entrada sempre geram a mesma classificação. |
| RF-04 | Gravar `snapshot.json` versionado | O arquivo segue o schema publicado, tem data de coleta e fica no repositório, para formar histórico pelo próprio git. |
| RF-05 | Gerar a página estática | A partir do snapshot, gera HTML com projetos por categoria, os selos de cada um, link para o repositório e a data da última coleta. A página funciona sem JavaScript. |
| RF-06 | Atualizar automaticamente | Um workflow agendado roda uma vez por dia, faz commit do snapshot e publica a página. Se a coleta falhar por completo, a página anterior continua no ar. |
| RF-07 | Validar PRs que mexem na lista | Todo PR que altera `projects.yaml` roda `validate` no CI: schema, IDs únicos, categoria existente e repositório acessível. |

Nice-to-have da v1, se sobrar tempo: filtro por categoria e linguagem com JavaScript progressivo, e um link "como ajudar" por projeto que leva às issues com label `good first issue`.

## Métricas de saúde

O Radar mostra dois selos separados, **Atividade** e **Sustentação**, em vez de uma nota única. Uma nota esconde o motivo; dois selos mostram se o projeto está parado ou se está vivo mas depende de alguém. Todos os limites ficam num arquivo de configuração e podem ser discutidos via PR.

**Métricas coletadas** (janela de 90 dias, branch padrão)

| Métrica | Definição | Fonte na API |
| --- | --- | --- |
| `last_commit_at` | Data do commit mais recente na branch padrão. Sempre coletada: é o que a página mostra quando não há alterações na janela | `GET /repos/{o}/{r}/commits?per_page=1` |
| `last_release_at` | Data do release mais recente; sem release, a data da tag mais recente; sem nenhum, nulo | `GET /repos/{o}/{r}/releases/latest`, depois `/tags` |
| `commits_90d` | Commits nos últimos 90 dias, sem bots | `GET /repos/{o}/{r}/commits?since=...`, paginado |
| `authors_90d` | Autores distintos nesses commits | Mesmo endpoint |
| `top_author_share` | Fatia dos commits da janela feitos pelo autor que mais commitou | Calculado |
| `bus_factor` | Menor número de autores que somam pelo menos 50% dos commits da janela | Calculado |
| `archived`, `license`, `open_issues` | Dados do repositório | `GET /repos/{o}/{r}` |

Regras de identificação de autor: usar o `login` do GitHub quando existir; senão, o e-mail do commit normalizado em minúsculas. Bots são logins terminados em `[bot]` mais uma lista configurável (ex.: `dependabot`, `renovate`). Commits de merge contam normalmente.

A concentração é calculada assim, onde cᵢ é o número de commits do autor i:

```latex
\text{top\_author\_share} = \frac{\max_i c_i}{\sum_i c_i}
```

**Classificação**

| Selo | Texto na página | Regra |
| --- | --- | --- |
| Atividade | Ativo | Commit nos últimos 90 dias e release nos últimos 12 meses |
| Atividade | Ativo, sem release recente | Commit nos últimos 90 dias, mas último release há mais de 12 meses ou nenhum |
| Atividade | Sem alterações recentes | Nenhum commit em 90 dias. A página mostra "Última alteração em DD/MM/AAAA" |
| Atividade | Arquivado | Repositório marcado como arquivado |
| Sustentação | Bem distribuído | `bus_factor` ≥ 3 |
| Sustentação | Poucas pessoas | `bus_factor` = 2 |
| Sustentação | Precisa de mãos | `bus_factor` = 1 ou `top_author_share` > 75% |
| Sustentação | Poucos commits para medir | Menos de 10 commits em 90 dias: amostra pequena demais para medir |

A janela de 90 dias mostra o estado atual do projeto. Um projeto estável que muda pouco aparece como "Sem alterações recentes" com a data da última alteração, o que não é demérito. Cor nunca é a única informação: todo selo tem texto e ícone.

O caso do sped-da (impressão do DANFE) mostra por que os selos são separados: com commits frequentes mas sem release desde 2024, ele aparece como "Ativo, sem release recente", não como abandonado.

Referência de métricas: o projeto [CHAOSS](https://chaoss.community/) define métricas parecidas de saúde de comunidade, incluindo o "Contributor Absence Factor", equivalente ao `bus_factor`.

## Formato de dados

São dois arquivos: `projects.yaml`, editado por pessoas, e `data/snapshot.json`, gerado pelo coletor. Campos e chaves em inglês; descrições em português.

**projects.yaml** (exemplo com dois projetos do levantamento da #10)

```yaml
categories:
  - id: fiscal
    name: Fiscal e notas
  - id: pagamentos
    name: Pagamentos, boleto e Pix

projects:
  - id: sped-nfe
    name: sped-nfe
    repo: github:nfephp-org/sped-nfe
    category: fiscal
    description: Emissão de NF-e e NFC-e, já com IBS/CBS da reforma tributária.
    language: PHP
    replaces: Emissores fiscais pagos
    tags: [nf-e, nfc-e, reforma-tributaria]

  - id: laravel-boleto
    name: laravel-boleto
    repo: github:eduardokum/laravel-boleto
    category: pagamentos
    description: Geração de boletos e arquivos CNAB.
    language: PHP
    replaces: Gateways de cobrança pagos
    tags: [boleto, cnab]
```

Obrigatórios: `id` (único, kebab-case), `name`, `repo`, `category`, `description`. O prefixo em `repo` (`github:`) já prepara a v2 para `codeberg:` e `gitlab:`.

**Lista inicial (M0)**: os 9 repositórios do levantamento da #10, todos conferidos e existentes em 28/10/2026.

| id | repo | category |
| --- | --- | --- |
| sped-nfe | github:nfephp-org/sped-nfe | fiscal |
| sped-da | github:nfephp-org/sped-da | fiscal |
| sped-cte | github:nfephp-org/sped-cte | fiscal |
| sped-mdfe | github:nfephp-org/sped-mdfe | fiscal |
| nfse-php | github:nfse-nacional/nfse-php | fiscal |
| laravel-boleto | github:eduardokum/laravel-boleto | pagamentos |
| openboleto | github:openboleto/openboleto | pagamentos |
| php-pix | github:piggly-dev/php-pix | pagamentos |
| php-qrcode-pix | github:renatomb/php_qrcode_pix | pagamentos |

**snapshot.json** (um item)

```json
{
  "generated_at": "2026-10-28T06:00:00Z",
  "schema_version": 1,
  "window_days": 90,
  "projects": [
    {
      "id": "sped-nfe",
      "status": "ok",
      "metrics": {
        "last_commit_at": "2026-10-27",
        "last_release_at": "2026-10-14",
        "commits_90d": 61,
        "authors_90d": 9,
        "top_author_share": 0.55,
        "bus_factor": 1,
        "archived": false,
        "license": "LGPL-3.0",
        "open_issues": 0
      },
      "health": { "activity": "active", "sustainability": "needs_hands" }
    }
  ]
}
```

Os números do exemplo são fictícios, só para mostrar o formato. A licença e as issues abertas precisam ser confirmadas na primeira coleta real.

## Arquitetura e stack

Recomendação: **PHP 8.3 para o coletor e para gerar o HTML estático com Twig**, rodando no GitHub Actions, sem servidor. PHP se aplica bem: o Radar é um programa de linha de comando que faz requisições HTTP, calcula números e renderiza templates, e PHP faz as três coisas com folga.

**Fluxo**

1. Um workflow agendado do GitHub Actions roda uma vez por dia.
2. `bin/radar collect` lê `projects.yaml` e consulta cada repositório por um adaptador de fonte (`SourceInterface`; na v1 só `GitHubSource`).
3. Classes puras calculam métricas e classificação, sem acesso à rede, para serem fáceis de testar.
4. O coletor grava `data/snapshot.json`, e o workflow faz commit dele.
5. `bin/radar build` renderiza os templates Twig em `public/`: HTML e CSS, sem JavaScript obrigatório.
6. O workflow publica `public/` no GitHub Pages e espelha no Codeberg Pages, atendendo à #2.

**Por que PHP aqui**

- O primeiro grupo de projetos do Radar (NFePHP, laravel-boleto, libs de Pix) é PHP, e quem toca o projeto, o IEwerthonDev, é dev PHP. O projeto fica perto de quem vai mantê-lo.
- PHP é muito difundido no Brasil, o que facilita o projeto-escola da #9.
- O ecossistema Symfony cobre tudo: `symfony/console`, `symfony/http-client`, `symfony/yaml`, `twig/twig`. Testes com Pest ou PHPUnit, análise estática com PHPStan.
- Não precisa de SDK do GitHub: são quatro endpoints REST, mais simples de chamar direto do que adicionar uma dependência.

O ponto fraco do PHP aqui é o front-end: não há um gerador de site estático tão maduro quanto o Astro. Para uma página de listagem, Twig e CSS puro bastam.

**Alternativas**

| Stack | Encaixe | Pontos fortes | Pontos fracos |
| --- | --- | --- | --- |
| PHP 8.3 + Symfony + Twig | Recomendada | Comunidade do público-alvo, simples, um só idioma | Front-end mais artesanal |
| TypeScript + Astro + Octokit | Boa alternativa | Octokit oficial com retry e throttling, Astro gera HTML estático acessível | Duas camadas (Node + Astro), mais dependências npm |
| Python + Jinja2 | Boa para dados | Bom para análise e gráficos futuros | Menos alinhada ao público dos projetos fiscais |
| Go | Exagero na v1 | Binário único, rápido | Menos gente para contribuir; desempenho não é o gargalo |

O gargalo real é a API, não a linguagem. Com 30 projetos, a coleta fica na ordem de 100 a 200 requisições por dia. O `GITHUB_TOKEN` do Actions tem limite de 1.000 requisições por hora por repositório, então sobra margem. Usar cabeçalhos `ETag` reduz ainda mais o consumo.

Evitar o endpoint `/stats/contributors`: ele devolve 202 enquanto calcula e conta o histórico inteiro, não só a janela. A lista de commits com `since` dá o recorte certo.

**Estrutura de pastas**

```
radar/
  projects.yaml
  config/health.yaml        # janela, limites dos selos e lista de bots
  bin/radar                 # collect | build | validate
  src/
    Source/SourceInterface.php
    Source/GitHubSource.php
    Metrics/MetricsCalculator.php
    Health/HealthClassifier.php
    Catalog/CatalogLoader.php
    Site/SiteBuilder.php
  templates/
  schema/projects.schema.json
  schema/snapshot.schema.json
  data/snapshot.json
  tests/fixtures/           # respostas da API gravadas
  .github/workflows/radar.yml
  .github/workflows/validate.yml
```

## Requisitos não funcionais

| ID | Tema | Requisito |
| --- | --- | --- |
| RNF-01 | Acessibilidade | WCAG 2.2 nível AA: HTML semântico, contraste mínimo de 4.5:1, navegação por teclado, selos com texto além de cor. Verificado no CI com axe ou pa11y. |
| RNF-02 | Idioma | Interface, README e guia de contribuição em português. Código, nomes de classes e chaves de dados em inglês. |
| RNF-03 | Leveza | Página funciona sem JavaScript, pesa menos de 200 KB e abre bem em celular simples e conexão 3G. |
| RNF-04 | Privacidade | Sem analytics, cookies ou scripts de terceiros. Nenhum dado pessoal além de logins públicos usados só para contagem; a página não mostra nomes de autores. |
| RNF-05 | Soberania | Página publicada também no Codeberg Pages. Sem IA e sem serviço pago. A fonte de dados é um adaptador, para incluir outras forjas. |
| RNF-06 | Reprodutibilidade | Qualquer pessoa roda `collect` e `build` localmente com um token próprio e obtém o mesmo resultado para o mesmo snapshot. |
| RNF-07 | Testes | Testes não acessam a rede: usam respostas gravadas em `tests/fixtures`. Cobertura mínima de 80% em `Metrics` e `Health`. |
| RNF-08 | Licença | Código sob licença MIT e dados do snapshot sob CC BY 4.0. |

## Marcos de entrega

A demo para apresentar fica pronta no M3; M4 e M5 transformam a demo em projeto da comunidade.

1. **M0 — Base.** Repositório, Composer, PHPStan, Pest, `projects.yaml` com os 9 repositórios da lista inicial, schemas JSON e comando `validate`.
2. **M1 — Coletor.** `GitHubSource` com paginação, retry e tratamento de erro por projeto; `MetricsCalculator`; testes com fixtures; `collect` gera `snapshot.json`.
3. **M2 — Classificação.** `HealthClassifier` lendo `config/health.yaml`; testes cobrindo cada faixa de cada selo.
4. **M3 — Página (demo).** Templates Twig, CSS, selos acessíveis, data da coleta, seção "onde ajudar" com os projetos "Precisa de mãos".
5. **M4 — Automação.** Workflow diário, commit do snapshot, deploy no GitHub Pages, espelho no Codeberg e checagem de acessibilidade no CI.
6. **M5 — Comunidade.** README e CONTRIBUTING em português, template de PR "adicionar projeto", issues `good first issue`, `CODEOWNERS`, post na discussão #10 e uma issue amigável avisando cada mantenedor listado.

**Próximas tarefas**

- [ ] Criar o repositório e o esqueleto do M0
- [ ] Escrever `projects.yaml` com os 9 repositórios da lista inicial
- [ ] Gravar fixtures reais da API para 2 projetos
- [ ] Implementar e testar `MetricsCalculator`

**v2 (fora do escopo agora):** adaptadores Codeberg/Forgejo e GitLab, gráfico de tendência a partir do histórico do snapshot, tempo de primeira resposta em issues, OpenSSF Scorecard, e projetos das outras discussões (transparência da #7).

## Decisões

| Tema | Decisão |
| --- | --- |
| Nome | Harpia Radar |
| Janela de análise | 90 dias. Sem alterações na janela, a página mostra a data da última alteração |
| Limiares dos selos | Os da tabela de classificação: bus factor 3 / 2 / 1, concentração acima de 75%, mínimo de 10 commits na janela e release nos últimos 12 meses |
| Lista de projetos | Aberta: qualquer pessoa sugere ou mantém projetos via PR. Cada PR precisa de 1 aprovação, definida no `CODEOWNERS` |
| Mantenedores dos projetos | Avisados com uma issue amigável, escrita à mão, uma por projeto, depois que a página estiver no ar. Se o repositório não aceita issues, usar Discussions ou não avisar |
| Licença | MIT para o código; CC BY 4.0 para os dados |
| Repositório | No perfil do IEwerthonDev até a apresentação; depois, proposta de transferir para a organização Harpia |
| Hospedagem | GitHub Pages, grátis, em `iewerthondev.github.io/harpia-radar`, com espelho no Codeberg Pages |
| Domínio próprio | Definido depois que a comunidade Harpia aceitar o projeto |

**Próximo passo**

- [ ] Depois da primeira coleta real com os 9 repositórios, conferir se os selos batem com o que a comunidade sabe deles e recalibrar os limiares se precisar.
