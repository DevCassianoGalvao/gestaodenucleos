<?php $assetVersion = (string) filemtime(ROOT_PATH . '/public/assets/css/main.css'); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Security::esc($pageTitle ?? 'Painel') ?> — <?= Security::esc(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= Security::esc(APP_URL) ?>/public/assets/css/main.css?v=<?= $assetVersion ?>">
  <?php if (!empty($extraCss)): ?>
    <?php foreach ((array) $extraCss as $css): ?>
      <link rel="stylesheet" href="<?= Security::esc($css) ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>

<div class="app-layout">

  <!-- Sidebar -->
  <?php require_once __DIR__ . '/../components/sidebar.php'; ?>

  <!-- Main area -->
  <div class="main-content" id="mainContent">

    <!-- Top bar -->
    <?php require_once __DIR__ . '/../components/topbar.php'; ?>

    <!-- Flash messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="flash-wrap" style="padding:.75rem 1.5rem 0">
        <div class="alert alert-success" role="alert" data-autohide="5000">
          <i data-lucide="check-circle" style="width:16px;height:16px;flex-shrink:0"></i>
          <span><?= Security::esc($_SESSION['flash_success']) ?></span>
        </div>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="flash-wrap" style="padding:.75rem 1.5rem 0">
        <div class="alert alert-error" role="alert" data-autohide="7000">
          <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0"></i>
          <span><?= Security::esc($_SESSION['flash_error']) ?></span>
        </div>
      </div>
      <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Page content -->
    <div class="page-content">
      <?= $content ?? '' ?>
    </div>

  </div><!-- /.main-content -->

</div><!-- /.app-layout -->

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Lucide icons -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="<?= Security::esc(APP_URL) ?>/public/assets/js/main.js?v=<?= $assetVersion ?>"></script>
<?php if (!empty($extraJs)): ?>
  <?php foreach ((array) $extraJs as $js): ?>
    <script src="<?= Security::esc($js) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
