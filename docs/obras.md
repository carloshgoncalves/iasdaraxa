# Modulo de Obras

Inspiracao funcional: Vobi para gestao integrada de obras/orcamento/financeiro e MS Project para EAP, hierarquia de tarefas, dependencias, prazos e acompanhamento de progresso.

## Areas

- Portfolio de obras: status, prioridade, prazo, progresso e orcado x realizado.
- Planejamento: itens e subitens com profundidade ilimitada, ordem, predecessoras, marco, duracao, datas, progresso e custo previsto.
- Visualizacao da EAP: ramos recolhiveis, filtro por profundidade e composicao com materiais, servicos e equipamentos vinculados.
- Materiais, servicos e equipamentos sao incluidos diretamente na composicao de cada item do Planejamento.
- Recursos existentes podem ser editados no popup da composicao, inclusive quantidade, fornecedor, catalogo SINAPI, status e observacoes.
- Itens do planejamento podem ser movidos para outra etapa pela alca de arraste; ciclos hierarquicos sao bloqueados no servidor.
- O seletor `Colunas` controla os campos visiveis e salva a preferencia no navegador.
- Exportacoes disponiveis em PDF, XLSX e CSV. A importacao aceita XLS, XLSX e CSV com EAP, hierarquia, datas, custos, progresso, marcos e predecessoras.
- Um planejamento pode ser salvo como modelo e aplicado ao cadastrar uma nova obra.
- Catalogo SINAPI: materiais, composicoes de servicos e equipamentos pesquisaveis por codigo ou descricao.
- Financeiro: entradas e saidas por obra, categoria, status, fornecedor e item do planejamento.
- Diario de obra: relato por data, equipe, clima e impedimentos.

## Scripts de banco

- Base completa: `database/schema.sql`
- Incremental para producao: `database/migrations/20260729_obras_gestao.sql`
- Migracao para itens hierarquicos: `database/migrations/20260808_obras_itens_hierarquicos.sql`
- Migracao do catalogo SINAPI e equipamentos: `database/migrations/20260808_sinapi_catalogo_obras.sql`
- Catalogo tecnico importavel: `database/data/sinapi-mg-2026-06.csv.gz`
- Importacao: `php bin/import-sinapi.php database/data/sinapi-mg-2026-06.csv.gz 2026-06 <url-fonte>`
- A busca e paginada e permite percorrer todas as opcoes ativas. Quando uma referencia tecnica nao publica preco, o importador preserva o ultimo preco conhecido do mesmo tipo e codigo e registra sua competencia separadamente.

## Configuracoes

- O acesso `Configuracoes` fica acima de `Sair` e e restrito ao perfil administrador.
- Aparencia global: fonte, densidade, linguagem, cores e largura da barra lateral.
- Usuarios: cadastro sem obrigatoriedade de `@`, alteracao de senha, perfil e status.

## Observacoes

- Qualquer item pode conter subitens indefinidamente por meio de `parent_id`.
- Itens podem apontar multiplas predecessoras sem permitir dependencias circulares.
- Materiais, servicos, equipamentos e movimentos financeiros podem se vincular a itens do planejamento.
- A referencia carregada e MG 09/2025 sem desoneracao. Registros sem custo publicado ficam com preco nulo.
- O financeiro atual registra previsto/comprometido/pago/recebido; depois pode receber aprovacao e anexos.
