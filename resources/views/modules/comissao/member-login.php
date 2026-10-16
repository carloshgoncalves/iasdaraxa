<?php use IasdCentral\Core\Csrf; ?>
<main class="member-access-shell">
    <section class="member-login-card">
        <div class="member-access-brand" aria-hidden="true">IC</div>
        <span class="eyebrow">Comissão de nomeações</span>
        <h1>Acesso do membro</h1>
        <p>Digite o código de seis dígitos fornecido pela comissão.</p>

        <form method="post" action="<?= url('/comissao/membro/entrar') ?>" class="form member-code-form">
            <?= Csrf::field() ?>
            <label for="member-access-code">Código do membro</label>
            <input id="member-access-code" name="codigo" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" placeholder="000000" data-member-code autofocus required>
            <button class="button primary">Entrar</button>
        </form>
        <small>O código identifica você e a comissão da qual participa.</small>
    </section>
</main>
