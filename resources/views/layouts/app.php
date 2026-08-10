<?php
use IasdCentral\Core\Csrf;
use IasdCentral\Repositories\SettingsRepository;

$user = $user ?? null;
$menus = $menus ?? [];
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
$templateSettings = (new SettingsRepository())->all();
$fontFamilies = ['Arial' => 'Arial, Helvetica, sans-serif', 'Inter' => 'Inter, Arial, sans-serif', 'Georgia' => 'Georgia, serif', 'System' => 'system-ui, sans-serif'];
$templateFont = $fontFamilies[$templateSettings['font_family']] ?? $fontFamilies['Arial'];
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="<?= e($templateSettings['language']) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e($title ?? 'Painel') ?> | IASD Central</title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script src="<?= asset('js/app.js') ?>" defer></script>
</head>
<body class="admin-body density-<?= e($templateSettings['layout_density']) ?> theme-<?= e($templateSettings['theme_mode']) ?>" style="--brand: <?= e($templateSettings['brand_color']) ?>; --sidebar-bg: <?= e($templateSettings['sidebar_color']) ?>; --sidebar-width: <?= e($templateSettings['sidebar_width']) ?>px; --app-font: <?= e($templateFont) ?>;">
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= url('/dashboard') ?>"><span>IC</span><strong>IASD Central</strong></a>
    <nav>
        <?php foreach ($menus as $item): ?>
            <?php if (($item['tipo'] ?? '') === 'Tela'): ?>
                <a href="<?= url((string) $item['rota']) ?>"><?= e($item['nome']) ?></a>
            <?php else: ?>
                <div class="nav-group">
                    <span><?= e($item['nome']) ?></span>
                    <?php foreach (($item['children'] ?? []) as $child): ?>
                        <a href="<?= url((string) $child['rota']) ?>"><?= e($child['nome']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a class="settings-link" href="<?= url('/configuracoes') ?>"><span aria-hidden="true">&#9881;</span> Configuracoes</a>
        <form method="post" action="<?= url('/logout') ?>"><?= Csrf::field() ?><button class="link-button">Sair</button></form>
    </div>
</aside>
<main class="main">
    <header class="topbar">
        <button class="icon-button" id="menuToggle" type="button" aria-label="Abrir menu">☰</button>
        <div>
            <span class="eyebrow">Painel administrativo</span>
            <h1><?= e($title ?? 'Painel') ?></h1>
        </div>
        <span class="user-chip"><?= e($user['name'] ?? 'Usuario') ?></span>
    </header>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?= $content ?>
</main>
</body>
</html>
