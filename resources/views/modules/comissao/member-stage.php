<?php $isIndication = $mode === 'indicar'; ?>
<main class="member-area-shell member-stage-shell">
    <a class="member-back-link" href="<?= url('/comissao/membro') ?>">&larr; Voltar aos cargos</a>

    <header class="member-stage-page-header">
        <span class="eyebrow"><?= $isIndication ? 'Indicações abertas' : 'Votação aberta' ?></span>
        <h1><?= e($stage['cargo_nome']) ?></h1>
        <p><?= e($member['comissao_nome']) ?> · <?= e($member['nome']) ?></p>
    </header>

    <section class="member-stage-focus-card">
        <h2><?= $isIndication ? 'Indicar nomes' : 'Registrar voto' ?></h2>
        <?php if ($isIndication): ?>
            <p>Você poderá indicar até <strong><?= e($stage['limite_indicacoes']) ?></strong> nome(s) para este cargo.</p>

            <?php if ($indicationsFinished): ?>
                <div class="alert success member-indication-finished">Indicação encerrada. Suas escolhas foram registradas.</div>
            <?php else: ?>
                <div class="member-indication-counter">
                    <strong><?= e(count($indications)) ?> de <?= e($stage['limite_indicacoes']) ?></strong>
                    <span>nomes selecionados</span>
                </div>

                <?php if (count($indications) < (int) $stage['limite_indicacoes']): ?>
                    <form method="post" action="<?= url('/comissao/membro/indicacoes/' . $stage['voto_id'] . '/adicionar') ?>" class="form member-indication-form">
                        <?= \IasdCentral\Core\Csrf::field() ?>
                        <label>Pessoa cadastrada
                            <select name="pessoa_id" required <?= $availablePeople === [] ? 'disabled' : '' ?>>
                                <option value="">Selecione um nome</option>
                                <?php foreach ($availablePeople as $person): ?>
                                    <option value="<?= e($person['id']) ?>"><?= e($person['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="button primary" <?= $availablePeople === [] ? 'disabled' : '' ?>>Adicionar indicação</button>
                    </form>
                    <?php if ($availablePeople === []): ?>
                        <p class="empty-state">Nenhuma outra pessoa cadastrada está disponível.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <a class="button member-refresh-button" href="<?= url('/comissao/membro/indicacoes/' . $stage['voto_id']) ?>" aria-label="Atualizar pessoas cadastradas" title="Atualizar pessoas cadastradas">&#x1F504;</a>
            <?php endif; ?>

            <div class="member-indication-list">
                <div class="member-indication-list-head">
                    <strong>Suas indicações</strong>
                    <span><?= e(count($indications)) ?> nome(s)</span>
                </div>
                <?php if ($indications === []): ?>
                    <p class="empty-state">Você ainda não indicou nenhum nome.</p>
                <?php else: ?>
                    <?php foreach ($indications as $indication): ?>
                        <div class="member-indication-row">
                            <strong><?= e($indication['nome']) ?></strong>
                            <?php if (!$indicationsFinished): ?>
                                <form method="post" action="<?= url('/comissao/membro/indicacoes/' . $stage['voto_id'] . '/remover/' . $indication['id']) ?>">
                                    <?= \IasdCentral\Core\Csrf::field() ?>
                                    <button class="button small danger" data-confirm="Retirar este nome das suas indicações?">Retirar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!$indicationsFinished): ?>
                <form method="post" action="<?= url('/comissao/membro/indicacoes/' . $stage['voto_id'] . '/encerrar') ?>" class="member-finish-indication-form">
                    <?= \IasdCentral\Core\Csrf::field() ?>
                    <button class="button primary">Encerrar indicação</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p>Selecione até <strong><?= e($stage['limite_votos']) ?></strong> candidato(s) para este cargo.</p>
            <?php $confirmedChoices = $voteReceipt['choices'] ?? []; ?>
            <?php if ($voteReceipt !== null): ?>
                <div class="alert success member-vote-confirmed">Voto confirmado com sucesso.</div>
            <?php endif; ?>

            <?php if ($votingCandidates === []): ?>
                <p class="empty-state">Nenhum candidato está disponível para esta votação.</p>
            <?php else: ?>
                <form method="post" action="<?= url('/comissao/membro/votacoes/' . $stage['voto_id'] . '/confirmar') ?>" class="member-ballot" data-member-ballot data-limit="<?= e($stage['limite_votos']) ?>">
                    <?= \IasdCentral\Core\Csrf::field() ?>
                    <div class="member-ballot-counter">
                        <strong data-vote-counter><?= e(count($confirmedChoices)) ?></strong>
                        <span>de <?= e($stage['limite_votos']) ?> selecionado(s)</span>
                    </div>
                    <div class="member-vote-candidate-list">
                        <?php foreach ($votingCandidates as $candidate): ?>
                            <?php $selected = in_array((int) $candidate['pessoa_id'], $confirmedChoices, true); ?>
                            <label class="member-vote-option <?= $selected ? 'selected' : '' ?> <?= $voteReceipt !== null && !$selected ? 'not-selected' : '' ?>">
                                <input class="sr-only" type="checkbox" name="candidatos[]" value="<?= e($candidate['pessoa_id']) ?>" <?= $selected ? 'checked' : '' ?> <?= $voteReceipt !== null ? 'disabled' : '' ?>>
                                <span class="member-vote-check" aria-hidden="true"></span>
                                <strong><?= e($candidate['nome']) ?></strong>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($voteReceipt === null): ?>
                        <button class="button primary member-confirm-vote-button">Confirma</button>
                    <?php endif; ?>
                </form>
                <?php if ($voteReceipt === null): ?>
                    <dialog class="member-incomplete-vote-dialog" data-incomplete-vote-dialog aria-labelledby="incomplete-vote-title">
                        <h2 id="incomplete-vote-title">Confirmar voto</h2>
                        <p>Tem certeza que deseja encerrar, você ainda precisa escolher <strong data-missing-votes>0</strong> pessoa(s).</p>
                        <div class="member-incomplete-vote-actions">
                            <button class="button primary" type="button" data-confirm-incomplete-vote>Tenho certeza</button>
                            <button class="button" type="button" data-return-to-vote>Voltar ao voto</button>
                        </div>
                    </dialog>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
