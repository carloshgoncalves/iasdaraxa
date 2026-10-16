<?php

use IasdCentral\Core\Csrf;

$situationLabels = ['aguardando' => 'Aguardando', 'iniciada' => 'Iniciada', 'finalizada' => 'Finalizada'];
$roleLabels = ['votado' => 'Votado', 'associado' => 'Associado', 'candidato' => 'Candidato', 'secretario' => 'Secretário'];
$voteFlowLabels = ['aguardando' => 'Aguardando', 'indicacao' => 'Indicações abertas', 'votando' => 'Em votação', 'encerrado' => 'Encerrado'];
$memberRoleLabels = ['presidente' => 'Presidente', 'secretario' => 'Secretário', 'membro' => 'Membro'];
?>
<section class="panel no-margin commission-office-head">
    <div>
        <span class="eyebrow">Comissão de nomeações</span>
        <h2><?= e($commission['nome']) ?></h2>
        <div class="commission-office-meta">
            <span><?= e(date_br($commission['data_inicio'])) ?> a <?= e(date_br($commission['data_fim'])) ?></span>
            <span class="status-badge status-<?= e($commission['situacao']) ?>"><?= e($situationLabels[$commission['situacao']] ?? $commission['situacao']) ?></span>
        </div>
    </div>
    <a class="button" href="<?= url('/comissao/comissoes') ?>">Voltar às comissões</a>
</section>

