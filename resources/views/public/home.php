<?php
$hero = $news[0] ?? null;
$fallback = $hero === null;
?>
<main>
    <section class="hero">
        <div class="hero-media" style="background-image: url('<?= e($hero['image_url'] ?: asset('img/igreja-fallback.svg')) ?>')"></div>
        <div class="hero-content">
            <span>IASD Central</span>
            <h1><?= e($fallback ? 'Uma igreja para servir, acolher e anunciar esperança.' : $hero['title']) ?></h1>
            <p><?= e($fallback ? 'Quando nao houver noticias recentes, esta pagina permanece com uma chamada institucional limpa e atual.' : mb_strimwidth((string) $hero['summary'], 0, 180, '...')) ?></p>
            <a class="button primary" href="<?= e($fallback ? '#agenda' : (string) $hero['source_url']) ?>">Ver agora</a>
        </div>
    </section>

    <section class="public-section">
        <div>
            <span class="eyebrow">Comunidade</span>
            <h2>Programacao e comunicados recentes</h2>
        </div>
        <div class="news-grid">
            <?php if ($news === []): ?>
                <article class="news-card">
                    <h3>Pagina padrao ativa</h3>
                    <p>Sem noticias recentes no feed. A capa permanece institucional para nao destacar conteudo antigo.</p>
                </article>
            <?php endif; ?>
            <?php foreach ($news as $item): ?>
                <article class="news-card">
                    <span><?= e(date_br($item['published_at'] ?? null)) ?></span>
                    <h3><?= e($item['title']) ?></h3>
                    <p><?= e(mb_strimwidth((string) $item['summary'], 0, 130, '...')) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="public-band" id="agenda">
        <h2>Agenda da igreja</h2>
        <p>Escola Sabatina, culto divino, JA, pequeno grupo e atividades missionarias em um unico lugar.</p>
    </section>

    <section class="public-section" id="contato">
        <div>
            <span class="eyebrow">Contato</span>
            <h2>IASD Central</h2>
        </div>
        <p class="muted">Enderecos, liderancas e canais oficiais serao cadastrados no painel administrativo.</p>
    </section>
</main>

