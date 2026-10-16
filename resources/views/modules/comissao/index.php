<?php

use IasdCentral\Core\Csrf;

$editing = $editing ?? null;
$isEdit = $editing !== null;
$formatCpf = static function (string $cpf): string {
    $digits = preg_replace('/\D+/', '', $cpf) ?? '';
    return strlen($digits) === 11
        ? substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2)
        : $cpf;
};
$situationLabels = ['aguardando' => 'Aguardando', 'iniciada' => 'Iniciada', 'finalizada' => 'Finalizada'];
?>
<section class="panel no-margin commission-head">
    <div>
        <span class="eyebrow">Comissão de nomeações</span>
        <h2>Cadastros básicos</h2>
        <p class="muted">Gerencie as pessoas, os períodos de trabalho e os cargos que poderão compor o processo de nomeações.</p>
    </div>
    <nav class="commission-nav" aria-label="Cadastros da comissão">
        <a href="<?= url('/comissao/comissoes') ?>" <?= $section === 'comissoes' ? 'aria-current="page"' : '' ?>>Comissões</a>
        <a href="<?= url('/comissao/pessoas') ?>" <?= $section === 'pessoas' ? 'aria-current="page"' : '' ?>>Pessoas</a>
        <a href="<?= url('/comissao/cargos') ?>" <?= $section === 'cargos' ? 'aria-current="page"' : '' ?>>Cargos</a>
    </nav>
</section>

