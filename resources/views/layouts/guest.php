<?php
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Entrar') ?> | IASD Central</title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="guest-body">
<?php if ($error): ?><div class="alert error floating"><?= e($error) ?></div><?php endif; ?>
<?= $content ?>
</body>
</html>

