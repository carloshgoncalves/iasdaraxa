<section class="grid cards-3">
    <article class="card">
        <span>Obras ativas</span>
        <strong><?= e($obraSummary['ativas'] ?? 0) ?></strong>
    </article>
    <article class="card">
        <span>Pendencias de obras</span>
        <strong><?= e($obraSummary['pendencias'] ?? 0) ?></strong>
    </article>
    <article class="card">
        <span>Contratos ativos</span>
        <strong><?= e($obraSummary['contratos'] ?? 0) ?></strong>
    </article>
</section>
<section class="panel">
    <h2>Prioridade atual</h2>
    <p class="muted">O modulo de Obras foi preparado como primeira area operacional para controlar reformas, equipes contratadas, pendencias e prazos.</p>
    <a class="button primary" href="<?= url('/obras') ?>">Abrir Obras</a>
</section>

