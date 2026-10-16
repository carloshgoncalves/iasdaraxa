<?php
use IasdCentral\Core\Csrf;

$memberRoleLabels = ['presidente' => 'Presidente', 'secretario' => 'Secretário', 'membro' => 'Membro'];
?>
<main class="member-area-shell">
    <header class="member-area-header">
        <div>
            <span class="eyebrow">Comissão de nomeações</span>
            <h1><?= e($member['comissao_nome']) ?></h1>
            <strong class="member-header-name"><?= e($member['nome']) ?></strong>
            <p><?= e($memberRoleLabels[$member['papel']] ?? $member['papel']) ?> · <?= e(date_br($member['data_inicio'])) ?> a <?= e(date_br($member['data_fim'])) ?></p>
        </div>
        <form method="post" action="<?= url('/comissao/membro/sair') ?>">
            <?= Csrf::field() ?>
            <button class="button small">Sair</button>
        </form>
    </header>

    <section class="member-stages-section">
        <div class="member-section-title">
            <div>
                <span class="eyebrow">Indicações e votos</span>
                <h2>Cargos da comissão</h2>
            </div>
            <span><?= e(count($stages)) ?> cargo(s)</span>
        </div>

        <?php if ($stages === []): ?>
            <p class="empty-state">Nenhum cargo está disponível nesta comissão.</p>
        <?php else: ?>
            <div class="member-stage-list">
                <?php foreach ($stages as $stage): ?>
                    <?php
                    $stageClass = 'waiting';
                    $stageLabel = 'Aguardando';
                    $stageMessage = 'A comissão ainda não abriu as indicações para este cargo.';
                    if ($stage['votacao_status'] === 'encerrada') {
                        $stageClass = 'closed';
                        $stageLabel = 'Votação encerrada';
                        $stageMessage = 'A votação deste cargo foi concluída.';
                    } elseif ($stage['etapa_status'] === 'indicacao') {
                        $stageClass = 'indication';
                        $stageLabel = 'Indicações abertas';
                        $stageMessage = 'Você poderá indicar até ' . (int) $stage['limite_indicacoes'] . ' nome(s).';
                    } elseif ($stage['etapa_status'] === 'votando') {
                        $stageClass = 'voting';
                        $stageLabel = 'Votação aberta';
                        $stageMessage = 'Você poderá registrar até ' . (int) $stage['limite_votos'] . ' voto(s).';
                    } elseif ($stage['etapa_status'] === 'encerrado') {
                        $stageClass = 'paused';
                        $stageLabel = 'Indicações encerradas';
                        $stageMessage = 'Aguarde a abertura da votação.';
                    }
                    ?>
                    <article class="member-stage-card stage-<?= e($stageClass) ?>">
                        <div>
                            <strong><?= e($stage['cargo_nome']) ?></strong>
                            <span class="member-stage-status"><?= e($stageLabel) ?></span>
                            <p><?= e($stageMessage) ?></p>
                        </div>
                        <?php if ($stageClass === 'indication'): ?>
                            <a class="button primary member-stage-action" href="<?= url('/comissao/membro/indicacoes/' . $stage['voto_id']) ?>">Indicar</a>
                        <?php elseif ($stageClass === 'voting'): ?>
                            <a class="button primary member-stage-action" href="<?= url('/comissao/membro/votacoes/' . $stage['voto_id']) ?>">Votar</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
