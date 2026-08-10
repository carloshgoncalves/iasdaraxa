# Memorial de Calculo e Planejamento

## Origem do modelo

O motor foi estruturado a partir da planilha `PROPOSTA OBRA IASD ARAXA REV1 20.04.26.xlsx`.
A planilha separa dados do empreendimento, memorias por disciplina e a planilha orcamentaria. As quantidades do orcamento referenciam o memorial, enquanto custos sao separados em material, equipamento e mao de obra e recebem BDI.

No sistema, referencias de celula foram substituidas por regras nomeadas. Cada item gerado registra:

- codigo da regra;
- quantidade e unidade calculadas;
- formula legivel;
- origem gerada ou associada;
- item correspondente na EAP.

## Entradas

O questionario considera tipologia, padrao de acabamento, sistema estrutural, areas, pavimentos, unidades, ambientes molhados, prazo, subsolo, elevador e piscina.

## Saidas

O gerador cria pacotes de trabalho para servicos preliminares, movimento de terra, fundacoes, estrutura, vedacoes, instalacoes, cobertura, acabamentos, areas externas e entrega. As tarefas recebem datas em dias uteis, duracao por produtividade, predecessora termino-inicio e uma composicao SINAPI quando ha correspondencia tecnicamente plausivel.

## Controle de mudancas

O recálculo atualiza itens vinculados sem duplica-los. Itens manuais podem ser associados ao memorial. A exclusao de item gerado registra a supressao da regra para impedir recriacao involuntaria no proximo calculo.

Os quantitativos sao estimativas de planejamento preliminar. Projeto executivo, levantamento local, normas aplicaveis e validacao do responsavel tecnico continuam obrigatorios antes de contratar ou executar os servicos.
