<?php
use IasdCentral\Core\Csrf;

$monthTime = strtotime($month . '-01');
$prevMonth = date('Y-m', strtotime('-1 month', $monthTime));
$nextMonth = date('Y-m', strtotime('+1 month', $monthTime));
$monthLabel = strtr(date('F Y', $monthTime), [
    'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Marco', 'April' => 'Abril',
    'May' => 'Maio', 'June' => 'Junho', 'July' => 'Julho', 'August' => 'Agosto',
    'September' => 'Setembro', 'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro',
]);
$weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
?>
<section class="agenda-toolbar panel no-margin">
    <div>
        <span class="eyebrow">Agenda geral</span>
        <h2><?= e($monthLabel) ?></h2>
        <p class="muted">Cadastre varias atividades no mesmo dia e deixe cada evento pronto para receber escala depois.</p>
    </div>
    <div class="toolbar-actions">
        <a class="button" href="<?= url('/agenda?mes=' . $prevMonth) ?>">Mes anterior</a>
        <a class="button" href="<?= url('/agenda?mes=' . date('Y-m')) ?>">Hoje</a>
        <a class="button" href="<?= url('/agenda?mes=' . $nextMonth) ?>">Proximo mes</a>
        <a class="button primary" href="<?= url('/agenda/create') ?>">Novo evento</a>
    </div>
</section>

<section class="grid cards-3 agenda-summary">
    <article class="card"><span>Eventos no mes</span><strong><?= e($summary['eventos'] ?? 0) ?></strong></article>
    <article class="card"><span>Publicos</span><strong><?= e($summary['publicos'] ?? 0) ?></strong></article>
    <article class="card"><span>Precisam de escala</span><strong><?= e($summary['com_escala'] ?? 0) ?></strong></article>
</section>

<section class="agenda-layout">
    <div class="agenda-calendar panel">
        <div class="calendar-weekdays">
            <?php foreach ($weekdays as $weekday): ?><span><?= e($weekday) ?></span><?php endforeach; ?>
        </div>
        <div class="calendar-grid">
            <?php foreach ($calendar as $day): ?>
                <?php if ($day === null): ?>
                    <div class="calendar-day empty"></div>
                <?php else: ?>
                    <div class="calendar-day <?= $day['date'] === date('Y-m-d') ? 'today' : '' ?>">
                        <div class="day-head">
                            <strong><?= e($day['day']) ?></strong>
                            <a href="<?= url('/agenda/create?data=' . $day['date']) ?>">+</a>
                        </div>
                        <div class="day-events">
                            <?php foreach ($day['events'] as $event): ?>
                                <a class="event-pill status-<?= e(strtolower($event['status'])) ?>" href="<?= url('/agenda/' . $event['id'] . '/edit') ?>" style="--event-color: <?= e($event['atividade_cor'] ?: '#7a1f38') ?>">
                                    <span><?= e($event['dia_todo'] ? 'Dia todo' : substr((string) $event['hora_inicio'], 0, 5)) ?></span>
                                    <?= e($event['titulo']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <aside class="agenda-side">
        <section class="panel no-margin">
            <h2>Proximos eventos</h2>
            <div class="upcoming-list">
                <?php if ($upcoming === []): ?><p class="muted">Nenhum evento futuro cadastrado.</p><?php endif; ?>
                <?php foreach ($upcoming as $event): ?>
                    <a class="upcoming-item" href="<?= url('/agenda/' . $event['id'] . '/edit') ?>">
                        <span class="swatch" style="background: <?= e($event['atividade_cor'] ?: '#7a1f38') ?>"></span>
                        <div>
                            <strong><?= e($event['titulo']) ?></strong>
                            <small><?= e(date_br($event['data_inicio'])) ?> <?= e($event['dia_todo'] ? 'Dia todo' : substr((string) $event['hora_inicio'], 0, 5)) ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <h2>Nova atividade</h2>
            <form method="post" action="<?= url('/agenda/atividades') ?>" class="compact-form">
                <?= Csrf::field() ?>
                <label>Nome <input name="nome" required placeholder="Ex: Culto de domingo"></label>
                <label>Cor <input type="color" name="cor" value="#7a1f38"></label>
                <label>Duração <input type="number" name="duracao_padrao_minutos" min="5" step="5" value="60"></label>
                <label class="checkbox"><input type="checkbox" name="exige_escala" value="1"> Exige escala</label>
                <input type="hidden" name="status" value="Ativa">
                <label>Descrição <textarea name="descricao" rows="3"></textarea></label>
                <button class="button primary">Salvar atividade</button>
            </form>
        </section>

        <section class="panel">
            <h2>Atividades</h2>
            <div class="activity-list">
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-chip"><span class="swatch" style="background: <?= e($activity['cor']) ?>"></span><?= e($activity['nome']) ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    </aside>
</section>
