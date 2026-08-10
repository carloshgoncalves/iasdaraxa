<?php use IasdCentral\Core\Csrf; ?>
<section class="obra-hero panel no-margin">
    <div>
        <span class="eyebrow">Gestao de obras</span>
        <h2>Obras e reformas da igreja</h2>
        <p class="muted">Controle escopo, cronograma, materiais, servicos contratados e financeiro de cada obra em um unico lugar.</p>
    </div>
    <div class="obra-kpis">
        <article><span>Ativas</span><strong><?= e($summary['ativas'] ?? 0) ?></strong></article>
        <article><span>Pendencias</span><strong><?= e($summary['pendencias'] ?? 0) ?></strong></article>
        <article><span>Contratos</span><strong><?= e($summary['contratos'] ?? 0) ?></strong></article>
    </div>
</section>

<section class="obra-layout">
    <div class="panel no-margin">
        <div class="section-head">
            <div>
                <h2>Portfolio</h2>
                <p class="muted">Acompanhe status, prazo, progresso e diferenca entre orcado e realizado.</p>
            </div>
        </div>
        <div class="obra-cards">
            <?php if ($obras === []): ?><p class="muted">Nenhuma obra cadastrada ainda.</p><?php endif; ?>
            <?php foreach ($obras as $obra): ?>
                <?php $percent = min(100, max(0, (float) $obra['progresso_itens'])); ?>
                <a class="obra-card" href="<?= url('/obras/' . $obra['id']) ?>">
                    <div class="obra-card-top">
                        <strong><?= e($obra['nome']) ?></strong>
                        <span class="status-badge"><?= e($obra['status']) ?></span>
                    </div>
                    <p><?= e($obra['local'] ?: 'Local nao informado') ?></p>
                    <div class="progress"><span style="width: <?= e($percent) ?>%"></span></div>
                    <div class="obra-card-meta">
                        <span><?= e(number_format($percent, 0, ',', '.')) ?>%</span>
                        <span><?= e(date_br($obra['data_inicio_prevista'])) ?> - <?= e(date_br($obra['data_fim_prevista'])) ?></span>
                    </div>
                    <div class="money-row"><span>Orcado</span><strong><?= e(money_br($obra['custo_previsto'])) ?></strong></div>
                    <div class="money-row"><span>Realizado</span><strong><?= e(money_br($obra['custo_real'])) ?></strong></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <aside class="panel no-margin">
        <h2>Nova obra</h2>
        <form method="post" action="<?= url('/obras') ?>" class="compact-form">
            <?= Csrf::field() ?>
            <label>Nome <input name="nome" required placeholder="Ex: Reforma do templo"></label>
            <label>Local <input name="local" placeholder="Templo, fachada, salas..."></label>
            <label>Modelo de planejamento
                <select name="modelo_id">
                    <option value="">Iniciar sem modelo</option>
                    <?php foreach ($planningModels as $model): ?><option value="<?= e($model['id']) ?>"><?= e($model['nome']) ?> (<?= e($model['item_count']) ?> itens)</option><?php endforeach; ?>
                </select>
            </label>
            <div class="form-grid two compact-two">
                <label>Status
                    <select name="status">
                        <?php foreach (['Planejada','Em andamento','Pausada','Concluida','Cancelada'] as $option): ?><option><?= e($option) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Prioridade
                    <select name="prioridade">
                        <?php foreach (['Baixa','Media','Alta','Critica'] as $option): ?><option><?= e($option) ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-grid two compact-two">
                <label>Data prevista de inicio <input type="date" name="data_inicio_prevista"></label>
                <label>Data prevista de conclusao <input type="date" name="data_fim_prevista"></label>
            </div>
            <label>Descricao <textarea name="descricao" rows="4"></textarea></label>
            <button class="button primary">Criar obra</button>
        </form>
    </aside>
</section>
