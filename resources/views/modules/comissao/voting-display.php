<main class="live-voting-display-shell" data-live-voting-display>
    <header class="live-voting-display-header">
        <div>
            <span class="eyebrow">Comissão de nomeações</span>
            <h1><?= e($commission['nome']) ?></h1>
            <p><?= e(date_br($commission['data_inicio'])) ?> a <?= e(date_br($commission['data_fim'])) ?></p>
        </div>
        <a class="button live-voting-refresh" href="<?= url('/comissao/comissoes/' . $commission['id'] . '/painel-votacao') ?>">
            <span aria-hidden="true">&#x1F504;</span> Atualizar
        </a>
    </header>

    <?php if ($presentation === null): ?>
        <section class="live-voting-waiting">
            <span class="live-voting-status waiting">Aguardando</span>
            <h2>Nenhuma etapa está aberta no momento</h2>
            <p>Assim que o administrador abrir indicações ou uma votação, as informações aparecerão nesta tela.</p>
        </section>
    <?php else: ?>
        <section class="live-voting-current">
            <div class="live-voting-title">
                <?php if ($presentation['display_mode'] === 'indications'): ?>
                    <span class="live-voting-status indications">
                        <i aria-hidden="true"></i>
                        <?= $presentation['etapa_status'] === 'indicacao' ? 'Indicações abertas' : 'Indicações encerradas' ?>
                    </span>
                <?php elseif ($presentation['display_mode'] === 'voting'): ?>
                    <span class="live-voting-status active"><i aria-hidden="true"></i> Em votação</span>
                <?php else: ?>
                    <span class="live-voting-status result">Votação encerrada</span>
                <?php endif; ?>

                <h2><?= e($presentation['cargo_nome']) ?></h2>
                <p>
                    <?php if ($presentation['display_mode'] === 'indications'): ?>
                        Nomes indicados até o momento
                    <?php elseif ($presentation['display_mode'] === 'voting'): ?>
                        Candidatos apresentados para este cargo
                    <?php else: ?>
                        Resultado da votação
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($items === []): ?>
                <p class="empty-state">
                    <?= $presentation['display_mode'] === 'indications'
                        ? 'Nenhum nome foi indicado até o momento.'
                        : 'Nenhum candidato foi disponibilizado para esta votação.' ?>
                </p>
            <?php elseif ($presentation['display_mode'] === 'result'): ?>
                <?php
                $roleLabels = [
                    'votado' => 'Votado',
                    'secretario' => 'Secretário',
                    'associado' => 'Associado',
                ];
                ?>
                <div class="live-voting-result-list">
                    <?php foreach ($items as $item): ?>
                        <article class="live-voting-result-card">
                            <strong><?= e($item['nome']) ?></strong>
                            <div class="live-voting-result-details">
                                <span class="live-voting-result-votes">
                                    <?= e((string) $item['votos']) ?> <?= (int) $item['votos'] === 1 ? 'voto' : 'votos' ?>
                                </span>
                                <?php if (isset($roleLabels[$item['papel']])): ?>
                                    <span class="live-voting-result-badge role-<?= e($item['papel']) ?>">
                                        <?= e($roleLabels[$item['papel']]) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="live-voting-candidate-list">
                    <?php foreach ($items as $item): ?>
                        <article><strong><?= e($item['nome']) ?></strong></article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <footer class="live-voting-display-footer">
        <span>Atualização automática a cada 30 segundos</span>
        <small>Atualizado às <?= e(date('H:i:s')) ?></small>
    </footer>
</main>
