<?php

declare(strict_types=1);

use IasdCentral\Core\Csrf;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Migrador | IASD Central</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f1f3; color: #251b20; font-family: Arial, sans-serif; }
        main { width: min(920px, calc(100% - 32px)); margin: 32px auto; }
        header, section, .alert, pre { margin-bottom: 14px; padding: 18px; border: 1px solid #ddd4d8; border-radius: 8px; background: #fff; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        h1, h2, p { margin-top: 0; }
        h1 { margin-bottom: 6px; }
        .destinations { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .destinations section { margin: 0; }
        .muted { color: #71666b; }
        .alert { border-color: #d49a49; background: #fff8e8; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; }
        button, a { min-height: 40px; padding: 10px 14px; border: 1px solid #7a1f38; border-radius: 6px; background: #7a1f38; color: #fff; cursor: pointer; font: inherit; text-decoration: none; }
        button.secondary { background: #fff; color: #7a1f38; }
        button:disabled { border-color: #aaa; background: #aaa; color: #fff; cursor: not-allowed; }
        pre { min-height: 260px; max-height: 520px; overflow: auto; background: #211e22; color: #eff5f1; white-space: pre-wrap; }
        @media (max-width: 700px) { header { align-items: stretch; flex-direction: column; } .destinations { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <header>
        <div><h1>Migrador IASD Central</h1><p class="muted">Ferramenta local de apoio ao desenvolvimento.</p></div>
        <a href="<?= url('/') ?>">Voltar ao site</a>
    </header>

    <?php if (!$configExists): ?>
        <div class="alert">Crie <code>migrador/config.php</code> a partir de <code>migrador/config.example.php</code>.</div>
    <?php endif; ?>

    <div class="destinations">
        <section><h2><code>/iasdaraxa.com.br</code></h2><p class="muted">Recebe o conteudo de <code>public/</code>.</p></section>
        <section><h2><code>/appiasdaraxa</code></h2><p class="muted">Recebe os arquivos privados da aplicacao.</p></section>
    </div>

    <section>
        <div class="actions">
            <button id="updateFtp" type="button" data-endpoint="<?= url('/migrador/atualizar-ftp') ?>" <?= $configExists ? '' : 'disabled' ?>>Atualizar FTP</button>
            <button class="secondary" id="syncProductionDatabase" type="button" data-endpoint="<?= url('/migrador/sincronizar-banco') ?>" <?= $configExists ? '' : 'disabled' ?>>Copiar banco local para producao</button>
        </div>
        <p class="muted">A copia do banco gera backup, recria as tabelas e substitui os dados de producao.</p>
        <input id="migratorCsrf" type="hidden" value="<?= e(Csrf::token()) ?>">
    </section>
    <pre id="migratorOutput" aria-live="polite"></pre>
</main>
<script>
const output = document.getElementById('migratorOutput');
const csrf = document.getElementById('migratorCsrf');

async function runAction(button, message, extraData = {}) {
    button.disabled = true;
    output.textContent = `${message}\n`;
    const data = new FormData();
    data.append('_csrf', csrf.value);
    Object.entries(extraData).forEach(([key, value]) => data.append(key, value));

    try {
        const response = await fetch(button.dataset.endpoint, { method: 'POST', body: data });
        if (!response.body) throw new Error('O servidor nao disponibilizou o fluxo de resposta.');
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            output.textContent += decoder.decode(value, { stream: true });
            output.scrollTop = output.scrollHeight;
        }
    } catch (error) {
        output.textContent += `\n[ERROR] ${error.message}`;
    } finally {
        button.disabled = false;
    }
}

document.getElementById('updateFtp')?.addEventListener('click', (event) => runAction(event.currentTarget, 'Iniciando atualizacao FTP...'));
document.getElementById('syncProductionDatabase')?.addEventListener('click', (event) => {
    const confirmation = window.prompt('Digite COPIAR PARA PRODUCAO para substituir o banco de producao.');
    if (confirmation === 'COPIAR PARA PRODUCAO') runAction(event.currentTarget, 'Iniciando copia do banco local para producao...', { confirmation });
});
</script>
</body>
</html>
