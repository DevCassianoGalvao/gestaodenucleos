<?php $assetVersion = (string) filemtime(ROOT_PATH . '/public/assets/css/main.css'); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= Security::esc($pageTitle ?? APP_NAME) ?> — <?= Security::esc(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= Security::esc(APP_URL) ?>/public/assets/css/main.css?v=<?= $assetVersion ?>">
  <style>
    body { background: var(--cinza-claro); min-height: 100vh; display: flex; flex-direction: column; }
    .public-wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    .public-card {
      background: #fff;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      width: 100%;
      max-width: 480px;
    }
    .public-header {
      background: var(--azul-marinho);
      border-radius: var(--radius-lg) var(--radius-lg) 0 0;
      padding: 2rem;
      text-align: center;
      color: #fff;
    }
    .public-header-logo {
      width: 56px;
      height: 56px;
      border-radius: 12px;
      background: rgba(255,255,255,.15);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
    }
    .public-header h1 { font-size: 1.25rem; font-weight: 700; margin: 0 0 .25rem; }
    .public-header p  { font-size: .85rem; opacity: .75; margin: 0; }
    .public-body { padding: 2rem; }
    .public-footer {
      padding: 1.25rem 2rem;
      text-align: center;
      border-top: 1px solid var(--cinza-borda);
      font-size: .75rem;
      color: var(--cinza-texto);
    }
  </style>
</head>
<body>
<div class="public-wrapper">
  <div class="public-card">
    <?= $content ?? '' ?>
  </div>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
  });
</script>
</body>
</html>