<section class="panel commission-office-panel">
    <div class="registry-title">
        <div>
            <h2>Cargos que serão votados</h2>
            <p class="muted"><?= e(count($offices)) ?> cargo(s) nesta comissão</p>
        </div>
        <div class="registry-title-actions">
            <a class="button" href="<?= url('/comissao/comissoes/' . $commission['id'] . '/painel-votacao') ?>" target="_blank" rel="noopener">Tela de votação</a>
            <button class="button" type="button" data-dialog-open="commission-members-dialog">Membros</button>
            <?php if ($editable): ?>
                <button class="button primary" type="button" data-dialog-open="commission-office-dialog">Adicionar cargo</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$editable): ?>
        <div class="alert commission-readonly">Esta comissão está finalizada. Os cargos estão disponíveis apenas para consulta.</div>
    <?php endif; ?>

    <?php if ($offices === []): ?>
        <p class="empty-state">Nenhum cargo foi incluído nesta comissão.</p>
    <?php else: ?>
        <div class="registry-list commission-office-list">
            <div class="registry-header" aria-hidden="true"><span>Ordem e cargo</span><span>Ações</span></div>
            <?php foreach ($offices as $officeIndex => $office): ?>
                <article class="registry-item">
                    <div>
                        <small>Cargo</small>
                        <span class="office-order-number" aria-label="Posição <?= e($officeIndex + 1) ?>"><?= e($officeIndex + 1) ?></span>
                        <strong><?= e($office['nome']) ?></strong>
                        <?php if ($office['status'] !== 'ativo'): ?><span class="status-badge status-inativo">Inativo no cadastro geral</span><?php endif; ?>
                    </div>
                    <div class="registry-actions">
                        <?php if ($office['votacao_status'] === 'encerrada'): ?>
                            <span class="vote-flag">Votado</span>
                        <?php endif; ?>
                        <a class="button small <?= $office['votacao_status'] === 'aberta' && $editable ? 'primary' : '' ?>" href="<?= url('/comissao/comissoes/' . $commission['id'] . '/cargos') ?>?votacao=<?= e($office['comissao_cargo_id']) ?>">
                            <?= $office['votacao_status'] === 'encerrada' || !$editable ? 'VER' : 'Votar' ?>
                        </a>
                        <?php if ($editable): ?>
                            <div class="office-order-actions" role="group" aria-label="Alterar ordem de <?= e($office['nome']) ?>">
                                <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/cargos/' . $office['id'] . '/mover') ?>">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="direcao" value="up">
                                    <button class="button small order-button" aria-label="Mover <?= e($office['nome']) ?> para cima" title="Mover para cima" <?= $officeIndex === 0 ? 'disabled' : '' ?>>&#8593;</button>
                                </form>
                                <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/cargos/' . $office['id'] . '/mover') ?>">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="direcao" value="down">
                                    <button class="button small order-button" aria-label="Mover <?= e($office['nome']) ?> para baixo" title="Mover para baixo" <?= $officeIndex === count($offices) - 1 ? 'disabled' : '' ?>>&#8595;</button>
                                </form>
                            </div>
                            <?php if ($office['votacao_status'] !== 'encerrada'): ?>
                                <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/cargos/' . $office['id'] . '/excluir') ?>">
                                    <?= Csrf::field() ?>
                                    <button class="button small danger" data-confirm="Retirar este cargo somente desta comissão?">Retirar</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($editable): ?>
    <dialog id="commission-office-dialog" class="planning-dialog small-dialog registry-dialog" data-app-dialog aria-labelledby="commission-office-dialog-title">
        <header class="planning-dialog-header">
            <div>
                <span class="eyebrow">Cargos da comissão</span>
                <h2 id="commission-office-dialog-title">Adicionar cargo</h2>
            </div>
            <button class="dialog-close" type="button" data-dialog-close aria-label="Fechar">&times;</button>
        </header>
        <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/cargos') ?>" class="form planning-editor">
            <?= Csrf::field() ?>
            <label>Cargo já cadastrado
                <select name="cargo_id" <?= $availableOffices === [] ? 'disabled' : '' ?>>
                    <option value="">Selecione um cargo</option>
                    <?php foreach ($availableOffices as $office): ?>
                        <option value="<?= e($office['id']) ?>"><?= e($office['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="form-divider"><span>ou</span></div>

            <label>Novo cargo
                <input name="novo_cargo" maxlength="180" placeholder="Digite o nome do novo cargo">
            </label>
            <p class="form-help">Ao informar um novo cargo, ele também ficará disponível no cadastro geral de cargos.</p>

            <div class="form-actions">
                <button class="button" type="button" data-dialog-close>Cancelar</button>
                <button class="button primary">Adicionar</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>

<dialog id="commission-members-dialog" class="planning-dialog members-dialog" data-app-dialog data-auto-open="<?= $membersDialogOpen ? 'true' : 'false' ?>" aria-labelledby="commission-members-dialog-title">
    <header class="planning-dialog-header">
        <div>
            <span class="eyebrow">Comissão de nomeações</span>
            <h2 id="commission-members-dialog-title">Membros com direito a voto</h2>
        </div>
        <button class="dialog-close" type="button" data-dialog-close aria-label="Fechar">&times;</button>
    </header>

    <div class="members-dialog-content">
        <?php if ($editable): ?>
            <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/membros') ?>" class="form commission-member-form">
                <?= Csrf::field() ?>
                <label>Pessoa já cadastrada
                    <select name="pessoa_id" <?= $availableCommissionPeople === [] ? 'disabled' : '' ?>>
                        <option value="">Selecione uma pessoa</option>
                        <?php foreach ($availableCommissionPeople as $person): ?>
                            <option value="<?= e($person['id']) ?>"><?= e($person['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="form-divider"><span>ou</span></div>
                <label>Novo nome
                    <input name="nova_pessoa" maxlength="160" placeholder="Digite o nome completo">
                </label>
                <label>Classificação
                    <select name="papel" required>
                        <?php foreach ($memberRoleLabels as $role => $label): ?>
                            <option value="<?= e($role) ?>" <?= $role === 'membro' ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p class="form-help">Um novo nome também será incluído no cadastro geral de pessoas.</p>
                <div class="form-actions">
                    <button class="button primary">Adicionar membro</button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert commission-readonly">Esta comissão está disponível apenas para consulta.</div>
        <?php endif; ?>

        <div class="members-list-head">
            <strong>Participantes</strong>
            <span><?= e(count($commissionMembers)) ?> membro(s)</span>
        </div>
        <?php if ($commissionMembers === []): ?>
            <p class="empty-state">Nenhum membro foi cadastrado nesta comissão.</p>
        <?php else: ?>
            <div class="commission-members-list">
                <?php foreach ($commissionMembers as $member): ?>
                    <article class="commission-member-row">
                        <div class="commission-member-identity">
                            <strong><?= e($member['nome']) ?></strong>
                            <div>
                                <span>ID <?= e(sprintf('%03d', $member['id'])) ?></span>
                                <code title="Código de acesso"><?= e(sprintf('%03d%03d', $commission['id'], $member['id'])) ?></code>
                            </div>
                        </div>
                        <?php if ($editable): ?>
                            <div class="commission-member-actions">
                                <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/membros/' . $member['id']) ?>">
                                    <?= Csrf::field() ?>
                                    <label class="sr-only" for="member-role-<?= e($member['id']) ?>">Classificação de <?= e($member['nome']) ?></label>
                                    <select id="member-role-<?= e($member['id']) ?>" name="papel">
                                        <?php foreach ($memberRoleLabels as $role => $label): ?>
                                            <option value="<?= e($role) ?>" <?= $member['papel'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="button small">Salvar</button>
                                </form>
                                <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/membros/' . $member['id'] . '/excluir') ?>">
                                    <?= Csrf::field() ?>
                                    <button class="button small danger" data-confirm="Retirar este membro da comissão?">Retirar</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="member-role-badge role-<?= e($member['papel']) ?>"><?= e($memberRoleLabels[$member['papel']] ?? $member['papel']) ?></span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="vote-dialog-footer">
            <button class="button" type="button" data-dialog-close>Fechar</button>
        </div>
    </div>
</dialog>

<?php if ($selectedVote !== null): ?>
    <?php $voteEditable = $editable && $selectedVote['votacao_status'] === 'aberta'; ?>
    <?php $ballotEditable = $voteEditable && $voteFlow !== null && $voteFlow['status'] === 'votando'; ?>
    <dialog id="commission-vote-dialog" class="planning-dialog vote-dialog" data-app-dialog data-auto-open="true" aria-labelledby="commission-vote-dialog-title">
        <header class="planning-dialog-header">
            <div>
                <span class="eyebrow">Votação do cargo</span>
                <h2 id="commission-vote-dialog-title"><?= e($selectedVote['nome']) ?></h2>
            </div>
            <button class="dialog-close" type="button" data-dialog-close aria-label="Fechar">&times;</button>
        </header>

        <div class="vote-dialog-content">
            <section class="vote-indications-panel">
                <div>
                    <span class="eyebrow">Indicações dos membros</span>
                    <div class="vote-indications-title">
                        <strong>Receber indicações para este cargo</strong>
                        <?php if ($voteFlow !== null): ?>
                            <?php $flowLabel = $voteFlow['status'] === 'encerrado' && $selectedVote['votacao_status'] === 'aberta' ? 'Indicações encerradas' : ($voteFlowLabels[$voteFlow['status']] ?? $voteFlow['status']); ?>
                            <span class="vote-flow-status flow-<?= e($voteFlow['status']) ?>"><?= e($flowLabel) ?></span>
                            <span class="vote-flow-limit">Indicações: <?= e($voteFlow['limite_indicacoes']) ?></span>
                            <span class="vote-flow-limit">Votos: <?= e($voteFlow['limite_votos']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p>Os nomes indicados pelos membros da comissão serão apresentados neste popup.</p>
                </div>
                <div class="vote-stage-actions">
                    <?php if ($voteEditable && ($voteFlow === null || in_array($voteFlow['status'], ['aguardando', 'indicacao'], true))): ?>
                        <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/indicacoes/abrir') ?>" class="vote-indications-action">
                            <?= Csrf::field() ?>
                            <label>Limite de indicações
                                <input type="number" name="limite_indicacoes" value="<?= e($voteFlow['limite_indicacoes'] ?? 1) ?>" min="1" max="9" inputmode="numeric" required>
                            </label>
                            <label>Votos por membro
                                <input type="number" name="limite_votos" value="<?= e($voteFlow['limite_votos'] ?? 1) ?>" min="1" max="99" inputmode="numeric" required>
                            </label>
                            <button class="button primary"><?= $voteFlow !== null && $voteFlow['status'] === 'indicacao' ? 'Atualizar limite' : 'Abrir indicações' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($voteEditable && $voteFlow !== null && $voteFlow['status'] === 'indicacao'): ?>
                        <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/indicacoes/encerrar') ?>">
                            <?= Csrf::field() ?>
                            <button class="button" data-confirm="Encerrar as indicações para este cargo?">Encerrar indicações</button>
                        </form>
                    <?php elseif ($voteEditable && $voteFlow !== null && $voteFlow['status'] === 'encerrado'): ?>
                        <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/indicacoes/abrir') ?>">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="limite_indicacoes" value="<?= e($voteFlow['limite_indicacoes']) ?>">
                            <input type="hidden" name="limite_votos" value="<?= e($voteFlow['limite_votos']) ?>">
                            <button class="button" data-confirm="Reabrir as indicações mantendo o limite atual?">Reabrir indicações</button>
                        </form>
                        <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/abrir') ?>">
                            <?= Csrf::field() ?>
                            <button class="button primary" data-confirm="Abrir a votação para este cargo?">Abrir votações</button>
                        </form>
                    <?php elseif ($voteEditable && $voteFlow !== null && $voteFlow['status'] === 'votando'): ?>
                        <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/cancelar') ?>">
                            <?= Csrf::field() ?>
                            <button class="button danger" data-confirm="Cancelar esta votação? Os votos serão zerados e o cargo voltará para Aguardando.">Cancelar votação</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="indication-monitor" data-indication-monitor data-names-signature="<?= e(implode(',', array_column($indicationOverview['names'], 'id'))) ?>" data-endpoint="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/indicacoes/status') ?>">
                <div class="indication-monitor-head">
                    <div>
                        <span class="eyebrow">Participação dos membros</span>
                        <strong>Pendências deste cargo</strong>
                    </div>
                </div>
                <div class="participation-pending-grid">
                    <div class="participation-pending-block">
                        <strong>Faltam indicar</strong>
                        <div class="participation-member-list" data-missing-indications>
                            <?php foreach ($indicationOverview['missing_indications'] as $pendingMember): ?>
                                <span><strong><?= e($pendingMember['nome']) ?></strong><small><?= e($memberRoleLabels[$pendingMember['papel']] ?? $pendingMember['papel']) ?></small></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="participation-complete" data-missing-indications-empty <?= $indicationOverview['missing_indications'] === [] ? '' : 'hidden' ?>>Todos concluíram as indicações.</p>
                        <small>O presidente não participa desta contagem.</small>
                    </div>
                    <div class="participation-pending-block" data-missing-votes-block <?= $indicationOverview['vote_open'] ? '' : 'hidden' ?>>
                        <strong>Faltam votar</strong>
                        <div class="participation-member-list" data-missing-votes>
                            <?php foreach ($indicationOverview['missing_votes'] as $pendingMember): ?>
                                <span><strong><?= e($pendingMember['nome']) ?></strong><small><?= e($memberRoleLabels[$pendingMember['papel']] ?? $pendingMember['papel']) ?></small></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="participation-complete" data-missing-votes-empty <?= $indicationOverview['missing_votes'] === [] ? '' : 'hidden' ?>>Todos confirmaram o voto.</p>
                    </div>
                </div>
                <small>As pendências são atualizadas automaticamente a cada 30 segundos.</small>
            </section>

            <?php if ($voteEditable && !$ballotEditable): ?>
                <div class="alert vote-stage-message">
                    <?php if ($voteFlow === null || $voteFlow['status'] === 'aguardando'): ?>Abra as indicações para iniciar novamente o processo deste cargo.
                    <?php elseif ($voteFlow['status'] === 'indicacao'): ?>As indicações estão abertas. Encerre-as antes de abrir a votação.
                    <?php else: ?>As indicações foram encerradas. Use o botão Abrir votações para registrar os votos.<?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($voteEditable): ?>
                <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/pessoas') ?>" class="form vote-add-person">
                    <?= Csrf::field() ?>
                    <label>Pessoa já cadastrada
                        <select name="pessoa_id" <?= $availablePeople === [] ? 'disabled' : '' ?>>
                            <option value="">Selecione uma pessoa</option>
                            <?php foreach ($availablePeople as $person): ?>
                                <option value="<?= e($person['id']) ?>"><?= e($person['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="form-divider"><span>ou</span></div>
                    <label>Novo nome
                        <input name="nova_pessoa" maxlength="160" placeholder="Digite o nome completo">
                    </label>
                    <p class="form-help">Um novo nome será incluído também no cadastro de pessoas.</p>
                    <div class="form-actions">
                        <button class="button primary">Adicionar nome</button>
                    </div>
                </form>
            <?php elseif ($selectedVote['votacao_status'] === 'encerrada'): ?>
                <div class="alert vote-closed-message">Votação encerrada.</div>
            <?php else: ?>
                <div class="alert commission-readonly">Esta votação está disponível apenas para consulta.</div>
            <?php endif; ?>

            <?php if ($voteParticipants === []): ?>
                <p class="empty-state">Nenhum nome foi adicionado a esta votação.</p>
            <?php elseif ($ballotEditable): ?>
                <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/salvar') ?>" class="vote-form">
                    <?= Csrf::field() ?>
                    <div class="vote-person-list">
                        <?php foreach ($voteParticipants as $participant): ?>
                            <div class="vote-person-row">
                                <strong><?= e($participant['nome']) ?></strong>
                                <label>Votos
                                    <input type="number" name="votos[<?= e($participant['id']) ?>]" value="<?= e($participant['votos']) ?>" min="0" max="999999" inputmode="numeric" required>
                                </label>
                                <label>Classificação
                                    <select name="papeis[<?= e($participant['id']) ?>]" class="vote-role-select role-<?= e($participant['papel']) ?>" data-vote-role>
                                        <?php foreach ($roleLabels as $role => $label): ?>
                                            <option value="<?= e($role) ?>" <?= $participant['papel'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-actions vote-form-actions">
                        <button class="button" type="submit">Salvar</button>
                        <button class="button primary" type="submit" formaction="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/encerrar') ?>" data-confirm="Encerrar esta votação? Os dados ficarão disponíveis para consulta.">Encerrar votação</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="vote-person-list readonly">
                    <?php foreach ($voteParticipants as $participant): ?>
                        <div class="vote-person-row">
                            <strong><?= e($participant['nome']) ?></strong>
                            <span><small>Votos</small><b><?= e($participant['votos']) ?></b></span>
                            <span><small>Classificação</small><span class="vote-role-badge role-<?= e($participant['papel']) ?>"><?= e($roleLabels[$participant['papel']] ?? $participant['papel']) ?></span></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="vote-dialog-footer">
                <button class="button" type="button" data-dialog-close>Fechar</button>
                <?php if ($editable && $selectedVote['votacao_status'] === 'encerrada'): ?>
                    <form method="post" action="<?= url('/comissao/comissoes/' . $commission['id'] . '/votacoes/' . $selectedVote['comissao_cargo_id'] . '/reabrir') ?>">
                        <?= Csrf::field() ?>
                        <button class="button primary" data-confirm="Reabrir esta votação para edição?">REABRIR</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </dialog>
<?php endif; ?>
