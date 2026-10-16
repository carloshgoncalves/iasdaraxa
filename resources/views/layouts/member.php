<?php
use IasdCentral\Core\Csrf;

$displayMode = $displayMode ?? false;
$success = $displayMode ? null : ($_SESSION['flash_success'] ?? null);
$error = $displayMode ? null : ($_SESSION['flash_error'] ?? null);
if (!$displayMode) {
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e($title ?? 'Comissão') ?> | IASD Central</title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="commission-member-body">
    <?php if ($success): ?><div class="alert success member-flash"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error member-flash"><?= e($error) ?></div><?php endif; ?>
    <?= $content ?>
    <script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
