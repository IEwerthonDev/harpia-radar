# Rascunho do post na discussão #10

Para publicar à mão em https://github.com/orgs/HarpyBR/discussions/10 depois que a página estiver no ar.

---

Pessoal, na conversa sobre clonar ferramentas a gente chegou num ponto em comum: **curadoria antes de clonar**. Fiz aquele levantamento à mão das libs de NF-e, boleto e Pix, e ficou claro que o problema não é falta de código, é depender de poucas pessoas.

Só que levantamento manual envelhece rápido. Então transformei ele no **Harpia Radar**: uma página estática, atualizada todo dia, que mostra para cada projeto dois selos:

- **Atividade:** o projeto está mudando?
- **Sustentação:** quantas pessoas sustentam essas mudanças?

🔗 Página: https://iewerthondev.github.io/harpia-radar/
🔗 Código: https://github.com/IEwerthonDev/harpia-radar

Alguns cuidados que tomei:

- Não é ranking, não tem nota única.
- A página não mostra nomes de ninguém, só números.
- Um projeto com uma pessoa só aparece como "Precisa de mãos", nunca como "arriscado". A ideia é chamar ajuda, não expor quem mantém.
- Sem JavaScript, sem analytics, acessível (WCAG 2.2 AA), com espelho no Codeberg.

Começa com os 9 repositórios do levantamento. **A lista é aberta:** qualquer pessoa sugere projetos editando o `projects.yaml` via PR. As categorias de documentos/PDF, assinatura digital, transparência e infraestrutura ainda estão vazias, e tem issues `good first issue` para quem quer começar.

Os limites dos selos (90 dias de janela, bus factor, etc.) ficam num arquivo de configuração e podem ser discutidos. Se algum selo não bater com o que vocês sabem de um projeto, me avisem: a primeira calibragem é justamente isso.

Quando a comunidade topar, a ideia é transferir o repositório para a organização da Harpia.
