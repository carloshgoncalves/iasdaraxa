<?php use IasdCentral\Core\Csrf; ?>
<?php $sinapiCompetence = $sinapiReference ? substr($sinapiReference['competencia'], 5, 2) . '/' . substr($sinapiReference['competencia'], 0, 4) : ''; ?>
<?php $sinapiPriceCompetence = !empty($sinapiReference['competencia_preco']) ? substr($sinapiReference['competencia_preco'], 5, 2) . '/' . substr($sinapiReference['competencia_preco'], 0, 4) : ''; ?>
<?php $sinapiLabel = $sinapiReference ? 'Catalogo SINAPI ' . $sinapiCompetence . ($sinapiPriceCompetence ? ' · precos MG ' . $sinapiPriceCompetence : '') . ' - ' . $sinapiReference['regime'] : 'SINAPI'; ?>
<section class="obra-detail-head panel no-margin">
    <div>
        <span class="eyebrow">Controle de obra</span>
        <h2><?= e($obra['nome']) ?></h2>
        <p class="muted"><?= e($obra['local'] ?: 'Local nao informado') ?> · <?= e($obra['status']) ?> · Prioridade <?= e($obra['prioridade']) ?></p>
    </div>
    <a class="button" href="<?= url('/obras') ?>">Voltar</a>
</section>

<nav class="module-tabs" data-tabs role="tablist" aria-label="Topicos da obra">
    <a id="tab-resumo" href="#resumo" role="tab" aria-controls="resumo" aria-selected="true">Resumo</a>
    <a id="tab-planejamento" href="#planejamento" role="tab" aria-controls="planejamento" aria-selected="false" tabindex="-1">Planejamento</a>
    <a id="tab-financeiro" href="#financeiro" role="tab" aria-controls="financeiro" aria-selected="false" tabindex="-1">Financeiro</a>
    <a id="tab-diario" href="#diario" role="tab" aria-controls="diario" aria-selected="false" tabindex="-1">Diario</a>
</nav>

<section id="resumo" class="grid cards-3 obra-summary-grid obra-tab-panel" role="tabpanel" aria-labelledby="tab-resumo" tabindex="0">
    <article class="card"><span>Progresso medio</span><strong><?= e(number_format($overview['progresso'], 0, ',', '.')) ?>%</strong></article>
    <article class="card"><span>Orcado</span><strong><?= e(money_br($overview['orcado'])) ?></strong></article>
    <article class="card"><span>Realizado</span><strong><?= e(money_br($overview['realizado'])) ?></strong></article>
</section>

