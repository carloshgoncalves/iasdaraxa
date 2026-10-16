<?php

use IasdCentral\Core\Csrf;

$editing = $editing ?? null;
$isEdit = $editing !== null;
$situationLabels = ['aguardando' => 'Aguardando', 'iniciada' => 'Iniciada', 'finalizada' => 'Finalizada'];
$sectionConfig = match ($section) {
    'pessoas' => [
        'singular' => 'pessoa',
        'new_label' => 'Nova pessoa',
        'create_action' => url('/comissao/pessoas'),
    ],
    'cargos' => [
        'singular' => 'cargo',
        'new_label' => 'Novo cargo',
        'create_action' => url('/comissao/cargos'),
    ],
    default => [
        'singular' => 'comissão',
        'new_label' => 'Nova comissão',
        'create_action' => url('/comissao/comissoes'),
    ],
};
$formAction = $isEdit ? match ($section) {
    'pessoas' => url('/comissao/pessoas/' . $editing['id']),
    'cargos' => url('/comissao/cargos/' . $editing['id']),
    default => url('/comissao/comissoes/' . $editing['id']),
} : $sectionConfig['create_action'];
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
            <button
                class="button primary"
                type="button"
                data-dialog-open="commission-record-dialog"
                data-form-action="<?= e($sectionConfig['create_action']) ?>"
                data-dialog-title="<?= e($sectionConfig['new_label']) ?>"
                data-submit-label="Salvar"
                data-record-nome=""
                data-record-data-inicio=""
                data-record-data-fim=""
                data-record-situacao="aguardando"
                data-record-status="ativo"
            ><?= e($sectionConfig['new_label']) ?></button>
        </div>

        <?php if ($records === []): ?>
            <p class="empty-state">Nenhum registro cadastrado até o momento.</p>
        <?php elseif ($section === 'pessoas'): ?>
            <div class="registry-list people-list">
                <div class="registry-header" aria-hidden="true"><span>Nome</span><span>Ações</span></div>
                <?php foreach ($records as $record): ?>
                    <article class="registry-item">
                        <div><small>Nome</small><strong><?= e($record['nome']) ?></strong></div>
                        <div class="registry-actions">
                            <button
                                class="button small"
                                type="button"
                                data-dialog-open="commission-record-dialog"
                                data-form-action="<?= e(url('/comissao/pessoas/' . $record['id'])) ?>"
                                data-dialog-title="Editar pessoa"
                                data-submit-label="Salvar alterações"
                                data-record-nome="<?= e($record['nome']) ?>"
                            >Editar</button>
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
                            <?php if ($record['situacao'] === 'aguardando'): ?>
                                <form method="post" action="<?= url('/comissao/comissoes/' . $record['id'] . '/iniciar') ?>">
                                    <?= Csrf::field() ?>
                                    <button class="button small primary">Iniciar</button>
                                </form>
                            <?php else: ?>
                                <a class="button small primary" href="<?= url('/comissao/comissoes/' . $record['id'] . '/cargos') ?>"><?= e($record['situacao'] === 'finalizada' ? 'Ver cargos' : 'Abrir') ?></a>
                            <?php endif; ?>
                            <button
                                class="button small"
                                type="button"
                                data-dialog-open="commission-record-dialog"
                                data-form-action="<?= e(url('/comissao/comissoes/' . $record['id'])) ?>"
                                data-dialog-title="Editar comissão"
                                data-submit-label="Salvar alterações"
                                data-record-nome="<?= e($record['nome']) ?>"
                                data-record-data-inicio="<?= e($record['data_inicio']) ?>"
                                data-record-data-fim="<?= e($record['data_fim']) ?>"
                                data-record-situacao="<?= e($record['situacao']) ?>"
                            >Editar</button>
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
                            <button
                                class="button small"
                                type="button"
                                data-dialog-open="commission-record-dialog"
                                data-form-action="<?= e(url('/comissao/cargos/' . $record['id'])) ?>"
                                data-dialog-title="Editar cargo"
                                data-submit-label="Salvar alterações"
                                data-record-nome="<?= e($record['nome']) ?>"
                                data-record-status="<?= e($record['status']) ?>"
                            >Editar</button>
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
</div>

<dialog
    id="commission-record-dialog"
    class="planning-dialog small-dialog registry-dialog"
    data-app-dialog
    data-record-dialog
    data-auto-open="<?= $isEdit ? 'true' : 'false' ?>"
    aria-labelledby="commission-record-dialog-title"
>
    <header class="planning-dialog-header">
        <div>
            <span class="eyebrow">Comissão de nomeações</span>
            <h2 id="commission-record-dialog-title" data-record-dialog-title><?= e($isEdit ? 'Editar ' . $sectionConfig['singular'] : $sectionConfig['new_label']) ?></h2>
        </div>
        <button class="dialog-close" type="button" data-dialog-close aria-label="Fechar">&times;</button>
    </header>
    <form method="post" action="<?= e($formAction) ?>" class="form planning-editor" data-record-form>
        <?= Csrf::field() ?>

        <?php if ($section === 'pessoas'): ?>
            <label>Nome
                <input name="nome" maxlength="160" required autocomplete="name" autofocus value="<?= e($editing['nome'] ?? '') ?>">
            </label>
        <?php elseif ($section === 'comissoes'): ?>
            <label>Nome da comissão
                <input name="nome" maxlength="160" required autofocus placeholder="Ex: Nomeações 2027" value="<?= e($editing['nome'] ?? '') ?>">
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
        <?php else: ?>
            <label>Nome do cargo
                <input name="nome" maxlength="180" required autofocus placeholder="Ex: Diretor de Comunicação" value="<?= e($editing['nome'] ?? '') ?>">
            </label>
            <label>Status
                <select name="status" required>
                    <option value="ativo" <?= ($editing['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= ($editing['status'] ?? 'ativo') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </label>
            <p class="form-help">A lista inicial segue os cargos sugeridos no Manual da Igreja. Novos cargos podem ser incluídos normalmente.</p>
        <?php endif; ?>

        <div class="form-actions">
            <button class="button" type="button" data-dialog-close>Cancelar</button>
            <button class="button primary" data-record-submit><?= e($isEdit ? 'Salvar alterações' : 'Salvar') ?></button>
        </div>
    </form>
</dialog>
