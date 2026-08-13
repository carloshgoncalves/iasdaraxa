<?php use IasdCentral\Core\Csrf; ?>

<section class="panel no-margin migrator-head">
    <div>
        <span class="eyebrow">Administracao</span>
        <h2>Publicar IASD Central</h2>
        <p class="muted">Envia os arquivos para as duas pastas fixas da hospedagem.</p>
    </div>
    <a class="button" href="<?= url($user ? '/configuracoes' : '/') ?>"><?= $user ? 'Voltar para configuracoes' : 'Voltar ao site' ?></a>
</section>

<?php if (!$configExists): ?>
    <div class="alert error">
        Crie <code>migrador/config.php</code> a partir de <code>migrador/config.example.php</code> antes de publicar.
    </div>
<?php endif; ?>

<div class="migrator-destinations">
    <section class="panel no-margin">
        <span class="eyebrow">Destino publico</span>
        <h2><code>/iasdaraxa.com.br</code></h2>
        <p class="muted">Recebe todo o conteudo local de <code>public/</code>.</p>
    </section>
    <section class="panel no-margin">
        <span class="eyebrow">Destino privado</span>
        <h2><code>/appiasdaraxa</code></h2>
        <p class="muted">Recebe o restante da aplicacao, sem duplicar a pasta <code>public/</code>.</p>
    </section>
</div>

<section class="panel no-margin">
    <div class="form-actions">
        <button
            class="button primary"
            id="updateFtp"
            type="button"
            data-endpoint="<?= url('/migrador/atualizar-ftp') ?>"
            <?= $configExists ? '' : 'disabled' ?>
        >Atualizar FTP</button>
    </div>
    <input id="migratorCsrf" type="hidden" value="<?= e(Csrf::token()) ?>">
    <pre id="migratorOutput" class="migrator-output" aria-live="polite"></pre>
</section>