<section id="planejamento" class="obra-section panel obra-tab-panel" data-planning-tree role="tabpanel" aria-labelledby="tab-planejamento" tabindex="0">
    <?php $memorialLinksByItem = array_column($memorialLinks, null, 'item_id'); ?>
    <div class="section-head">
        <div><h2>Estrutura do planejamento</h2><p class="muted">Itens e subitens sem limite de niveis, com cronograma, progresso e dependencias.</p></div>
        <a class="button primary small" href="<?= url('/obras/' . $obra['id'] . '?novo_item=1#planejamento') ?>">Novo item</a>
    </div>

    <nav class="planning-mode-tabs" data-planning-mode-tabs data-default-view="<?= e($planningSubView) ?>" aria-label="Visualizacoes do planejamento">
        <button type="button" data-planning-view="calendar">Calendario</button>
        <button type="button" data-planning-view="memorial">Memorial de calculo</button>
        <button type="button" data-planning-view="planning">Planejamento</button>
        <button type="button" data-planning-view="gantt">Gantt</button>
    </nav>

    <?php
    $calendarTime = strtotime($planningCalendarMonth . '-01');
    $previousCalendarMonth = date('Y-m', strtotime('-1 month', $calendarTime));
    $nextCalendarMonth = date('Y-m', strtotime('+1 month', $calendarTime));
    $calendarMonthLabel = strtr(date('F Y', $calendarTime), ['January'=>'Janeiro','February'=>'Fevereiro','March'=>'Marco','April'=>'Abril','May'=>'Maio','June'=>'Junho','July'=>'Julho','August'=>'Agosto','September'=>'Setembro','October'=>'Outubro','November'=>'Novembro','December'=>'Dezembro']);
    ?>
    <div class="planning-mode-panel" data-planning-panel="calendar" hidden>
        <div class="planning-subhead"><div><h3><?= e($calendarMonthLabel) ?></h3><p class="muted">Tarefas posicionadas pela data prevista de inicio.</p></div><div class="toolbar-actions"><a class="button small" href="<?= url('/obras/'.$obra['id'].'?visao=calendar&obra_mes='.$previousCalendarMonth.'#planejamento') ?>">Anterior</a><a class="button small" href="<?= url('/obras/'.$obra['id'].'?visao=calendar&obra_mes='.date('Y-m').'#planejamento') ?>">Hoje</a><a class="button small" href="<?= url('/obras/'.$obra['id'].'?visao=calendar&obra_mes='.$nextCalendarMonth.'#planejamento') ?>">Proximo</a><a class="button small primary" href="<?= url('/obras/'.$obra['id'].'/planejamento/calendario.ics') ?>">Exportar .ics</a></div></div>
        <div class="calendar-weekdays"><?php foreach(['Dom','Seg','Ter','Qua','Qui','Sex','Sab'] as $weekday): ?><span><?= e($weekday) ?></span><?php endforeach; ?></div>
        <div class="calendar-grid planning-calendar"><?php foreach($planningCalendar as $day): ?><?php if($day===null): ?><div class="calendar-day empty"></div><?php else: ?><div class="calendar-day <?= $day['date']===date('Y-m-d')?'today':'' ?>"><div class="day-head"><strong><?= e($day['day']) ?></strong></div><div class="day-events"><?php foreach($day['events'] as $event): ?><a class="event-pill" href="<?= url('/obras/'.$obra['id'].'?editar_item='.$event['id'].'#planejamento') ?>" style="--event-color: <?= e($event['status']==='Concluida'?'#2f6d54':'#7a1f38') ?>"><span><?= e($event['wbs']) ?></span><?= e($event['nome']) ?></a><?php endforeach; ?></div></div><?php endif; ?><?php endforeach; ?></div>
    </div>

    <?php $mem = $memorial ?? []; $area=(float)($mem['area_construida']??0); $floors=max(1,(int)($mem['pavimentos']??1)); ?>
    <div class="planning-mode-panel" data-planning-panel="memorial" hidden>
        <div class="planning-subhead"><div><h3>Memorial de calculo parametrico</h3><p class="muted">Entradas do empreendimento, grandezas derivadas e rastreabilidade com a EAP.</p></div><?php if(!empty($mem['generated_at'])): ?><span class="reference-badge">Calculado em <?= e(date_br(substr($mem['generated_at'],0,10))) ?></span><?php endif; ?></div>
        <form method="post" action="<?= url('/obras/'.$obra['id'].'/memorial') ?>" class="compact-form memorial-form"><?= Csrf::field() ?>
            <label class="memorial-switch"><input type="checkbox" name="ativo" value="1" <?= !empty($mem['ativo'])?'checked':'' ?> data-memorial-toggle><span><strong>Ativar Memorial de Calculo</strong><small>Quando ativo, o sistema pode calcular e manter itens vinculados ao planejamento.</small></span></label>
            <div data-memorial-questionnaire <?= empty($mem['ativo'])?'hidden':'' ?>>
                <fieldset><legend>Caracterizacao</legend><div class="form-grid four"><label>Tipo de obra <select name="tipo_obra"><?php foreach(['Residencial','Comercial','Industrial','Institucional','Reforma','Infraestrutura','Outro'] as $option): ?><option <?= ($mem['tipo_obra']??'Comercial')===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></label><label>Padrao de acabamento <select name="padrao_acabamento"><?php foreach(['Economico','Padrao','Alto','Especial'] as $option): ?><option <?= ($mem['padrao_acabamento']??'Padrao')===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></label><label>Sistema estrutural <select name="sistema_estrutural"><?php foreach(['Concreto armado','Estrutura metalica','Alvenaria estrutural','Misto','Outro'] as $option): ?><option <?= ($mem['sistema_estrutural']??'Concreto armado')===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></label><label>Prazo desejado (meses) <input type="number" min="1" name="meses_obra" value="<?= e($mem['meses_obra']??12) ?>"></label></div></fieldset>
                <fieldset><legend>Dimensoes</legend><div class="form-grid four"><label>Area do terreno (m2) <input type="number" min="0" step="0.01" name="area_terreno" value="<?= e($mem['area_terreno']??'') ?>"></label><label>Area construida (m2) <input required type="number" min="0" step="0.01" name="area_construida" value="<?= e($mem['area_construida']??'') ?>"></label><label>Testada (m) <input type="number" min="0" step="0.01" name="testada" value="<?= e($mem['testada']??'') ?>"></label><label>Pe-direito medio (m) <input type="number" min="2" step="0.01" name="pe_direito" value="<?= e($mem['pe_direito']??2.8) ?>"></label><label>Area externa (m2) <input type="number" min="0" step="0.01" name="area_externa" value="<?= e($mem['area_externa']??'') ?>"></label><label>Area de cobertura (m2) <input type="number" min="0" step="0.01" name="area_cobertura" value="<?= e($mem['area_cobertura']??'') ?>"></label></div></fieldset>
                <fieldset><legend>Programa e complexidade</legend><div class="form-grid four"><label>Pavimentos <input type="number" min="1" name="pavimentos" value="<?= e($mem['pavimentos']??1) ?>"></label><label>Unidades ou apartamentos <input type="number" min="1" name="unidades" value="<?= e($mem['unidades']??1) ?>"></label><label>Banheiros <input type="number" min="0" name="banheiros" value="<?= e($mem['banheiros']??1) ?>"></label><label>Cozinhas/copas <input type="number" min="0" name="cozinhas" value="<?= e($mem['cozinhas']??1) ?>"></label></div><div class="memorial-options"><label class="checkbox"><input type="checkbox" name="possui_subsolo" value="1" <?= !empty($mem['possui_subsolo'])?'checked':'' ?>> Subsolo</label><label class="checkbox"><input type="checkbox" name="possui_elevador" value="1" <?= !empty($mem['possui_elevador'])?'checked':'' ?>> Elevador</label><label class="checkbox"><input type="checkbox" name="possui_piscina" value="1" <?= !empty($mem['possui_piscina'])?'checked':'' ?>> Piscina</label></div><label>Observacoes tecnicas <textarea name="observacoes" rows="3"><?= e($mem['observacoes']??'') ?></textarea></label></fieldset>
                <?php
                $memorialGroups = [
                    'Estrutura e fundacoes' => [
                        ['comprimento_baldrame','Baldrame (m)','0.01'], ['comprimento_muro_divisa','Muro de divisa (m)','0.01'],
                        ['quantidade_brocas','Brocas/estacas (un)','1'], ['peso_aco_estimado','Aco estrutural (kg)','0.01'],
                        ['volume_concreto_estimado','Concreto estrutural (m3)','0.001'],
                    ],
                    'Revestimentos e pintura' => [
                        ['area_revestimento_interno','Revestimento interno (m2)','0.01'], ['area_revestimento_externo','Revestimento externo (m2)','0.01'],
                        ['area_reboco','Chapisco/emboço/reboco (m2)','0.01'], ['area_pintura_interna','Pintura interna (m2)','0.01'],
                        ['area_pintura_externa','Pintura externa (m2)','0.01'],
                    ],
                    'Esquadrias e serralheria' => [
                        ['quantidade_portas_madeira','Portas de madeira (un)','1'], ['quantidade_esquadrias_aluminio','Esquadrias de aluminio (un)','1'],
                        ['guarda_corpo_m','Guarda-corpo e corrimao (m)','0.01'],
                    ],
                    'Marmoraria e pedras' => [
                        ['quantidade_bancadas','Bancadas (un)','1'], ['soleiras_m','Soleiras (m)','0.01'], ['peitoris_m','Peitoris (m)','0.01'],
                    ],
                    'Forros e cobertura' => [
                        ['area_forro_gesso','Forro de gesso (m2)','0.01'], ['area_forro_madeira','Forro de madeira (m2)','0.01'],
                        ['area_telhado_metalico','Telhado metalico (m2)','0.01'], ['area_telhado_ceramico','Telhado ceramico (m2)','0.01'],
                        ['calhas_m','Calhas (m)','0.01'], ['rufos_m','Rufos (m)','0.01'],
                    ],
                    'Loucas, metais e instalacoes' => [
                        ['quantidade_bacias','Bacias sanitarias (un)','1'], ['quantidade_torneiras','Torneiras e metais (un)','1'],
                        ['quantidade_cubas','Cubas e tanques (un)','1'], ['pontos_eletricos','Pontos eletricos (un)','1'],
                        ['pontos_hidraulicos','Pontos hidraulicos (un)','1'],
                    ],
                    'Areas especiais e externas' => [
                        ['area_deck','Deck (m2)','0.01'], ['area_paisagismo','Paisagismo (m2)','0.01'], ['area_piscina','Piscina - area revestida (m2)','0.01'],
                    ],
                ];
                ?>
                <div class="memorial-specialties">
                    <?php foreach($memorialGroups as $groupName=>$fields): ?>
                        <details class="memorial-section">
                            <summary><?= e($groupName) ?><span><?= count($fields) ?> quantitativos</span></summary>
                            <div class="form-grid four">
                                <?php foreach($fields as [$field,$label,$step]): ?><label><?= e($label) ?><input type="number" min="0" step="<?= e($step) ?>" name="<?= e($field) ?>" value="<?= e($mem[$field]??0) ?>"></label><?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
                <?php if($area>0): ?><div class="memorial-results"><article><span>Projecao estimada</span><strong><?= e(number_format($area/$floors,2,',','.')) ?> m2</strong><small>area / pavimentos</small></article><article><span>Aco preliminar</span><strong><?= e(number_format($area*22,0,',','.')) ?> kg</strong><small>22 kg/m2</small></article><article><span>Concreto preliminar</span><strong><?= e(number_format($area*.16,2,',','.')) ?> m3</strong><small>0,16 m3/m2</small></article><article><span>Paredes/revestimentos</span><strong><?= e(number_format($area*3,2,',','.')) ?> m2</strong><small>area x 3,00</small></article></div><?php endif; ?>
                <div class="form-actions"><button class="button" name="generate" value="0">Salvar memorial</button><button class="button primary" name="generate" value="1">Salvar e recalcular planejamento</button></div>
            </div>
        </form>
        <?php if($memorialLinks!==[]): ?><div class="memorial-links"><h3>Itens vinculados</h3><div class="responsive-table"><table><thead><tr><th>Item</th><th>Origem</th><th>Quantidade</th><th>Formula</th></tr></thead><tbody><?php foreach($memorialLinks as $link): ?><tr><td><?= e($link['item_nome']) ?></td><td><?= e($link['origem']) ?></td><td><?= e(number_format((float)$link['quantidade_calculada'],3,',','.').' '.$link['unidade']) ?></td><td><small><?= e($link['formula']) ?></small></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
    </div>

    <div class="planning-mode-panel" data-planning-panel="gantt" hidden>
        <div class="planning-subhead"><div><h3>Grafico de Gantt</h3><p class="muted"><?= $gantt['start'] ? e(date_br($gantt['start']).' a '.date_br($gantt['finish']).' · '.$gantt['days'].' dias corridos') : 'Inclua datas previstas para montar o grafico.' ?></p></div></div>
        <?php if($gantt['items']!==[]): ?><div class="gantt"><div class="gantt-head"><span>Item</span><div><time><?= e(date_br($gantt['start'])) ?></time><time><?= e(date_br($gantt['finish'])) ?></time></div></div><?php foreach($gantt['items'] as $item): ?><div class="gantt-row"><div style="padding-left:<?= min(120,(int)$item['depth']*14) ?>px"><strong><?= e($item['wbs']) ?></strong> <?= e($item['nome']) ?></div><div class="gantt-track"><a href="<?= url('/obras/'.$obra['id'].'?editar_item='.$item['id'].'#planejamento') ?>" class="gantt-bar status-<?= e(strtolower(str_replace(' ','-',$item['status']))) ?>" style="--gantt-left:<?= e(number_format($item['gantt_left'],4,'.','')) ?>%;--gantt-width:<?= e(number_format($item['gantt_width'],4,'.','')) ?>%" data-tooltip="<?= e($item['nome'].' · '.date_br($item['data_inicio_prevista']).' a '.date_br($item['data_fim_prevista'])) ?>"><span style="width:<?= (int)$item['progresso'] ?>%"></span></a></div></div><?php endforeach; ?></div><?php endif; ?>
    </div>

    <?php $formItem = $editingItem ?? []; ?>
    <?php $selectedPredecessors = array_map('intval', $formItem['predecessor_ids'] ?? []); ?>
    <dialog class="planning-dialog" data-planning-dialog data-auto-open="<?= !empty($openPlanningItemDialog) ? 'true' : 'false' ?>" aria-labelledby="planning-dialog-title">
        <header class="planning-dialog-header">
            <div><span class="eyebrow">Planejamento</span><h2 id="planning-dialog-title"><?= $editingItem ? 'Editar item ' . e($editingItem['wbs']) : 'Inserir item' ?></h2></div>
            <button class="dialog-close" type="button" data-dialog-close aria-label="Fechar" title="Fechar">&times;</button>
        </header>
        <form method="post" action="<?= $editingItem ? url('/obras/' . $obra['id'] . '/itens/' . $editingItem['id']) : url('/obras/' . $obra['id'] . '/itens') ?>" class="compact-form planning-editor">
        <?= Csrf::field() ?>
        <div class="form-grid two">
            <label>Item superior
                <select name="parent_id">
                    <option value="">Raiz do planejamento</option>
                    <?php foreach ($parentOptions as $option): ?>
                        <option value="<?= e($option['id']) ?>" <?= (int) $selectedParentId === (int) $option['id'] ? 'selected' : '' ?>><?= e($option['wbs'] . ' ' . str_repeat('— ', (int) $option['depth']) . $option['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nome <input name="nome" required maxlength="180" value="<?= e($formItem['nome'] ?? '') ?>" autofocus></label>
        </div>
        <div class="form-grid four">
            <label>Ordem <input name="ordem" type="number" value="<?= e($formItem['ordem'] ?? 0) ?>"></label>
            <label>Duracao prevista (dias) <input name="duracao_prevista_dias" type="number" min="0" value="<?= e($formItem['duracao_prevista_dias'] ?? '') ?>"></label>
            <label>Progresso (%) <input name="progresso" type="number" min="0" max="100" value="<?= e($formItem['progresso'] ?? 0) ?>"></label>
            <label>Custo previsto <input name="custo_previsto" type="number" step="0.01" min="0" value="<?= e($formItem['custo_previsto'] ?? 0) ?>"></label>
        </div>
        <div class="form-grid four">
            <label>Data prevista de inicio <input name="data_inicio_prevista" type="date" value="<?= e($formItem['data_inicio_prevista'] ?? '') ?>"></label>
            <label>Data prevista de conclusao <input name="data_fim_prevista" type="date" value="<?= e($formItem['data_fim_prevista'] ?? '') ?>"></label>
            <label>Responsavel <input name="responsavel" maxlength="160" value="<?= e($formItem['responsavel'] ?? '') ?>"></label>
            <label>Status
                <select name="status"><?php foreach (['Nao iniciada','Em andamento','Bloqueada','Pausada','Concluida','Cancelada'] as $option): ?><option <?= ($formItem['status'] ?? 'Nao iniciada') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            </label>
        </div>
        <div class="form-grid two planning-relations">
            <fieldset class="predecessor-picker">
                <legend>Predecessoras</legend>
                <input type="search" data-predecessor-filter placeholder="Filtrar predecessoras" aria-label="Filtrar predecessoras">
                <div class="predecessor-options">
                    <?php foreach ($itemOptions as $option): ?>
                        <?php if (!$editingItem || (int) $option['id'] !== (int) $editingItem['id']): ?>
                            <label class="predecessor-option" data-predecessor-option>
                                <input type="checkbox" name="predecessor_ids[]" value="<?= e($option['id']) ?>" <?= in_array((int) $option['id'], $selectedPredecessors, true) ? 'checked' : '' ?>>
                                <span><?= e($option['wbs'] . ' ' . $option['nome']) ?></span>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <label>Descricao <textarea name="descricao" rows="6"><?= e($formItem['descricao'] ?? '') ?></textarea></label>
        </div>
        <div class="planning-editor-actions">
            <div><label class="checkbox"><input type="checkbox" name="marco" value="1" <?= !empty($formItem['marco']) ? 'checked' : '' ?>> Marco do projeto</label><?php if(!empty($mem['ativo'])): ?><label class="checkbox memorial-association"><input type="checkbox" name="memorial_link" value="1" <?= !empty($editingItemMemorialLink)?'checked':'' ?>> Associar este item ao Memorial de Calculo</label><?php endif; ?></div>
            <div class="form-actions"><button class="button" type="button" data-dialog-close>Cancelar</button><button class="button primary"><?= $editingItem ? 'Salvar alteracoes' : 'Inserir item' ?></button></div>
        </div>
        </form>
    </dialog>

    <?php if ($compositionItem): ?>
    <?php $resourceType = $editingResourceType ?? 'Material'; ?>
    <?php $resourceStatuses = [
        'Material' => ['Previsto','Solicitado','Comprado','Entregue','Cancelado'],
        'Servico' => ['Previsto','Cotando','Contratado','Em execucao','Medido','Concluido','Cancelado'],
        'Equipamento' => ['Previsto','Cotando','Contratado','Em uso','Devolvido','Cancelado'],
    ]; ?>
    <dialog class="planning-dialog composition-dialog" data-composition-dialog data-auto-open="<?= !empty($openCompositionDialog) ? 'true' : 'false' ?>" aria-labelledby="composition-dialog-title">
        <header class="planning-dialog-header">
            <div><span class="eyebrow">Composicao <?= e($compositionItem['wbs']) ?></span><h2 id="composition-dialog-title"><?= e($compositionItem['nome']) ?></h2><small class="muted"><?= e($sinapiLabel) ?> · <?= e(number_format((int) ($sinapiReference['total'] ?? 0), 0, ',', '.')) ?> opcoes</small></div>
            <button class="dialog-close" type="button" data-dialog-close aria-label="Fechar" data-tooltip="Fechar">&times;</button>
        </header>
        <form method="post" action="<?= $editingResource ? url('/obras/' . $obra['id'] . '/composicao/' . $editingResource['resource_id']) : url('/obras/' . $obra['id'] . '/composicao') ?>" class="compact-form planning-editor resource-composition-form" data-sinapi-form>
            <?= Csrf::field() ?>
            <input type="hidden" name="item_id" value="<?= e($compositionItem['id']) ?>">
            <?php if ($editingResource): ?><input type="hidden" name="resource_type" value="<?= e($resourceType) ?>"><?php endif; ?>
            <div class="form-grid two">
                <label>Tipo de recurso
                    <select <?= $editingResource ? 'disabled' : 'name="resource_type"' ?> data-resource-type>
                        <?php foreach (['Material','Servico','Equipamento'] as $typeOption): ?><option value="<?= e($typeOption) ?>" <?= $resourceType === $typeOption ? 'selected' : '' ?>><?= e($typeOption) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Fornecedor <input name="fornecedor_nome" list="composition-suppliers" autocomplete="off" value="<?= e($editingResource['fornecedor_nome'] ?? '') ?>"></label>
                <datalist id="composition-suppliers"><?php foreach ($fornecedores as $fornecedor): ?><option value="<?= e($fornecedor['nome']) ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="sinapi-combobox" data-sinapi-combobox data-type="<?= e($resourceType) ?>" data-endpoint="<?= url('/obras/sinapi/catalogo') ?>" <?= $editingResource ? 'data-sinapi-optional' : '' ?>>
                <label for="sinapi-composition-search">Item SINAPI</label>
                <div class="combobox-control"><input id="sinapi-composition-search" type="search" data-sinapi-search autocomplete="off" placeholder="Digite o codigo ou a descricao" <?= $editingResource ? '' : 'required' ?> role="combobox" aria-autocomplete="list" aria-expanded="false" value="<?= $editingResource ? e(trim((($editingResource['sinapi_codigo'] ?? '') ? $editingResource['sinapi_codigo'] . ' - ' : '') . $editingResource['nome'])) : '' ?>"><button type="button" data-sinapi-toggle aria-label="Abrir catalogo" data-tooltip="Abrir catalogo">&#9662;</button></div>
                <input type="hidden" name="sinapi_id" data-sinapi-id value="<?= e($editingResource['sinapi_id'] ?? '') ?>">
                <div class="combobox-options" data-sinapi-options role="listbox" hidden></div>
            </div>
            <div class="form-grid three composition-values">
                <label>Unidade <input data-sinapi-unit readonly value="<?= e($editingResource['unidade'] ?? '') ?>"></label>
                <label>Quantidade <input name="quantidade" data-sinapi-quantity type="number" min="0.001" step="0.001" value="<?= e($editingResource['quantidade'] ?? 1) ?>" required></label>
                <label>Preco unitario <input data-sinapi-unit-price type="number" step="0.0001" readonly value="<?= e($editingResource['valor_unitario'] ?? '') ?>"></label>
                <label>Valor previsto <input data-sinapi-total type="number" step="0.01" readonly value="<?= $editingResource ? e(number_format((float) $editingResource['quantidade'] * (float) $editingResource['valor_unitario'], 2, '.', '')) : '' ?>"></label>
            </div>
            <div class="form-grid two">
                <label>Status <select name="status" data-resource-status><?php foreach ($resourceStatuses[$resourceType] as $statusOption): ?><option <?= ($editingResource['status'] ?? 'Previsto') === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option><?php endforeach; ?></select></label>
                <label>Observacoes <textarea name="observacoes" rows="3"><?= e($editingResource['observacoes'] ?? '') ?></textarea></label>
            </div>
            <div class="planning-editor-actions"><div class="form-actions"><button class="button" type="button" data-dialog-close>Cancelar</button><button class="button primary"><?= $editingResource ? 'Salvar recurso' : 'Adicionar a composicao' ?></button></div></div>
        </form>
    </dialog>
    <?php endif; ?>

    <div class="planning-mode-panel" data-planning-panel="planning">
    <?php
    $compositionCounts=['Composta'=>0,'Existente'=>0,'Revisao manual'=>0,'Sem correspondencia'=>0];
    foreach($compositionAnalysis as $analysis) $compositionCounts[$analysis['status']] = ($compositionCounts[$analysis['status']]??0)+1;
    $compositionReview=array_values(array_filter($compositionAnalysis,static fn(array $analysis): bool => in_array($analysis['status'],['Revisao manual','Sem correspondencia'],true)));
    ?>
    <?php $maxPlanningLevel = $planningItems === [] ? 1 : max(array_map(static fn (array $item): int => (int) $item['depth'] + 1, $planningItems)); ?>
    <div class="planning-view-toolbar">
        <label>Visualizar niveis
            <select data-planning-level>
                <option value="">Todos os niveis</option>
                <?php for ($level = 1; $level <= $maxPlanningLevel; $level++): ?><option value="<?= $level ?>">Ate o nivel <?= $level ?></option><?php endfor; ?>
            </select>
        </label>
        <div class="planning-view-actions">
            <form method="post" action="<?= url('/obras/'.$obra['id'].'/planejamento/compor') ?>"><?= Csrf::field() ?><button class="button small primary" data-confirm="Gerar composicoes SINAPI para todas as tarefas sem recursos? Os recursos existentes serao preservados.">Compor com SINAPI</button></form>
            <details class="column-picker" data-column-picker>
                <summary class="button small" data-tooltip="Escolher colunas">Colunas</summary>
                <div class="column-picker-menu">
                    <?php foreach (['wbs'=>'EAP','item'=>'Item','status'=>'Status','duration'=>'Duracao','start'=>'Inicio','finish'=>'Termino','progress'=>'Progresso','responsible'=>'Responsavel','cost'=>'Custo previsto','predecessors'=>'Predecessoras','actions'=>'Acoes'] as $columnKey => $columnLabel): ?>
                        <label><input type="checkbox" value="<?= e($columnKey) ?>" data-column-toggle <?= in_array($columnKey, ['wbs','item','status','duration','start','finish','progress','predecessors','actions'], true) ? 'checked' : '' ?>> <?= e($columnLabel) ?></label>
                    <?php endforeach; ?>
                </div>
            </details>
            <button class="button small" type="button" data-tree-collapse-all>Recolher tudo</button>
            <button class="button small" type="button" data-tree-expand-all>Expandir tudo</button>
        </div>
    </div>

    <?php if($compositionAnalysis!==[]): ?>
        <div class="composition-audit">
            <span><strong><?= (int)$compositionCounts['Composta'] ?></strong> compostas</span>
            <span><strong><?= (int)$compositionCounts['Existente'] ?></strong> existentes</span>
            <span><strong><?= (int)($compositionCounts['Revisao manual']+$compositionCounts['Sem correspondencia']) ?></strong> para revisao</span>
            <?php if($compositionReview!==[]): ?><details><summary>Ver pendencias</summary><div><?php foreach($compositionReview as $analysis): ?><p><strong><?= e($analysis['item_nome']) ?></strong><small><?= e($analysis['justificativa']) ?></small></p><?php endforeach; ?></div></details><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="planning-root-drop" data-root-drop>Solte aqui para mover o item para a raiz do planejamento</div>
    <div class="planning-table-wrap responsive-table" data-column-storage="obra-planning-columns-<?= (int) $obra['id'] ?>">
        <table class="planning-table">
            <thead><tr><th data-column="wbs">EAP</th><th data-column="item">Item</th><th data-column="status">Status</th><th data-column="duration">Duracao</th><th data-column="start">Inicio</th><th data-column="finish">Termino</th><th data-column="progress">Progresso</th><th data-column="responsible">Responsavel</th><th data-column="cost">Custo previsto</th><th data-column="predecessors">Predecessoras</th><th data-column="actions">Acoes</th></tr></thead>
            <tbody>
            <?php if ($planningItems === []): ?><tr><td colspan="11" class="muted">Insira o primeiro item do planejamento.</td></tr><?php endif; ?>
            <?php foreach ($planningItems as $item): ?>
                <?php $resourceCounts = ['Material' => 0, 'Servico' => 0, 'Equipamento' => 0]; ?>
                <?php foreach ($item['resources'] as $resource): ?><?php $resourceCounts[$resource['tipo']]++; ?><?php endforeach; ?>
                <tr class="<?= (int) $item['children_count'] > 0 ? 'summary-item' : '' ?>" data-planning-row data-item-id="<?= (int) $item['id'] ?>" data-parent-id="<?= (int) ($item['parent_id'] ?? 0) ?>" data-depth="<?= (int) $item['depth'] ?>" data-move-endpoint="<?= url('/obras/' . $obra['id'] . '/itens/' . $item['id'] . '/move') ?>">
                    <td class="wbs-cell" data-column="wbs">
                        <div class="wbs-control">
                            <button class="drag-handle" type="button" draggable="true" aria-label="Mover item" data-drag-handle data-tooltip="Segure e arraste">&#8942;</button>
                            <?php if ((int) $item['children_count'] > 0): ?><button type="button" data-tree-toggle aria-expanded="true" title="Recolher <?= e($item['wbs']) ?>" aria-label="Recolher subitens de <?= e($item['wbs']) ?>">&#9662;</button><?php else: ?><span class="wbs-spacer"></span><?php endif; ?>
                            <span><?= e($item['wbs']) ?></span>
                        </div>
                    </td>
                    <td data-column="item">
                        <div class="tree-item" style="--tree-depth: <?= (int) $item['depth'] ?>">
                            <div class="tree-item-title"><strong><?= e($item['nome']) ?></strong><?php if ($item['marco']): ?><span>Marco</span><?php endif; ?></div>
                            <details class="planning-composition" <?= $item['resources'] !== [] ? 'open' : '' ?>>
                                    <summary>Composicao: <?= $resourceCounts['Material'] ?> mat. · <?= $resourceCounts['Servico'] ?> serv. · <?= $resourceCounts['Equipamento'] ?> equip.</summary>
                                    <div class="composition-resources">
                                        <?php if ($item['resources'] === []): ?><p class="composition-empty">Nenhum recurso adicionado.</p><?php endif; ?>
                                        <?php foreach ($item['resources'] as $resource): ?>
                                            <div class="composition-resource" data-resource-type="<?= e($resource['tipo']) ?>">
                                                <span><?= e($resource['tipo']) ?></span>
                                                <div><strong><?= e(trim(($resource['sinapi_codigo'] ?: '') . ' ' . $resource['nome'])) ?></strong><small><?= e(number_format((float) $resource['quantidade'], 3, ',', '.')) ?> <?= e($resource['unidade']) ?> · <?= e(money_br((float) $resource['valor_previsto'])) ?> · <?= e($resource['status']) ?></small></div>
                                                <div class="resource-actions">
                                                    <a class="resource-edit-icon" href="<?= url('/obras/' . $obra['id'] . '?editar_recurso_tipo=' . rawurlencode($resource['tipo']) . '&editar_recurso_id=' . $resource['resource_id'] . '#planejamento') ?>" aria-label="Editar recurso" data-tooltip="Editar recurso">&#9998;</a>
                                                    <form method="post" action="<?= url('/obras/' . $obra['id'] . '/composicao/' . $resource['resource_id'] . '/delete') ?>">
                                                        <?= Csrf::field() ?>
                                                        <input type="hidden" name="resource_type" value="<?= e($resource['tipo']) ?>">
                                                        <button class="resource-edit-icon danger" type="submit" aria-label="Excluir recurso" data-tooltip="Excluir recurso" data-confirm="Excluir este recurso da composicao?">&#128465;</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                        </div>
                    </td>
                    <td data-column="status"><?= e($item['status']) ?></td>
                    <td data-column="duration"><?= $item['duracao_prevista_dias'] !== null ? e($item['duracao_prevista_dias']) . ' dias' : '-' ?></td>
                    <td data-column="start"><?= e(date_br($item['data_inicio_prevista'])) ?></td>
                    <td data-column="finish"><?= e(date_br($item['data_fim_prevista'])) ?></td>
                    <td data-column="progress"><?= e($item['progresso']) ?>%</td>
                    <td data-column="responsible"><?= e($item['responsavel'] ?: '-') ?></td>
                    <td data-column="cost"><?= e(money_br((float) $item['custo_previsto'])) ?></td>
                    <td class="predecessor-cell" data-column="predecessors"><?= e(implode(', ', $item['predecessors'])) ?: '-' ?></td>
                    <td data-column="actions">
                        <div class="planning-actions">
                            <a class="planning-action-icon" href="<?= url('/obras/' . $obra['id'] . '?parent_item=' . $item['id'] . '#planejamento') ?>" aria-label="Inserir subitem" data-tooltip="Inserir subitem"><span aria-hidden="true">+</span></a>
                            <a class="planning-action-icon" href="<?= url('/obras/' . $obra['id'] . '?composicao_item=' . $item['id'] . '#planejamento') ?>" aria-label="Adicionar recurso a composicao" data-tooltip="Adicionar recurso"><span aria-hidden="true">&#9776;</span></a>
                            <a class="planning-action-icon" href="<?= url('/obras/' . $obra['id'] . '?editar_item=' . $item['id'] . '#planejamento') ?>" aria-label="Editar item" data-tooltip="Editar item"><span aria-hidden="true">&#9998;</span></a>
                            <form method="post" action="<?= url('/obras/' . $obra['id'] . '/itens/' . $item['id'] . '/delete') ?>">
                                <?= Csrf::field() ?>
                                <?php if(isset($memorialLinksByItem[$item['id']])): ?><input type="hidden" name="memorial_action" value="suppress"><?php endif; ?>
                                <button class="planning-action-icon danger" type="submit" aria-label="Remover item" data-tooltip="Remover item" data-confirm="<?= isset($memorialLinksByItem[$item['id']]) ? 'Este item esta ligado ao Memorial. Excluir e impedir que a regra o recrie no proximo calculo?' : 'Remover este item e todos os seus subitens?' ?>"><span aria-hidden="true">&#128465;</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="planning-bottom-actions">
        <strong>Arquivos do planejamento</strong>
        <div>
            <a class="button small" href="<?= url('/obras/' . $obra['id'] . '/planejamento/exportar?formato=pdf') ?>">Exportar PDF</a>
            <a class="button small" href="<?= url('/obras/' . $obra['id'] . '/planejamento/exportar?formato=xlsx') ?>">Exportar XLSX</a>
            <a class="button small" href="<?= url('/obras/' . $obra['id'] . '/planejamento/exportar?formato=csv') ?>">Exportar CSV</a>
            <button class="button small" type="button" data-dialog-open="planning-import-dialog">Importar planilha</button>
            <button class="button small primary" type="button" data-dialog-open="planning-model-dialog">Salvar como modelo</button>
        </div>
    </div>
    </div>

    <dialog id="planning-import-dialog" class="planning-dialog small-dialog" data-app-dialog aria-labelledby="planning-import-title">
        <header class="planning-dialog-header"><div><span class="eyebrow">Planejamento</span><h2 id="planning-import-title">Importar planilha</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Fechar">&times;</button></header>
        <form method="post" action="<?= url('/obras/' . $obra['id'] . '/planejamento/importar') ?>" enctype="multipart/form-data" class="compact-form planning-editor">
            <?= Csrf::field() ?>
            <label>Arquivo XLS, XLSX ou CSV <input type="file" name="arquivo" accept=".xls,.xlsx,.csv" required></label>
            <p class="muted">Use as colunas EAP e Item. A coluna EAP superior e reconhecida quando estiver presente.</p>
            <div class="form-actions"><button class="button" type="button" data-dialog-close>Cancelar</button><button class="button primary">Importar</button></div>
        </form>
    </dialog>

    <dialog id="planning-model-dialog" class="planning-dialog small-dialog" data-app-dialog aria-labelledby="planning-model-title">
        <header class="planning-dialog-header"><div><span class="eyebrow">Planejamento</span><h2 id="planning-model-title">Salvar como modelo</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Fechar">&times;</button></header>
        <form method="post" action="<?= url('/obras/' . $obra['id'] . '/modelos') ?>" class="compact-form planning-editor">
            <?= Csrf::field() ?>
            <label>Nome do modelo <input name="nome" maxlength="160" required></label>
            <label>Descricao <textarea name="descricao" rows="3"></textarea></label>
            <div class="form-actions"><button class="button" type="button" data-dialog-close>Cancelar</button><button class="button primary">Salvar modelo</button></div>
        </form>
    </dialog>
</section>

<section id="financeiro" class="obra-section panel obra-tab-panel" role="tabpanel" aria-labelledby="tab-financeiro" tabindex="0">
    <div class="section-head"><div><h2>Financeiro</h2><p class="muted">Movimentos de entrada e saida vinculados a itens e fornecedores.</p></div><div class="finance-kpis"><span>Entradas <?= e(money_br($finance['entradas'])) ?></span><span>Saldo <?= e(money_br($finance['saldo'])) ?></span></div></div>
    <div class="responsive-table"><table><thead><tr><th>Data</th><th>Descricao</th><th>Tipo</th><th>Categoria</th><th>Status</th><th>Valor</th></tr></thead><tbody>
        <?php foreach ($movements as $movement): ?><tr><td><?= e(date_br($movement['data_movimento'])) ?></td><td><?= e($movement['descricao']) ?><br><small><?= e($movement['fornecedor_nome'] ?: $movement['item_nome'] ?: '') ?></small></td><td><?= e($movement['tipo']) ?></td><td><?= e($movement['categoria']) ?></td><td><?= e($movement['status']) ?></td><td><?= e(money_br($movement['valor'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <form method="post" action="<?= url('/obras/' . $obra['id'] . '/financeiro') ?>" class="inline-create-form finance-form">
        <?= Csrf::field() ?><input name="descricao" required placeholder="Descricao"><input name="data_movimento" type="date" value="<?= e(date('Y-m-d')) ?>"><input name="valor" type="number" step="0.01" placeholder="Valor"><select name="tipo"><option>Saida</option><option>Entrada</option></select><select name="categoria"><?php foreach (['Material','Servico','Equipamento','Mao de obra','Taxa','Doacao','Outro'] as $option): ?><option><?= e($option) ?></option><?php endforeach; ?></select><select name="status"><?php foreach (['Previsto','Comprometido','Pago','Recebido','Cancelado'] as $option): ?><option><?= e($option) ?></option><?php endforeach; ?></select><input name="fornecedor_nome" placeholder="Fornecedor"><button class="button primary">Lancar</button>
    </form>
</section>

<section id="diario" class="obra-section panel obra-tab-panel" role="tabpanel" aria-labelledby="tab-diario" tabindex="0">
    <div class="section-head"><div><h2>Diario de obra</h2><p class="muted">Registro simples para documentar evolucao, equipe, clima e impedimentos.</p></div></div>
    <div class="diary-list"><?php foreach ($diary as $entry): ?><article><strong><?= e(date_br($entry['data_registro'])) ?> · <?= e($entry['titulo']) ?></strong><p><?= e($entry['relato']) ?></p><small><?= e($entry['impedimentos'] ?: '') ?></small></article><?php endforeach; ?></div>
    <form method="post" action="<?= url('/obras/' . $obra['id'] . '/diario') ?>" class="compact-form diary-form">
        <?= Csrf::field() ?><div class="form-grid two compact-two"><label>Data do registro <input type="date" name="data_registro" value="<?= e(date('Y-m-d')) ?>"></label><label>Titulo <input name="titulo" required></label></div><textarea name="relato" rows="4" required placeholder="O que aconteceu na obra hoje?"></textarea><div class="form-grid three"><input name="clima" placeholder="Clima"><input name="equipe_presente" placeholder="Equipe presente"><input name="impedimentos" placeholder="Impedimentos"></div><button class="button primary">Registrar diario</button>
    </form>
</section>