<div class="commission-layout">
    <section class="panel no-margin registry-panel">
        <div class="registry-title">
            <div>
                <h2><?= e($title) ?></h2>
                <p class="muted"><?= e(count($records)) ?> registro(s)</p>
            </div>
        </div>

        <?php if ($records === []): ?>
            <p class="empty-state">Nenhum registro cadastrado até o momento.</p>
        <?php elseif ($section === 'pessoas'): ?>
            <div class="registry-list people-list">
                <div class="registry-header" aria-hidden="true"><span>Nome</span><span>CPF</span><span>Ações</span></div>
                <?php foreach ($records as $record): ?>
                    <article class="registry-item">
                        <div><small>Nome</small><strong><?= e($record['nome']) ?></strong></div>
                        <div><small>CPF</small><span><?= e($formatCpf((string) $record['cpf'])) ?></span></div>
                        <div class="registry-actions">
                            <a class="button small" href="<?= url('/comissao/pessoas/' . $record['id'] . '/editar') ?>">Editar</a>
                            <form method="post" action="<?= url('/comissao/pessoas/' . $record['id'] . '/excluir') ?>">
                                <?= Csrf::field() ?>
                                <button class="button small danger" data-confirm="Remover esta pessoa?">Excluir</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif ($section === 'comissoes'): ?>
            <div class="registry-list commissions-list">
                <div class="registry-header" aria-hidden="true"><span>Comissão</span><span>Período</span><span>Situação</span><span>Ações</span></div>
                <?php foreach ($records as $record): ?>
                    <article class="registry-item">
                        <div><small>Comissão</small><strong><?= e($record['nome']) ?></strong></div>
                        <div><small>Período</small><span><?= e(date_br($record['data_inicio'])) ?> a <?= e(date_br($record['data_fim'])) ?></span></div>
                        <div><small>Situação</small><span class="status-badge status-<?= e($record['situacao']) ?>"><?= e($situationLabels[$record['situacao']] ?? $record['situacao']) ?></span></div>
                        <div class="registry-actions">
                            <a class="button small" href="<?= url('/comissao/comissoes/' . $record['id'] . '/editar') ?>">Editar</a>
                            <form method="post" action="<?= url('/comissao/comissoes/' . $record['id'] . '/excluir') ?>">
                                <?= Csrf::field() ?>
                                <button class="button small danger" data-confirm="Remover esta comissão?">Excluir</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="registry-list offices-list">
                <div class="registry-header" aria-hidden="true"><span>Cargo</span><span>Status</span><span>Ações</span></div>
                <?php foreach ($records as $record): ?>
                    <article class="registry-item">
                        <div><small>Cargo</small><strong><?= e($record['nome']) ?></strong></div>
                        <div><small>Status</small><span class="status-badge status-<?= e($record['status']) ?>"><?= e(ucfirst($record['status'])) ?></span></div>
                        <div class="registry-actions">
                            <a class="button small" href="<?= url('/comissao/cargos/' . $record['id'] . '/editar') ?>">Editar</a>
                            <form method="post" action="<?= url('/comissao/cargos/' . $record['id'] . '/excluir') ?>">
                                <?= Csrf::field() ?>
                                <button class="button small danger" data-confirm="Remover este cargo?">Excluir</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="panel no-margin registry-form-panel">
        <h2><?= e($isEdit ? 'Editar cadastro' : 'Novo cadastro') ?></h2>
        <?php if ($section === 'pessoas'): ?>
            <form method="post" action="<?= $isEdit ? url('/comissao/pessoas/' . $editing['id']) : url('/comissao/pessoas') ?>" class="form">
                <?= Csrf::field() ?>
                <label>Nome
                    <input name="nome" maxlength="160" required autocomplete="name" value="<?= e($editing['nome'] ?? '') ?>">
                </label>
                <label>CPF
                    <input name="cpf" maxlength="14" inputmode="numeric" autocomplete="off" data-cpf-mask required placeholder="000.000.000-00" value="<?= e(isset($editing['cpf']) ? $formatCpf((string) $editing['cpf']) : '') ?>">
                </label>
                <div class="form-actions">
                    <button class="button primary"><?= e($isEdit ? 'Salvar alterações' : 'Cadastrar pessoa') ?></button>
                    <?php if ($isEdit): ?><a class="button" href="<?= url('/comissao/pessoas') ?>">Cancelar</a><?php endif; ?>
                </div>
            </form>
        <?php elseif ($section === 'comissoes'): ?>
            <form method="post" action="<?= $isEdit ? url('/comissao/comissoes/' . $editing['id']) : url('/comissao/comissoes') ?>" class="form">
                <?= Csrf::field() ?>
                <label>Nome da comissão
                    <input name="nome" maxlength="160" required placeholder="Ex: Nomeações 2027" value="<?= e($editing['nome'] ?? '') ?>">
                </label>
                <div class="form-grid two">
                    <label>Data de início
                        <input type="date" name="data_inicio" required value="<?= e($editing['data_inicio'] ?? '') ?>">
                    </label>
                    <label>Data de fim
                        <input type="date" name="data_fim" required value="<?= e($editing['data_fim'] ?? '') ?>">
                    </label>
                </div>
                <label>Situação
                    <select name="situacao" required>
                        <?php foreach ($situationLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($editing['situacao'] ?? 'aguardando') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="form-actions">
                    <button class="button primary"><?= e($isEdit ? 'Salvar alterações' : 'Cadastrar comissão') ?></button>
                    <?php if ($isEdit): ?><a class="button" href="<?= url('/comissao/comissoes') ?>">Cancelar</a><?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <form method="post" action="<?= $isEdit ? url('/comissao/cargos/' . $editing['id']) : url('/comissao/cargos') ?>" class="form">
                <?= Csrf::field() ?>
                <label>Nome do cargo
                    <input name="nome" maxlength="180" required placeholder="Ex: Diretor de Comunicação" value="<?= e($editing['nome'] ?? '') ?>">
                </label>
                <label>Status
                    <select name="status" required>
                        <option value="ativo" <?= ($editing['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($editing['status'] ?? 'ativo') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </label>
                <p class="form-help">A lista inicial segue os cargos sugeridos no Manual da Igreja. Novos cargos podem ser incluídos normalmente.</p>
                <div class="form-actions">
                    <button class="button primary"><?= e($isEdit ? 'Salvar alterações' : 'Cadastrar cargo') ?></button>
                    <?php if ($isEdit): ?><a class="button" href="<?= url('/comissao/cargos') ?>">Cancelar</a><?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </aside>
</div>
