<?php

declare(strict_types=1);

/** @var int $status */
/** @var string $title */
/** @var string $message */
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($status) ?> — <?= e($title) ?></title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font: 16px/1.5 system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        main { text-align: center; padding: 2rem; }
        h1 { margin: 0; font-size: 4rem; font-weight: 700; color: #475569; }
        p { margin: .5rem 0 0; color: #64748b; }
        @media (prefers-color-scheme: dark) { body { background: #0f172a; color: #f1f5f9; } h1 { color: #94a3b8; } p { color: #94a3b8; } }
    </style>
</head>
<body>
<main>
    <h1><?= e($status) ?></h1>
    <p>Ocorreu um erro no servidor. Tenta novamente mais tarde.</p>
</main>
</body>
</html>
