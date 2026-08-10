<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'IASD Central') ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="public-body">
<header class="public-header">
    <a class="public-brand" href="<?= url('/') ?>">
        <span>IC</span>
        <strong>IASD Central</strong>
    </a>
    <nav>
        <a href="<?= url('/') ?>">Inicio</a>
        <a href="#agenda">Agenda</a>
        <a href="#contato">Contato</a>
        <a class="button small" href="<?= url('/login') ?>">Entrar</a>
    </nav>
</header>
<?= $content ?>
</body>
</html>

