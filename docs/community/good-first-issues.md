# Rascunhos de issues `good first issue`

Para abrir à mão depois do primeiro push, cada uma com as labels `good first issue` e `ajuda bem-vinda`.

---

## 1. Adicionar projetos de assinatura digital

A categoria "Assinatura digital" está vazia. Procure bibliotecas abertas usadas no Brasil para assinar documentos com certificado ICP-Brasil (A1/A3) ou validar assinaturas, e adicione no `projects.yaml`.

**Como fazer:** siga a seção "Sugerir um projeto" do CONTRIBUTING.md. Um projeto por PR facilita a revisão.

---

## 2. Adicionar projetos de documentos e PDF

A categoria "Documentos e PDF" está vazia. Exemplos do que cabe: geração de PDF, leitura de XML fiscal para impressão, OCR em português.

**Como fazer:** igual à issue anterior.

---

## 3. Adicionar projetos de transparência (discussão #7)

A [discussão #7](https://github.com/orgs/HarpyBR/discussions) trata de transparência pública. Traga para o Radar ferramentas abertas que leem dados de portais de transparência, diários oficiais ou licitações.

---

## 4. Revisar a página com leitor de tela

Abra a página com NVDA (Windows), Orca (Linux) ou VoiceOver (macOS/iOS) e conte como foi a experiência: a ordem de leitura faz sentido? Os selos são anunciados de forma clara? Relate o que achou nesta issue, mesmo sem saber corrigir.

---

## 5. Usar ETag para economizar requisições

A spec sugere usar o cabeçalho `ETag` da API do GitHub. Com `If-None-Match`, respostas sem mudança voltam `304` e não contam no limite.

**Onde mexer:** `src/Source/GitHubApi.php`. Precisa guardar os ETags entre execuções (por exemplo, num arquivo em `data/`). Escreva o teste antes, com `MockHttpClient`.

---

## 6. Filtro por categoria e linguagem (JavaScript progressivo)

Nice-to-have da v1: um filtro que funcione com JavaScript, mas sem quebrar a página sem ele. Precisa continuar passando no pa11y e não pode carregar nada de terceiros.
