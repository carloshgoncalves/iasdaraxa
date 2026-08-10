<?php
use IasdCentral\Core\Csrf;

$isEdit = $event !== null;
$action = $isEdit ? url('/agenda/' . $event['id']) : url('/agenda');
$dataInicio = $event['data_inicio'] ?? $selectedDate;
?>
<section class="panel no-margin agenda-form-shell">
    <div class="form-header">
        <div>
            <span class="eyebrow">Agenda geral</span>
            <h2><?= e($isEdit ? 'Editar evento' : 'Novo evento') ?></h2>
            <p class="muted">Cada evento e uma ocorrencia independente. Pode haver varias atividades no mesmo dia.</p>
        </div>
        <a class="button" href="<?= url('/agenda?mes=' . substr((string) $dataInicio, 0, 7)) ?>">Voltar</a>
    </div>

    <form method="post" action="<?= $action ?>" class="form agenda-form">
        <?= Csrf::field() ?>
        <div class="form-grid two">
            <label>Atividade
                <select name="atividade_id">
                    <option value="">Sem atividade vinculada</option>
                    <?php foreach ($activities as $activity): ?>
                        <option value="<?= e($activity['id']) ?>" <?= (int) ($event['atividade_id'] ?? 0) === (int) $activity['id'] ? 'selected' : '' ?>><?= e($activity['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Titulo <input name="titulo" required maxlength="180" value="<?= e($event['titulo'] ?? '') ?>" placeholder="Ex: Culto Divino"></label>
        </div>

        <label>Descricao <textarea name="descricao" rows="4" placeholder="Detalhes importantes para lideranca, comunicacao e escala."><?= e($event['descricao'] ?? '') ?></textarea></label>

        <div class="form-grid four">
            <label>Data de inicio <input type="date" name="data_inicio" required value="<?= e($dataInicio) ?>"></label>
            <label>Hora de inicio <input type="time" name="hora_inicio" value="<?= e(substr((string) ($event['hora_inicio'] ?? ''), 0, 5)) ?>"></label>
            <label>Data de termino <input type="date" name="data_fim" value="<?= e($event['data_fim'] ?? '') ?>"></label>
            <label>Hora de termino <input type="time" name="hora_fim" value="<?= e(substr((string) ($event['hora_fim'] ?? ''), 0, 5)) ?>"></label>
        </div>

        <label class="checkbox"><input type="checkbox" name="dia_todo" value="1" <?= !empty($event['dia_todo']) ? 'checked' : '' ?>> Evento de dia todo</label>

        <div class="form-grid two">
            <label>Local <input name="local" maxlength="160" value="<?= e($event['local'] ?? '') ?>" placeholder="Templo, sala jovem, quadra..."></label>
            <label>Responsavel <input name="responsavel" maxlength="160" value="<?= e($event['responsavel'] ?? '') ?>" placeholder="Ministerio ou pessoa responsavel"></label>
        </div>

        <div class="form-grid two">
            <label>Visibilidade
                <select name="visibilidade">
                    <?php foreach (['Interna', 'Publica'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= ($event['visibilidade'] ?? 'Interna') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Status
                <select name="status">
                    <?php foreach (['Agendada', 'Confirmada', 'Cancelada', 'Concluida'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= ($event['status'] ?? 'Agendada') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label>Observacoes internas <textarea name="observacoes" rows="3"><?= e($event['observacoes'] ?? '') ?></textarea></label>

        <div class="form-actions split-actions">
            <button class="button primary"><?= e($isEdit ? 'Salvar alteracoes' : 'Cadastrar evento') ?></button>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <form method="post" action="<?= url('/agenda/' . $event['id'] . '/delete') ?>" class="delete-form">
            <?= Csrf::field() ?>
            <button class="button danger" data-confirm="Remover este evento da agenda?">Remover evento</button>
        </form>
    <?php endif; ?>
</section>
