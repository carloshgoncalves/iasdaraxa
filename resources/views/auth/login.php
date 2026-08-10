<?php use IasdCentral\Core\Csrf; ?>
<section class="login-panel">
    <div class="brand login-brand"><span>IC</span><strong>IASD Central</strong></div>
    <h1>Entrar no painel</h1>
    <p>Acesse usuarios, menus e modulos administrativos da igreja.</p>
    <form method="post" action="<?= url('/login') ?>" class="form">
        <?= Csrf::field() ?>
        <label>Usuario <input type="text" name="username" required maxlength="80" autocomplete="username" autofocus></label>
        <label>Senha <input type="password" name="password" required></label>
        <button class="button primary">Entrar</button>
    </form>
</section>
